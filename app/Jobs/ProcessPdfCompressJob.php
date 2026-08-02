<?php

namespace App\Jobs;

use App\Models\ConversionLog;
use App\Models\Document;
use App\Services\PdfCompressionService;
use App\Services\PdfConversionService;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPdfCompressJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public int $documentId,
        public int $sourceDocumentId,
        public string $level,
    ) {}

    public function handle(PdfCompressionService $compressor, PdfConversionService $conversion): void
    {
        $document = Document::query()->findOrFail($this->documentId);
        $source = Document::query()
            ->whereKey($this->sourceDocumentId)
            ->where('user_id', $document->user_id)
            ->where('status', Document::STATUS_COMPLETED)
            ->firstOrFail();

        $start = microtime(true);
        $result = $compressor->compress($source->absolutePath(), $this->level);
        $relativePath = 'compressed/'.basename($result['path']);

        $pages = 0;
        try {
            $pages = $conversion->countPages($result['path']);
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
                'level' => $this->level,
                'method' => $result['method'],
                'original_size' => $result['original_size'],
                'new_size' => $result['new_size'],
            ],
        ]);

        ConversionLog::create([
            'document_id' => $document->id,
            'source_format' => 'pdf',
            'target_format' => 'pdf',
            'processing_time_ms' => (int) ((microtime(true) - $start) * 1000),
            'status' => 'success',
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('PDF compress job failed', [
            'document_id' => $this->documentId,
            'error' => $e?->getMessage(),
        ]);

        ConversionLog::create([
            'document_id' => $this->sourceDocumentId,
            'source_format' => 'pdf',
            'target_format' => 'pdf',
            'processing_time_ms' => 0,
            'status' => 'failed',
            'error_message' => 'Compression failed.',
        ]);

        Document::query()->whereKey($this->documentId)->update([
            'status' => Document::STATUS_FAILED,
            'metadata' => ['error' => 'Compression failed.'],
        ]);
    }
}
