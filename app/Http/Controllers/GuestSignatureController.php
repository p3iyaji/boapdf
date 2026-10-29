<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignPdfRequest;
use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\DocumentSigningService;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GuestSignatureController extends Controller
{
    public function __construct(private DocumentSigningService $signing) {}

    public function show(string $token): View|RedirectResponse
    {
        $signatureRequest = $this->signing->findOpenRequestByToken($token);

        if ($signatureRequest === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'This signing link is invalid or has expired.',
            ]);
        }

        $document = $signatureRequest->sourceDocument;

        return view('pdf.sign', [
            'signatureRequest' => $signatureRequest,
            'document' => $document,
            'token' => $token,
            'guestMode' => true,
            'signatureRequests' => collect(),
        ]);
    }

    public function stream(string $token): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $signatureRequest = $this->signing->findOpenRequestByToken($token);

        if ($signatureRequest === null) {
            abort(404);
        }

        $document = $this->signing->stampBaseDocument($signatureRequest->sourceDocument);

        if (! $document->isFileReady()) {
            abort(404, 'Document file is not available.');
        }

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $document->original_name,
            preg_replace('/[^\x20-\x7E]/', '_', $document->original_name) ?: 'document.pdf',
        );

        return response()->file($document->absolutePath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }

    public function store(SignPdfRequest $request, string $token, AuditLogger $auditLogger): RedirectResponse
    {
        $signatureRequest = $this->signing->findOpenRequestByToken($token);

        if ($signatureRequest === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'This signing link is invalid or has expired.',
            ]);
        }

        try {
            $signed = $this->signing->queueGuestSign($signatureRequest, $request->validated());

            if ($signed->status === Document::STATUS_FAILED) {
                return back()->withErrors(['sign' => 'Could not apply your signature. Please try again.']);
            }

            $auditLogger->log(
                action: 'signature.guest_signed',
                description: 'Guest signed a document via invitation link.',
                subject: $signatureRequest,
                metadata: [
                    'signer_email' => $signatureRequest->signer_email,
                    'signer_name' => $signatureRequest->signer_name,
                    'document_id' => $signatureRequest->source_document_id,
                    'signed_document_id' => $signed->id,
                ],
                actor: null,
                request: $request,
                actorName: $signatureRequest->signer_name,
                actorEmail: $signatureRequest->signer_email,
            );

            return redirect()
                ->route('sign.guest.thanks', $token)
                ->with('success', 'Thank you — your signature has been applied.');
        } catch (Throwable $e) {
            Log::error('Guest PDF signing failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['sign' => SafeUserMessage::from($e, 'Could not apply your signature')]);
        }
    }

    public function thanks(Request $request, string $token): View
    {
        return view('pdf.guest-sign-thanks', [
            'token' => $token,
        ]);
    }
}
