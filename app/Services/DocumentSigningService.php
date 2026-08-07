<?php

namespace App\Services;

use App\Jobs\ProcessPdfSignJob;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Notifications\SignatureInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class DocumentSigningService
{
    /**
     * Latest completed signed copy for this source, or the source itself.
     */
    public function stampBaseDocument(Document $source): Document
    {
        $latest = Document::query()
            ->where('parent_document_id', $source->id)
            ->where('operation_type', Document::OP_SIGNED)
            ->where('status', Document::STATUS_COMPLETED)
            ->latest('id')
            ->first();

        return $latest ?? $source;
    }

    public function createPendingSignedDocument(User $owner, Document $source): Document
    {
        return Document::create([
            'user_id' => $owner->id,
            'original_name' => pathinfo($source->original_name, PATHINFO_FILENAME).'-signed.pdf',
            'file_path' => 'signed/pending-'.uniqid('', true).'.pdf',
            'file_size' => 0,
            'mime_type' => 'application/pdf',
            'pages' => 0,
            'status' => Document::STATUS_PROCESSING,
            'operation_type' => Document::OP_SIGNED,
            'parent_document_id' => $source->id,
            'metadata' => [],
        ]);
    }

    /**
     * @param  array{
     *     signature?: string|null,
     *     page?: int|null,
     *     x?: float|null,
     *     y?: float|null,
     *     width?: float|null,
     *     typed_signature?: string|null,
     *     typed_page?: int|null,
     *     typed_x?: float|null,
     *     typed_y?: float|null,
     *     typed_width?: float|null,
     *     logo?: string|null,
     *     logo_page?: int|null,
     *     logo_x?: float|null,
     *     logo_y?: float|null,
     *     logo_width?: float|null
     * }  $overlayData
     */
    public function queueSelfSign(User $user, Document $source, array $overlayData): Document
    {
        $signed = $this->createPendingSignedDocument($user, $source);
        $stampFrom = $this->stampBaseDocument($source);

        ProcessPdfSignJob::dispatch($signed->id, $stampFrom->id, [
            ...$overlayData,
            'requester_email' => $user->email,
            'signer_email' => $user->email,
            'signer_name' => $user->name,
            'source_document_id' => $source->id,
        ]);

        return $signed->fresh();
    }

    /**
     * @param  list<array{email: string, name?: string|null}>  $signers
     * @return list<SignatureRequest>
     */
    public function inviteSigners(User $requester, Document $source, array $signers, int $expiresInDays = 7): array
    {
        if ($signers === []) {
            throw new InvalidArgumentException('At least one signer is required.');
        }

        $maxOrder = (int) SignatureRequest::query()
            ->where('source_document_id', $source->id)
            ->max('sort_order');

        $created = [];

        DB::transaction(function () use ($requester, $source, $signers, $expiresInDays, &$maxOrder, &$created): void {
            foreach ($signers as $signer) {
                $email = strtolower(trim($signer['email']));

                $existingPending = SignatureRequest::query()
                    ->where('source_document_id', $source->id)
                    ->where('signer_email', $email)
                    ->where('status', SignatureRequest::STATUS_PENDING)
                    ->where(function ($q): void {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->exists();

                if ($existingPending) {
                    continue;
                }

                $maxOrder++;

                $request = SignatureRequest::create([
                    'document_id' => $source->id,
                    'source_document_id' => $source->id,
                    'requester_email' => $requester->email,
                    'signer_email' => $email,
                    'signer_name' => filled($signer['name'] ?? null) ? trim((string) $signer['name']) : null,
                    'token' => SignatureRequest::generateToken(),
                    'status' => SignatureRequest::STATUS_PENDING,
                    'sort_order' => $maxOrder,
                    'expires_at' => now()->addDays($expiresInDays),
                ]);

                $created[] = $request;
            }
        });

        foreach ($created as $request) {
            Notification::route('mail', $request->signer_email)
                ->notify(new SignatureInvitation($request));
        }

        return $created;
    }

    /**
     * @param  array{
     *     signature?: string|null,
     *     page?: int|null,
     *     x?: float|null,
     *     y?: float|null,
     *     width?: float|null,
     *     typed_signature?: string|null,
     *     typed_page?: int|null,
     *     typed_x?: float|null,
     *     typed_y?: float|null,
     *     typed_width?: float|null,
     *     logo?: string|null,
     *     logo_page?: int|null,
     *     logo_x?: float|null,
     *     logo_y?: float|null,
     *     logo_width?: float|null
     * }  $overlayData
     */
    public function queueGuestSign(SignatureRequest $signatureRequest, array $overlayData): Document
    {
        if (! $signatureRequest->isOpenForSigning()) {
            throw new InvalidArgumentException('This signing link is no longer valid.');
        }

        $source = $signatureRequest->sourceDocument
            ?? Document::query()->findOrFail($signatureRequest->source_document_id);

        $owner = $source->user;
        if ($owner === null) {
            throw new InvalidArgumentException('Document owner is missing.');
        }

        $signed = $this->createPendingSignedDocument($owner, $source);
        $stampFrom = $this->stampBaseDocument($source);

        ProcessPdfSignJob::dispatch($signed->id, $stampFrom->id, [
            ...$overlayData,
            'requester_email' => $signatureRequest->requester_email ?? $owner->email,
            'signer_email' => $signatureRequest->signer_email,
            'signer_name' => $signatureRequest->signer_name,
            'source_document_id' => $source->id,
            'signature_request_id' => $signatureRequest->id,
        ]);

        return $signed->fresh();
    }

    public function findOpenRequestByToken(string $token): ?SignatureRequest
    {
        $request = SignatureRequest::query()
            ->where('token', $token)
            ->with(['sourceDocument'])
            ->first();

        if ($request === null || ! $request->isOpenForSigning()) {
            return null;
        }

        return $request;
    }

    public function cancelInvite(Document $source, SignatureRequest $signatureRequest): void
    {
        if ((int) $signatureRequest->source_document_id !== (int) $source->id) {
            throw new InvalidArgumentException('This signature request does not belong to the document.');
        }

        if ($signatureRequest->isSigned()) {
            throw new InvalidArgumentException('Signed requests cannot be removed.');
        }

        $signatureRequest->delete();
    }
}
