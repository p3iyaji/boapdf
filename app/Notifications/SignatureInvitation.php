<?php

namespace App\Notifications;

use App\Models\SignatureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignatureInvitation extends Notification implements ShouldQueue
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
        $documentName = $request->sourceDocument?->original_name ?? 'a document';
        $requester = $request->requester_email ?? 'Someone';
        $greetingName = $request->signer_name ?: null;

        $mail = (new MailMessage)
            ->subject('Please sign: '.$documentName)
            ->markdown('mail.signature-invitation', [
                'signerName' => $greetingName,
                'requesterEmail' => $requester,
                'documentName' => $documentName,
                'url' => $request->signingUrl(),
                'expiresAt' => $request->expires_at,
            ]);

        return $mail;
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
