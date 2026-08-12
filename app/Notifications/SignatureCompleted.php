<?php

namespace App\Notifications;

use App\Models\SignatureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignatureCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SignatureRequest $signatureRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->signatureRequest->loadMissing('sourceDocument');
        $document = $request->sourceDocument;
        $documentName = $document?->original_name ?? 'your document';
        $signerLabel = $request->signer_name ?: $request->signer_email;

        $envelopeId = (int) $request->source_document_id;
        $totalSigners = SignatureRequest::query()
            ->where('source_document_id', $envelopeId)
            ->count();
        $signedCount = SignatureRequest::query()
            ->where('source_document_id', $envelopeId)
            ->where('status', SignatureRequest::STATUS_SIGNED)
            ->count();
        $allComplete = $totalSigners > 0 && $signedCount >= $totalSigners;

        $subject = $allComplete
            ? "All signatures complete: {$documentName}"
            : "Signature received: {$documentName}";

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.signature-completed', [
                'ownerName' => $notifiable->name ?? null,
                'signerLabel' => $signerLabel,
                'signerEmail' => $request->signer_email,
                'documentName' => $documentName,
                'signedAt' => $request->signed_at,
                'signedCount' => $signedCount,
                'totalSigners' => $totalSigners,
                'allComplete' => $allComplete,
                'documentUrl' => $document ? route('pdf.show', $document) : route('pdf.index'),
                'signingUrl' => $document ? route('pdf.sign.create', $document) : route('pdf.index'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'signature_request_id' => $this->signatureRequest->id,
            'document_id' => $this->signatureRequest->source_document_id,
        ];
    }
}
