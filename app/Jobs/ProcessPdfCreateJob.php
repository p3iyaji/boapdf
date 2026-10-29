<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\PdfConversionService;
use App\Services\PdfCreateService;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPdfCreateJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        public int $documentId,
        public array $definition,
    ) {}

    public function handle(PdfCreateService $creator, PdfConversionService $conversion): void
    {
        $document = Document::query()->findOrFail($this->documentId);
        $absolutePath = null;

        try {
            $absolutePath = $creator->create($this->definition);
            $relativePath = 'created/'.basename($absolutePath);

            $pages = 0;
            try {
                $pages = $conversion->countPages($absolutePath);
            } catch (Throwable) {
                $pages = count($this->definition['pages'] ?? []);
            }

            $document->update([
                'file_path' => $relativePath,
                'file_size' => DocumentsDisk::disk()->size($relativePath),
                'pages' => $pages,
                'status' => Document::STATUS_COMPLETED,
                'mime_type' => 'application/pdf',
                'metadata' => [
                    'page_size' => $this->definition['page_size'] ?? 'A4',
                    'orientation' => $this->definition['orientation'] ?? 'P',
                    'page_count' => $pages,
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
        Log::error('PDF create job failed', [
            'document_id' => $this->documentId,
            'error' => $e?->getMessage(),
        ]);

        Document::query()->whereKey($this->documentId)->update([
            'status' => Document::STATUS_FAILED,
            'metadata' => ['error' => 'PDF creation failed.'],
        ]);
    }
}
