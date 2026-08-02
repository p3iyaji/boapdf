<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\PdfConversionService;
use App\Services\PdfMergeService;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPdfMergeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    /**
     * @param  list<int>  $sourceDocumentIds
     */
    public function __construct(
        public int $documentId,
        public array $sourceDocumentIds,
    ) {}

    public function handle(PdfMergeService $merger, PdfConversionService $conversion): void
    {
        $document = Document::query()->findOrFail($this->documentId);

        $sources = Document::query()
            ->whereIn('id', $this->sourceDocumentIds)
            ->where('user_id', $document->user_id)
            ->where('status', Document::STATUS_COMPLETED)
            ->get()
            ->keyBy('id');

        $paths = [];
        foreach ($this->sourceDocumentIds as $id) {
            $source = $sources->get($id);
            if ($source === null) {
                throw new \RuntimeException('A source document is missing or incomplete.');
            }
            $paths[] = $source->absolutePath();
        }

        $absolutePath = $merger->merge($paths);
        $relativePath = 'merged/'.basename($absolutePath);

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
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('PDF merge job failed', [
            'document_id' => $this->documentId,
            'error' => $e?->getMessage(),
        ]);

        Document::query()->whereKey($this->documentId)->update([
            'status' => Document::STATUS_FAILED,
            'metadata' => ['error' => 'Merge failed.'],
        ]);
    }
}
