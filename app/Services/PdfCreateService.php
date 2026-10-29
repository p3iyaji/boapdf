<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use FPDF;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;

class PdfCreateService
{
    public function __construct(private PdfSignatureService $images) {}

    /**
     * Create a new PDF from page element definitions (vector text + stamped images).
     *
     * @param  array{
     *     page_size?: string,
     *     orientation?: string,
     *     pages: list<array{elements?: list<array{
     *         type: string,
     *         x: float,
     *         y: float,
     *         width?: float,
     *         height?: float,
     *         text?: string,
     *         font_size?: float,
     *         color?: string,
     *         image?: string
     *     }>}>
     * }  $definition
     * @return string Absolute path to the created PDF
     */
    public function create(array $definition): string
    {
        $pages = $definition['pages'] ?? [];
        if ($pages === []) {
            throw new RuntimeException('At least one page is required.');
        }

        $pageSize = strtoupper((string) ($definition['page_size'] ?? 'A4'));
        if (! in_array($pageSize, ['A4', 'LETTER'], true)) {
            $pageSize = 'A4';
        }

        $orientation = strtoupper((string) ($definition['orientation'] ?? 'P')) === 'L' ? 'L' : 'P';
        $format = $pageSize === 'LETTER' ? 'Letter' : 'A4';

        $pdf = new FPDF($orientation, 'mm', $format);
        $tempFiles = [];

        try {
            foreach ($pages as $pageIndex => $page) {
                $pdf->AddPage();
                $elements = $page['elements'] ?? [];
                if (! is_array($elements)) {
                    continue;
                }

                foreach ($elements as $elementIndex => $element) {
                    if (! is_array($element)) {
                        continue;
                    }

                    $type = strtolower((string) ($element['type'] ?? ''));
                    $x = (float) ($element['x'] ?? 20);
                    $y = (float) ($element['y'] ?? 20);
                    $width = (float) ($element['width'] ?? 80);

                    if ($type === 'text') {
                        $text = trim((string) ($element['text'] ?? ''));
                        if ($text === '') {
                            continue;
                        }
                        $fontSize = (float) ($element['font_size'] ?? 12);
                        $color = $this->parseHexColor((string) ($element['color'] ?? '#111827'));
                        $pdf->SetTextColor($color['r'], $color['g'], $color['b']);
                        $pdf->SetFont('Helvetica', '', max(6, min(72, $fontSize)));
                        $pdf->SetXY($x, $y);
                        $pdf->MultiCell(max(10.0, $width), max(4.0, $fontSize * 0.45), $this->toPdfString($text), 0, 'L');
                    } elseif ($type === 'image') {
                        $dataUrl = (string) ($element['image'] ?? '');
                        if ($dataUrl === '') {
                            throw new RuntimeException("Page {$pageIndex} element {$elementIndex} is missing an image.");
                        }
                        $raw = $this->images->createImageFromDataUrl($dataUrl);
                        $optimized = $this->prepareRasterImage($raw, 1200);
                        @unlink($raw);
                        $tempFiles[] = $optimized;
                        $pdf->Image($optimized, $x, $y, max(5.0, $width), 0, 'PNG');
                    } else {
                        throw new RuntimeException("Unsupported create element type: {$type}");
                    }
                }
            }

            $outputPath = $this->ensureDirectory('created').'/'.Str::uuid().'.pdf';
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
