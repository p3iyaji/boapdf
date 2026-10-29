<?php

namespace App\Jobs;

use App\Models\ConversionLog;
use App\Models\Document;
use App\Services\PdfConversionService;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessPdfConvertJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $documentId,
        public int $sourceDocumentId,
        public string $target,
        public ?string $password = null,
    ) {}

    public function handle(PdfConversionService $conversion): void
    {
        $document = Document::query()->findOrFail($this->documentId);
        $source = Document::query()
            ->whereKey($this->sourceDocumentId)
            ->where('user_id', $document->user_id)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf')
            ->firstOrFail();

        $start = microtime(true);

        $result = $conversion->convertFromPdf(
            $source->absolutePath(),
            $this->target,
            $this->password,
        );

        $relativePath = 'converted/'.basename($result['path']);
        $peakMemoryMb = (int) ceil(memory_get_peak_usage(true) / 1024 / 1024);
        $baseName = pathinfo($source->original_name, PATHINFO_FILENAME);
        $downloadName = $result['extension'] === 'zip'
            ? $baseName.'-'.$this->target.'-pages.zip'
            : $baseName.'.'.$result['extension'];

        $document->update([
            'original_name' => $downloadName,
            'file_path' => $relativePath,
            'file_size' => DocumentsDisk::disk()->size($relativePath),
            'mime_type' => $result['mime_type'],
            'pages' => $result['pages'],
            'status' => Document::STATUS_COMPLETED,
            'metadata' => [
                'target' => $this->target,
                'packaged' => $result['extension'] === 'zip',
                'artifact_count' => count($result['files']),
            ],
        ]);

        ConversionLog::create([
            'document_id' => $source->id,
            'source_format' => 'pdf',
            'target_format' => $this->target,
            'processing_time_ms' => (int) ((microtime(true) - $start) * 1000),
            'memory_usage_mb' => $peakMemoryMb,
            'status' => 'success',
        ]);
    }

    public function failed(?Throwable $e): void
    {
        $message = $e?->getMessage() ?? 'Conversion failed.';
        $safeMessage = Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?? ''), 500) ?: 'Conversion failed.';

        Log::error('PDF convert job failed', [
            'document_id' => $this->documentId,
            'target' => $this->target,
            'error' => $safeMessage,
        ]);

        ConversionLog::create([
            'document_id' => $this->sourceDocumentId,
            'source_format' => 'pdf',
            'target_format' => $this->target,
            'processing_time_ms' => 0,
            'status' => 'failed',
            'error_message' => $safeMessage,
        ]);

        Document::query()->whereKey($this->documentId)->update([
            'status' => Document::STATUS_FAILED,
            'metadata' => ['error' => $safeMessage, 'target' => $this->target],
        ]);
    }
}
