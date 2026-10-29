<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\PdfEditException;
use App\Services\PdfEditService;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPdfEditJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $documentId,
        public int $sourceDocumentId,
        public string $mode,
        public array $payload,
    ) {}

    public function handle(PdfEditService $editor): void
    {
        $document = Document::query()->findOrFail($this->documentId);
        $source = Document::query()->whereKey($this->sourceDocumentId)->firstOrFail();

        if ($document->user_id !== null && $source->user_id !== null && $document->user_id !== $source->user_id) {
            $this->markFailed($document, 'Could not edit this PDF.');

            return;
        }

        $absolutePath = null;

        try {
            $result = $this->mode === 'pages'
                ? $editor->applyPages($source->absolutePath(), $this->payload['pages'] ?? [])
                : $editor->applyContent($source->absolutePath(), $this->payload);

            $absolutePath = $result['path'];
            $relativePath = $this->relativePath($absolutePath);

            $existing = $document->metadata instanceof \Traversable
                ? iterator_to_array($document->metadata)
                : [];

            $document->update([
                'file_path' => $relativePath,
                'file_size' => DocumentsDisk::disk()->size($relativePath),
                'pages' => $result['pages'],
                'status' => Document::STATUS_COMPLETED,
                'mime_type' => 'application/pdf',
                'metadata' => [...$existing, 'mode' => $this->mode],
            ]);
        } catch (PdfEditException $exception) {
            $this->deleteOutput($absolutePath);
            $this->markFailed($document, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->deleteOutput($absolutePath);
            Log::error('PDF edit job failed', [
                'document_id' => $this->documentId,
                'error' => $exception->getMessage(),
            ]);
            $this->markFailed($document, 'Could not edit this PDF.');
        } finally {
            $this->deleteAssets();
        }
    }

    private function markFailed(Document $document, string $message): void
    {
        $document->update([
            'status' => Document::STATUS_FAILED,
            'metadata' => ['error' => $message, 'mode' => $this->mode],
        ]);
    }

    private function relativePath(string $absolute): string
    {
        $root = rtrim(DocumentsDisk::disk()->path(''), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($absolute, $root)) {
            throw new PdfEditException('Could not edit this PDF.');
        }

        return ltrim(substr($absolute, strlen($root)), DIRECTORY_SEPARATOR);
    }

    private function deleteOutput(?string $absolutePath): void
    {
        if (is_string($absolutePath) && is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    private function deleteAssets(): void
    {
        foreach ($this->payload['images'] ?? [] as $image) {
            $path = $image['path'] ?? null;

            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
}
