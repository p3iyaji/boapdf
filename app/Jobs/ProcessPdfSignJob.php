<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\SignatureRequest;
use App\Services\PdfConversionService;
use App\Services\PdfSignatureService;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPdfSignJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

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
     *     logo_width?: float|null,
     *     requester_email: string,
     *     signer_email: string
     * }  $payload
     */
    public function __construct(
        public int $documentId,
        public int $sourceDocumentId,
        public array $payload,
    ) {}

    public function handle(PdfSignatureService $signer, PdfConversionService $conversion): void
    {
        $document = Document::query()->findOrFail($this->documentId);
        $source = Document::query()
            ->whereKey($this->sourceDocumentId)
            ->where('user_id', $document->user_id)
            ->firstOrFail();

        $data = $this->payload;
        $absolutePath = null;
        $metadata = [];
        $signaturePosition = [];

        try {
            if (! empty($data['signature'])) {
                $drawPosition = [
                    'page' => (int) $data['page'],
                    'x' => (float) $data['x'],
                    'y' => (float) $data['y'],
                    'width' => isset($data['width']) ? (float) $data['width'] : 60.0,
                ];
                $metadata['signature'] = $drawPosition;
                $signaturePosition['signature'] = $drawPosition;

                $image = $signer->createSignatureFromDataUrl($data['signature']);
                $absolutePath = $signer->addSignature($source->absolutePath(), $image, $drawPosition);
                @unlink($image);
            }

            if (! empty($data['typed_signature'])) {
                $typedPosition = [
                    'page' => (int) $data['typed_page'],
                    'x' => (float) $data['typed_x'],
                    'y' => (float) $data['typed_y'],
                    'width' => isset($data['typed_width']) ? (float) $data['typed_width'] : 60.0,
                ];
                $metadata['typed_text'] = $typedPosition;
                $signaturePosition['typed_text'] = $typedPosition;

                $image = $signer->createSignatureFromDataUrl($data['typed_signature']);
                $inputPdf = $absolutePath ?? $source->absolutePath();
                $nextPath = $signer->addSignature($inputPdf, $image, $typedPosition);
                @unlink($image);
                if ($absolutePath !== null && $nextPath !== $absolutePath && file_exists($absolutePath)) {
                    @unlink($absolutePath);
                }
                $absolutePath = $nextPath;
            }

            if (! empty($data['logo'])) {
                $logoPosition = [
                    'page' => (int) $data['logo_page'],
                    'x' => (float) $data['logo_x'],
                    'y' => (float) $data['logo_y'],
                    'width' => (float) $data['logo_width'],
                ];
                $metadata['logo'] = $logoPosition;
                $signaturePosition['logo'] = $logoPosition;

                $logoImage = $signer->createImageFromDataUrl($data['logo']);
                $inputPdf = $absolutePath ?? $source->absolutePath();
                $stampedPath = $signer->addSignature($inputPdf, $logoImage, $logoPosition, 800);
                @unlink($logoImage);
                if ($absolutePath !== null && $stampedPath !== $absolutePath && file_exists($absolutePath)) {
                    @unlink($absolutePath);
                }
                $absolutePath = $stampedPath;
            }

            if ($absolutePath === null) {
                throw new \RuntimeException('Nothing to apply.');
            }

            $relativePath = 'signed/'.basename($absolutePath);
            $pages = 0;
            try {
                $pages = $conversion->countPages($absolutePath);
            } catch (Throwable) {
                // ignore
            }

            $document->update([
                'file_path' => $relativePath,
                'file_size' => DocumentsDisk::disk()->size($relativePath),
                'pages' => $pages,
                'status' => Document::STATUS_COMPLETED,
                'mime_type' => 'application/pdf',
                'metadata' => $metadata,
            ]);

            SignatureRequest::create([
                'document_id' => $document->id,
                'requester_email' => $data['requester_email'],
                'signer_email' => $data['signer_email'],
                'signature_position' => $signaturePosition,
                'status' => SignatureRequest::STATUS_SIGNED,
                'signed_file_path' => $relativePath,
            ]);
        } catch (Throwable $e) {
            if ($absolutePath !== null && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('PDF sign job failed', [
            'document_id' => $this->documentId,
            'error' => $e?->getMessage(),
        ]);

        Document::query()->whereKey($this->documentId)->update([
            'status' => Document::STATUS_FAILED,
            'metadata' => ['error' => 'Signing failed.'],
        ]);
    }
}
