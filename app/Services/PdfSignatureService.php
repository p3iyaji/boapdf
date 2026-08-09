<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParserException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

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
        return $this->addSignatures($pdfPath, [[
            'image_path' => $signatureImagePath,
            'page' => (int) ($position['page'] ?? 1),
            'x' => (float) ($position['x'] ?? 20),
            'y' => isset($position['y']) ? (float) $position['y'] : null,
            'width' => (float) ($position['width'] ?? 60),
            'max_raster_width' => $maxRasterWidth,
        ]]);
    }

    /**
     * Stamp multiple raster overlays onto a PDF in a single FPDI pass.
     *
     * @param  list<array{
     *     image_path: string,
     *     page: int,
     *     x: float,
     *     y?: float|null,
     *     width: float,
     *     max_raster_width?: int
     * }>  $overlays
     */
    public function addSignatures(string $pdfPath, array $overlays): string
    {
        if (! file_exists($pdfPath)) {
            throw new RuntimeException("PDF not found: {$pdfPath}");
        }

        if ($overlays === []) {
            throw new RuntimeException('At least one signature overlay is required.');
        }

        $optimizedPaths = [];
        $compatibleTemp = null;

        try {
            $prepared = [];

            foreach ($overlays as $index => $overlay) {
                $imagePath = (string) ($overlay['image_path'] ?? '');
                if ($imagePath === '' || ! file_exists($imagePath)) {
                    throw new RuntimeException("Signature image not found for overlay #{$index}.");
                }

                $maxWidth = (int) ($overlay['max_raster_width'] ?? 600);
                $optimized = $this->prepareRasterImage($imagePath, $maxWidth);
                $optimizedPaths[] = $optimized;

                $prepared[] = [
                    'image_path' => $optimized,
                    'page' => (int) ($overlay['page'] ?? 1),
                    'x' => (float) ($overlay['x'] ?? 20),
                    'y' => array_key_exists('y', $overlay) && $overlay['y'] !== null
                        ? (float) $overlay['y']
                        : null,
                    'width' => (float) ($overlay['width'] ?? 60),
                ];
            }

            [$pdf, $pageCount, $compatibleTemp] = $this->fpdiFromPath($pdfPath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $template = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);

                foreach ($prepared as $item) {
                    if ($item['page'] !== $pageNo) {
                        continue;
                    }

                    $y = $item['y'] ?? ($pdf->GetPageHeight() - 40);
                    $pdf->Image($item['image_path'], $item['x'], $y, $item['width'], 0, 'PNG');
                }
            }

            $outputPath = $this->ensureDirectory('signed').'/'.Str::uuid().'.pdf';
            $pdf->Output('F', $outputPath);

            return $outputPath;
        } finally {
            foreach ($optimizedPaths as $path) {
                if (is_string($path) && file_exists($path)) {
                    @unlink($path);
                }
            }

            if (is_string($compatibleTemp) && file_exists($compatibleTemp)) {
                @unlink($compatibleTemp);
            }
        }
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

    /**
     * Open a PDF with FPDI, rewriting via qpdf when compressed object streams block the free parser.
     *
     * @return array{0: Fpdi, 1: int, 2: string|null}
     */
    public function fpdiFromPath(string $pdfPath): array
    {
        try {
            $pdf = new Fpdi;
            $pageCount = $pdf->setSourceFile($pdfPath);

            return [$pdf, $pageCount, null];
        } catch (CrossReferenceException|PdfParserException $e) {
            $rewritten = $this->rewriteForFpdi($pdfPath);
            if ($rewritten === null) {
                throw new RuntimeException(
                    'This PDF uses a format that cannot be signed with the available tools. Try re-saving or compressing it first.',
                    previous: $e,
                );
            }

            try {
                $pdf = new Fpdi;
                $pageCount = $pdf->setSourceFile($rewritten);

                return [$pdf, $pageCount, $rewritten];
            } catch (Throwable $retryError) {
                @unlink($rewritten);

                throw new RuntimeException(
                    'This PDF uses a format that cannot be signed with the available tools. Try re-saving or compressing it first.',
                    previous: $retryError,
                );
            }
        }
    }

    private function rewriteForFpdi(string $pdfPath): ?string
    {
        $qpdf = $this->qpdfBinary();
        if ($qpdf === null) {
            Log::warning('FPDI could not parse PDF and qpdf is unavailable', ['path' => $pdfPath]);

            return null;
        }

        $outputPath = $this->ensureDirectory('temp').'/fpdi_'.Str::uuid().'.pdf';
        $process = new Process([
            $qpdf,
            '--object-streams=disable',
            $pdfPath,
            $outputPath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            Log::warning('qpdf rewrite for FPDI failed', [
                'path' => $pdfPath,
                'error' => trim($process->getErrorOutput().' '.$process->getOutput()),
            ]);

            return null;
        }

        return $outputPath;
    }

    private function qpdfBinary(): ?string
    {
        $configured = config('pdf.qpdf_path');
        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $found = (new ExecutableFinder)->find('qpdf', null, ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin']);
        if (is_string($found) && $found !== '') {
            return $found;
        }

        foreach (['/opt/homebrew/bin/qpdf', '/usr/local/bin/qpdf', '/usr/bin/qpdf'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
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
