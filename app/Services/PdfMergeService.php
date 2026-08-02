<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PdfMergeService
{
    /**
     * Merge multiple PDFs into one.
     *
     * @param  array<int, string>  $filePaths  Absolute paths to source PDFs.
     * @return string Absolute path to the merged PDF.
     */
    public function merge(array $filePaths, ?string $outputFileName = null): string
    {
        if (empty($filePaths)) {
            throw new RuntimeException('No files provided for merging.');
        }

        $pdf = new Fpdi;

        foreach ($filePaths as $filePath) {
            if (! file_exists($filePath)) {
                throw new RuntimeException("File not found: {$filePath}");
            }

            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $template = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }

        $fileName = $outputFileName ?? (Str::uuid().'.pdf');
        $outputPath = $this->ensureDirectory('merged').'/'.$fileName;
        $pdf->Output('F', $outputPath);

        return $outputPath;
    }

    /**
     * Merge with explicit page selection per file.
     *
     * @param  array<int, array{path: string, pages?: array<int, int>|null}>  $filesWithPages
     */
    public function mergeWithPageSelection(array $filesWithPages): string
    {
        $pdf = new Fpdi;

        foreach ($filesWithPages as $item) {
            $filePath = $item['path'] ?? null;
            if (! $filePath || ! file_exists($filePath)) {
                throw new RuntimeException("File not found: {$filePath}");
            }

            $totalPages = $pdf->setSourceFile($filePath);
            $pagesToImport = $item['pages'] ?? range(1, $totalPages);

            foreach ($pagesToImport as $pageNo) {
                if ($pageNo < 1 || $pageNo > $totalPages) {
                    continue;
                }

                $template = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }

        $outputPath = $this->ensureDirectory('merged').'/'.Str::uuid().'.pdf';
        $pdf->Output('F', $outputPath);

        return $outputPath;
    }

    private function ensureDirectory(string $subPath): string
    {
        $disk = DocumentsDisk::disk();
        if (! $disk->exists($subPath)) {
            $disk->makeDirectory($subPath);
        }

        return $disk->path($subPath);
    }
}
