<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteSignersRequest;
use App\Http\Requests\SignPdfRequest;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Services\DocumentSigningService;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SignatureController extends Controller
{
    public function __construct(private DocumentSigningService $signing) {}

    public function create(Request $request, Document $document): View
    {
        $this->authorize('view', $document);

        $signatureRequests = SignatureRequest::query()
            ->where('source_document_id', $document->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('pdf.sign', [
            'document' => $document,
            'signatureRequests' => $signatureRequests,
        ]);
    }

    public function store(SignPdfRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $data = $request->validated();

        try {
            $signed = $this->signing->queueSelfSign($request->user(), $document, $data);

            if ($signed->status === Document::STATUS_FAILED) {
                return back()->withErrors(['sign' => 'Could not update PDF. Please try again.']);
            }

            $message = $signed->status === Document::STATUS_COMPLETED
                ? 'PDF signed successfully.'
                : 'Signing queued. Refresh this page shortly for the result.';

            return redirect()->route('pdf.show', $signed)->with('success', $message);
        } catch (Throwable $e) {
            Log::error('PDF signing failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['sign' => SafeUserMessage::from($e, 'Could not update PDF')]);
        }
    }

    public function invite(InviteSignersRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $signers = collect($request->validated('signers'))
            ->map(fn (array $signer): array => [
                'email' => $signer['email'],
                'name' => $signer['name'] ?? null,
            ])
            ->all();

        try {
            $created = $this->signing->inviteSigners($request->user(), $document, $signers);

            if ($created === []) {
                return back()->withErrors([
                    'signers' => 'Those signers already have an open invitation for this document.',
                ]);
            }

            $count = count($created);
            $message = $count === 1
                ? 'Signature request sent to '.$created[0]->signer_email.'.'
                : "Signature requests sent to {$count} people.";

            return redirect()
                ->route('pdf.sign.create', $document)
                ->with('success', $message);
        } catch (Throwable $e) {
            Log::error('Signature invite failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['signers' => SafeUserMessage::from($e, 'Could not send signature requests')]);
        }
    }

    public function destroyInvite(Request $request, Document $document, SignatureRequest $signatureRequest): RedirectResponse
    {
        $this->authorize('update', $document);

        try {
            $this->signing->cancelInvite($document, $signatureRequest);

            return redirect()
                ->route('pdf.sign.create', ['document' => $document, 'tab' => 'invite'])
                ->with('success', 'Removed '.$signatureRequest->signer_email.' from this signing request.');
        } catch (Throwable $e) {
            Log::error('Signature invite cancel failed', ['error' => $e->getMessage()]);

            return back()->withErrors([
                'signers' => SafeUserMessage::from($e, 'Could not remove this signer'),
            ]);
        }
    }
}
