<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PdfSignatureService
{
    /**
     * Add a raster image (signature or logo) to a PDF on the specified page/position.
     *
     * Position units are millimetres on the PDF page. Height follows the image aspect ratio (width-driven).
     *
     * @param  array{x?: float, y?: float, page?: int, width?: float}  $position
     */
    public function addSignature(string $pdfPath, string $signatureImagePath, array $position = [], int $maxRasterWidth = 600): string
    {
        if (! file_exists($pdfPath)) {
            throw new RuntimeException("PDF not found: {$pdfPath}");
        }
        if (! file_exists($signatureImagePath)) {
            throw new RuntimeException("Signature image not found: {$signatureImagePath}");
        }

        $optimizedSignature = $this->prepareRasterImage($signatureImagePath, $maxRasterWidth);

        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($pdfPath);

        $targetPage = (int) ($position['page'] ?? 1);
        $x = (float) ($position['x'] ?? 20);
        $width = (float) ($position['width'] ?? 60);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $template = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            if ($pageNo === $targetPage) {
                $y = (float) ($position['y'] ?? ($pdf->GetPageHeight() - 40));
                $pdf->Image($optimizedSignature, $x, $y, $width, 0, 'PNG');
            }
        }

        $outputPath = $this->ensureDirectory('signed').'/'.Str::uuid().'.pdf';
        $pdf->Output('F', $outputPath);

        @unlink($optimizedSignature);

        return $outputPath;
    }

    /**
     * Convert a base64-encoded PNG (from an HTML canvas) into a temp file.
     */
    public function createSignatureFromDataUrl(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new RuntimeException('Invalid signature data URL.');
        }

        $imageData = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        if ($imageData === false) {
            throw new RuntimeException('Could not decode signature image.');
        }

        $tempDir = $this->ensureDirectory('temp');
        $tempPath = $tempDir.'/'.Str::uuid().'.png';
        file_put_contents($tempPath, $imageData);
        $this->assertValidImage($tempPath);

        return $tempPath;
    }

    /**
     * Decode a data URL (PNG, JPEG, WebP, or GIF) into a temporary file for stamping.
     */
    public function createImageFromDataUrl(string $dataUrl): string
    {
        if (! preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#i', $dataUrl, $matches)) {
            throw new RuntimeException('Invalid image data URL.');
        }

        $base64Offset = stripos($dataUrl, 'base64,');
        if ($base64Offset === false) {
            throw new RuntimeException('Invalid image data URL.');
        }

        $imageData = base64_decode(substr($dataUrl, $base64Offset + strlen('base64,')), true);
        if ($imageData === false) {
            throw new RuntimeException('Could not decode image.');
        }

        $ext = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);

        $tempDir = $this->ensureDirectory('temp');
        $tempPath = $tempDir.'/'.Str::uuid().'.'.$ext;
        file_put_contents($tempPath, $imageData);
        $this->assertValidImage($tempPath);

        return $tempPath;
    }

    private function assertValidImage(string $path): void
    {
        $info = @getimagesize($path);
        if ($info === false) {
            @unlink($path);
            throw new RuntimeException('The uploaded image is not a valid raster image.');
        }
    }

    private function prepareRasterImage(string $path, int $maxWidth): string
    {
        $manager = ImageManager::gd();
        $image = $manager->read($path);
        $image->scale(width: $maxWidth);

        $optimizedPath = $this->ensureDirectory('temp').'/raster_'.Str::uuid().'.png';
        $image->toPng()->save($optimizedPath);

        return $optimizedPath;
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
