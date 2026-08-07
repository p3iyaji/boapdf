<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\PdfConversionService;
use App\Services\PdfFormFillService;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPdfFormFillJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function __construct(
        public int $documentId,
        public int $sourceDocumentId,
        public array $fields,
    ) {}

    public function handle(PdfFormFillService $filler, PdfConversionService $conversion): void
    {
        $document = Document::query()->findOrFail($this->documentId);
        $source = Document::query()->whereKey($this->sourceDocumentId)->firstOrFail();

        if ($document->user_id !== null && $source->user_id !== null && $document->user_id !== $source->user_id) {
            throw new \RuntimeException('Form fill source does not belong to the document owner.');
        }

        $absolutePath = null;

        try {
            $absolutePath = $filler->fillFields($source->absolutePath(), $this->fields);
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
                    'field_count' => count($this->fields),
                    'fields' => collect($this->fields)->map(fn (array $f): array => [
                        'name' => $f['name'] ?? null,
                        'type' => $f['type'] ?? null,
                        'page' => $f['page'] ?? null,
                        'value' => is_bool($f['value'] ?? null)
                            ? ($f['value'] ? 'Yes' : 'No')
                            : (string) ($f['value'] ?? ''),
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
        Log::error('PDF form fill job failed', [
            'document_id' => $this->documentId,
            'error' => $e?->getMessage(),
        ]);

        Document::query()->whereKey($this->documentId)->update([
            'status' => Document::STATUS_FAILED,
            'metadata' => ['error' => 'Form fill failed.'],
        ]);
    }
}
