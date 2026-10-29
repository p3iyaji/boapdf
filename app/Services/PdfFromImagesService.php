<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use FPDF;
use Illuminate\Support\Str;
use RuntimeException;

class PdfFromImagesService
{
    /**
     * Build a single A4 PDF with one page per image, each image scaled to fill the page (contain).
     *
     * @param  list<string>  $absoluteImagePaths  Readable paths to JPEG or PNG files.
     */
    public function buildPdfFromImages(array $absoluteImagePaths): string
    {
        if ($absoluteImagePaths === []) {
            throw new RuntimeException('At least one image is required.');
        }

        $pdf = new FPDF('P', 'mm', 'A4');
        $pageW = 210.0;
        $pageH = 297.0;
        $margin = 0.0;
        $maxW = $pageW - 2 * $margin;
        $maxH = $pageH - 2 * $margin;
        $nominalDpi = 150.0;

        foreach ($absoluteImagePaths as $path) {
            if (! is_readable($path)) {
                throw new RuntimeException("Image not readable: {$path}");
            }

            $info = @getimagesize($path);
            if ($info === false) {
                throw new RuntimeException("Invalid image: {$path}");
            }

            [$pxW, $pxH] = $info;
            $imgWmm = $pxW * 25.4 / $nominalDpi;
            $imgHmm = $pxH * 25.4 / $nominalDpi;
            // Always scale to the largest size that still fits the page (up or down).
            $scale = min($maxW / max($imgWmm, 0.01), $maxH / max($imgHmm, 0.01));
            $drawW = $imgWmm * $scale;
            $drawH = $imgHmm * $scale;
            $x = ($pageW - $drawW) / 2.0;
            $y = ($pageH - $drawH) / 2.0;

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $type = match ($ext) {
                'png' => 'PNG',
                'jpg', 'jpeg' => 'JPEG',
                default => throw new RuntimeException("Unsupported image extension: {$ext}"),
            };

            $pdf->AddPage();
            $pdf->Image($path, $x, $y, $drawW, $drawH, $type);
        }

        $disk = DocumentsDisk::disk();
        if (! $disk->exists('uploads')) {
            $disk->makeDirectory('uploads');
        }

        $outputRelative = 'uploads/'.Str::uuid().'.pdf';
        $outputPath = $disk->path($outputRelative);
        $pdf->Output('F', $outputPath);

        return $outputRelative;
    }
}
