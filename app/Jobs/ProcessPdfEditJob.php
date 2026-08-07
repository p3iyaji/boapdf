<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\PdfConversionService;
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

    public int $tries = 2;

    /**
     * @param  list<array<string, mixed>>  $annotations
     */
    public function __construct(
        public int $documentId,
        public int $sourceDocumentId,
        public array $annotations,
    ) {}

    public function handle(PdfEditService $editor, PdfConversionService $conversion): void
    {
        $document = Document::query()->findOrFail($this->documentId);
        $source = Document::query()->whereKey($this->sourceDocumentId)->firstOrFail();

        if ($document->user_id !== null && $source->user_id !== null && $document->user_id !== $source->user_id) {
            throw new \RuntimeException('Edit source does not belong to the document owner.');
        }

        $absolutePath = null;

        try {
            $absolutePath = $editor->applyAnnotations($source->absolutePath(), $this->annotations);
            $relativePath = 'edited/'.basename($absolutePath);

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
                'metadata' => [
                    'annotation_count' => count($this->annotations),
                    'annotations' => collect($this->annotations)->map(fn (array $a): array => [
                        'type' => $a['type'] ?? null,
                        'page' => $a['page'] ?? null,
                        'x' => $a['x'] ?? null,
                        'y' => $a['y'] ?? null,
                        'width' => $a['width'] ?? null,
                    ])->all(),
                ],
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
        Log::error('PDF edit job failed', [
            'document_id' => $this->documentId,
            'error' => $e?->getMessage(),
        ]);

        Document::query()->whereKey($this->documentId)->update([
            'status' => Document::STATUS_FAILED,
            'metadata' => ['error' => 'Editing failed.'],
        ]);
    }
}
