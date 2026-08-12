<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\SignatureRequest;
use App\Notifications\SignatureCompleted;
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
     *     typed_texts?: list<array{image: string, page: int|float|string, x: float|int|string, y: float|int|string, width?: float|int|string|null}>|null,
     *     logo?: string|null,
     *     logo_page?: int|null,
     *     logo_x?: float|null,
     *     logo_y?: float|null,
     *     logo_width?: float|null,
     *     requester_email: string,
     *     signer_email: string,
     *     signer_name?: string|null,
     *     source_document_id?: int|null,
     *     signature_request_id?: int|null
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
        $source = Document::query()->whereKey($this->sourceDocumentId)->firstOrFail();

        if ($document->user_id !== null && $source->user_id !== null && $document->user_id !== $source->user_id) {
            throw new \RuntimeException('Stamp source does not belong to the signed document owner.');
        }

        $data = $this->payload;
        $tempImages = [];
        $absolutePath = null;
        $metadata = [];
        $signaturePosition = [];
        $overlays = [];

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
                $tempImages[] = $image;
                $overlays[] = [
                    'image_path' => $image,
                    'page' => $drawPosition['page'],
                    'x' => $drawPosition['x'],
                    'y' => $drawPosition['y'],
                    'width' => $drawPosition['width'],
                    'max_raster_width' => 600,
                ];
            }

            $typedTexts = $this->normalizeTypedTexts($data);

            if ($typedTexts !== []) {
                $typedPositions = [];

                foreach ($typedTexts as $typedItem) {
                    $typedPosition = [
                        'page' => (int) $typedItem['page'],
                        'x' => (float) $typedItem['x'],
                        'y' => (float) $typedItem['y'],
                        'width' => isset($typedItem['width']) ? (float) $typedItem['width'] : 60.0,
                    ];
                    $typedPositions[] = $typedPosition;

                    $image = $signer->createSignatureFromDataUrl($typedItem['image']);
                    $tempImages[] = $image;
                    $overlays[] = [
                        'image_path' => $image,
                        'page' => $typedPosition['page'],
                        'x' => $typedPosition['x'],
                        'y' => $typedPosition['y'],
                        'width' => $typedPosition['width'],
                        'max_raster_width' => 600,
                    ];
                }

                $metadata['typed_texts'] = $typedPositions;
                $metadata['typed_text'] = $typedPositions[0];
                $signaturePosition['typed_texts'] = $typedPositions;
                $signaturePosition['typed_text'] = $typedPositions[0];
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
                $tempImages[] = $logoImage;
                $overlays[] = [
                    'image_path' => $logoImage,
                    'page' => $logoPosition['page'],
                    'x' => $logoPosition['x'],
                    'y' => $logoPosition['y'],
                    'width' => $logoPosition['width'],
                    'max_raster_width' => 800,
                ];
            }

            if ($overlays === []) {
                throw new \RuntimeException('Nothing to apply.');
            }

            $absolutePath = $signer->addSignatures($source->absolutePath(), $overlays);

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

            $envelopeId = isset($data['source_document_id'])
                ? (int) $data['source_document_id']
                : (int) ($document->parent_document_id ?: $this->sourceDocumentId);

            if (! empty($data['signature_request_id'])) {
                SignatureRequest::query()
                    ->whereKey((int) $data['signature_request_id'])
                    ->where('status', SignatureRequest::STATUS_PENDING)
                    ->update([
                        'document_id' => $document->id,
                        'source_document_id' => $envelopeId,
                        'signature_position' => $signaturePosition,
                        'status' => SignatureRequest::STATUS_SIGNED,
                        'signed_file_path' => $relativePath,
                        'signed_at' => now(),
                    ]);

                $this->notifyDocumentOwnerOfCompletedSignature((int) $data['signature_request_id']);
            } else {
                SignatureRequest::create([
                    'document_id' => $document->id,
                    'source_document_id' => $envelopeId,
                    'requester_email' => $data['requester_email'],
                    'signer_email' => $data['signer_email'],
                    'signer_name' => $data['signer_name'] ?? null,
                    'signature_position' => $signaturePosition,
                    'status' => SignatureRequest::STATUS_SIGNED,
                    'signed_file_path' => $relativePath,
                    'signed_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            if ($absolutePath !== null && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
            throw $e;
        } finally {
            foreach ($tempImages as $path) {
                if (is_string($path) && file_exists($path)) {
                    @unlink($path);
                }
            }
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

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{image: string, page: int|float|string, x: float|int|string, y: float|int|string, width?: float|int|string|null}>
     */
    private function normalizeTypedTexts(array $data): array
    {
        if (! empty($data['typed_texts']) && is_array($data['typed_texts'])) {
            $normalized = [];

            foreach ($data['typed_texts'] as $item) {
                if (! is_array($item) || empty($item['image'])) {
                    continue;
                }

                $normalized[] = [
                    'image' => (string) $item['image'],
                    'page' => $item['page'] ?? 1,
                    'x' => $item['x'] ?? 0,
                    'y' => $item['y'] ?? 0,
                    'width' => $item['width'] ?? 60.0,
                ];
            }

            return $normalized;
        }

        if (! empty($data['typed_signature'])) {
            return [[
                'image' => (string) $data['typed_signature'],
                'page' => $data['typed_page'] ?? 1,
                'x' => $data['typed_x'] ?? 0,
                'y' => $data['typed_y'] ?? 0,
                'width' => $data['typed_width'] ?? 60.0,
            ]];
        }

        return [];
    }

    private function notifyDocumentOwnerOfCompletedSignature(int $signatureRequestId): void
    {
        $signatureRequest = SignatureRequest::query()
            ->with('sourceDocument.user')
            ->find($signatureRequestId);

        if ($signatureRequest === null || ! $signatureRequest->isSigned()) {
            return;
        }

        $owner = $signatureRequest->sourceDocument?->user;
        if ($owner === null) {
            return;
        }

        $owner->notify(new SignatureCompleted($signatureRequest));
    }
}
