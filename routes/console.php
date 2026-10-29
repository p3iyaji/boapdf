<?php

use App\Models\Document;
use App\Support\DocumentsDisk;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Delete unreferenced generated PDF artifacts older than `pdf.temp_cleanup_hours`.
 *
 * Files still linked from `documents.file_path` are never deleted here — use
 * `pdf:prune-expired-documents` for library retention.
 */
Artisan::command('pdf:cleanup', function () {
    $cutoff = now()->subHours((int) config('pdf.temp_cleanup_hours', 24))->getTimestamp();
    $disk = DocumentsDisk::disk();
    $removed = 0;

    foreach (['merged', 'compressed', 'signed', 'converted', 'edited', 'created', 'temp'] as $dir) {
        if (! $disk->exists($dir)) {
            continue;
        }
        foreach ($disk->files($dir) as $file) {
            if ($disk->lastModified($file) >= $cutoff) {
                continue;
            }

            if (Document::query()->where('file_path', $file)->exists()) {
                continue;
            }

            $disk->delete($file);
            $removed++;
        }
    }

    $this->info("Pruned {$removed} unreferenced PDF artifact(s).");
})->purpose('Remove unreferenced generated PDF outputs older than the configured cutoff.');

Schedule::command('pdf:cleanup')->daily();
Schedule::command('pdf:prune-expired-documents')->daily();
