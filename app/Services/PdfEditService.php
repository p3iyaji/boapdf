<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PdfEditService
{
    public function __construct(private PdfSignatureService $images) {}

    /**
     * Stamp annotations onto a PDF in a single pass (original page content stays vector-intact).
     *
     * Position units are millimetres. Supported types: text, image, draw, highlight.
     *
     * @param  list<array{
     *     type: string,
     *     page: int,
     *     x: float,
     *     y: float,
     *     width?: float,
     *     height?: float,
     *     text?: string,
     *     font_size?: float,
     *     color?: string,
     *     image?: string
     * }>  $annotations
     */
    public function applyAnnotations(string $pdfPath, array $annotations): string
    {
        if (! file_exists($pdfPath)) {
            throw new RuntimeException("PDF not found: {$pdfPath}");
        }

        if ($annotations === []) {
            throw new RuntimeException('At least one annotation is required.');
        }

        $prepared = [];
        $tempFiles = [];

        try {
            foreach ($annotations as $index => $annotation) {
                $type = strtolower((string) ($annotation['type'] ?? ''));
                $page = (int) ($annotation['page'] ?? 0);
                if ($page < 1) {
                    throw new RuntimeException("Annotation #{$index} has an invalid page.");
                }

                $item = [
                    'type' => $type,
                    'page' => $page,
                    'x' => (float) ($annotation['x'] ?? 0),
                    'y' => (float) ($annotation['y'] ?? 0),
                    'width' => (float) ($annotation['width'] ?? 60),
                    'height' => isset($annotation['height']) ? (float) $annotation['height'] : null,
                    'text' => (string) ($annotation['text'] ?? ''),
                    'font_size' => (float) ($annotation['font_size'] ?? 12),
                    'color' => $this->parseHexColor((string) ($annotation['color'] ?? '#111827')),
                ];

                if (in_array($type, ['image', 'draw'], true)) {
                    $dataUrl = (string) ($annotation['image'] ?? '');
                    if ($dataUrl === '') {
                        throw new RuntimeException("Annotation #{$index} is missing an image.");
                    }
                    $raw = $type === 'draw'
                        ? $this->images->createSignatureFromDataUrl($dataUrl)
                        : $this->images->createImageFromDataUrl($dataUrl);
                    $optimized = $this->prepareRasterImage($raw, $type === 'draw' ? 600 : 800);
                    @unlink($raw);
                    $tempFiles[] = $optimized;
                    $item['image_path'] = $optimized;
                }

                if ($type === 'highlight') {
                    $item['height'] = $item['height'] ?? max(4.0, $item['font_size']);
                    $item['color'] = $this->parseHexColor((string) ($annotation['color'] ?? '#FDE047'));
                }

                if ($type === 'text' && trim($item['text']) === '') {
                    throw new RuntimeException("Annotation #{$index} text cannot be empty.");
                }

                if (! in_array($type, ['text', 'image', 'draw', 'highlight'], true)) {
                    throw new RuntimeException("Unsupported annotation type: {$type}");
                }

                $prepared[] = $item;
            }

            [$pdf, $pageCount, $compatibleTemp] = $this->images->fpdiFromPath($pdfPath);
            if (is_string($compatibleTemp)) {
                $tempFiles[] = $compatibleTemp;
            }

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $template = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);

                foreach ($prepared as $item) {
                    if ($item['page'] !== $pageNo) {
                        continue;
                    }

                    match ($item['type']) {
                        'text' => $this->drawText($pdf, $item),
                        'highlight' => $this->drawHighlight($pdf, $item),
                        'image', 'draw' => $pdf->Image(
                            $item['image_path'],
                            $item['x'],
                            $item['y'],
                            $item['width'],
                            0,
                            'PNG'
                        ),
                        default => null,
                    };
                }
            }

            $outputPath = $this->ensureDirectory('edited').'/'.Str::uuid().'.pdf';
            $pdf->Output('F', $outputPath);

            return $outputPath;
        } finally {
            foreach ($tempFiles as $path) {
                if (is_string($path) && file_exists($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * @param  array{x: float, y: float, width: float, height: ?float, text: string, font_size: float, color: array{r: int, g: int, b: int}}  $item
     */
    private function drawText(Fpdi $pdf, array $item): void
    {
        [$r, $g, $b] = [$item['color']['r'], $item['color']['g'], $item['color']['b']];
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetFont('Helvetica', '', max(6, min(72, $item['font_size'])));
        $pdf->SetXY($item['x'], $item['y']);
        $width = max(10.0, $item['width']);
        $pdf->MultiCell($width, max(4.0, $item['font_size'] * 0.45), $this->toPdfString($item['text']), 0, 'L');
    }

    /**
     * @param  array{x: float, y: float, width: float, height: ?float, color: array{r: int, g: int, b: int}}  $item
     */
    private function drawHighlight(Fpdi $pdf, array $item): void
    {
        [$r, $g, $b] = [$item['color']['r'], $item['color']['g'], $item['color']['b']];
        $pdf->SetFillColor($r, $g, $b);
        $height = max(2.0, (float) ($item['height'] ?? 8));
        $pdf->Rect($item['x'], $item['y'], max(2.0, $item['width']), $height, 'F');
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private function parseHexColor(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return ['r' => 17, 'g' => 24, 'b' => 39];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    private function toPdfString(string $text): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);

        return is_string($converted) ? $converted : $text;
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
