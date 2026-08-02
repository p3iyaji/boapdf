<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Support\DocumentsDisk;
use Illuminate\Console\Command;

class PruneExpiredDocumentsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pdf:prune-expired-documents {--days= : Retention period in days (overrides config)}';

    /**
     * @var string
     */
    protected $description = 'Remove library PDF documents (and their stored files) older than the configured retention.';

    public function handle(): int
    {
        $opt = $this->option('days');
        if ($opt !== null && $opt !== '') {
            $days = max(1, (int) $opt);
        } else {
            $configured = (int) config('pdf.upload_retention_days', 30);
            if ($configured < 1) {
                $this->comment('pdf.upload_retention_days is less than 1; skipping library prune.');

                return self::SUCCESS;
            }
            $days = max(1, $configured);
        }

        $cutoff = now()->subDays($days);
        $disk = DocumentsDisk::disk();
        $removed = 0;

        Document::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($documents) use ($disk, &$removed): void {
                foreach ($documents as $document) {
                    if ($disk->exists($document->file_path)) {
                        $disk->delete($document->file_path);
                    }
                    $document->delete();
                    $removed++;
                }
            });

        $this->info("Removed {$removed} document(s) with created_at before {$cutoff->toIso8601String()} (retention: {$days} day(s)).");

        return self::SUCCESS;
    }
}
