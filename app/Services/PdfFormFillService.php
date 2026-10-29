<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PdfFormFillService
{
    /**
     * Visually fill form fields by stamping values at widget rectangles (mm).
     * Preserves original page content; does not rewrite AcroForm dictionaries.
     *
     * @param  list<array{
     *     name?: string,
     *     type: string,
     *     page: int,
     *     x: float,
     *     y: float,
     *     width: float,
     *     height: float,
     *     value: string|bool|int|float|null
     * }>  $fields
     */
    public function fillFields(string $pdfPath, array $fields): string
    {
        if (! file_exists($pdfPath)) {
            throw new RuntimeException("PDF not found: {$pdfPath}");
        }

        if ($fields === []) {
            throw new RuntimeException('At least one form field value is required.');
        }

        $prepared = [];
        foreach ($fields as $index => $field) {
            $page = (int) ($field['page'] ?? 0);
            if ($page < 1) {
                throw new RuntimeException("Field #{$index} has an invalid page.");
            }

            $type = strtolower((string) ($field['type'] ?? 'text'));
            $value = $field['value'] ?? null;

            if ($type === 'checkbox' || $type === 'radio') {
                $checked = filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Yes' || $value === 'On' || $value === 1 || $value === '1';
                if (! $checked) {
                    continue;
                }
            } elseif ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $prepared[] = [
                'type' => $type,
                'page' => $page,
                'x' => (float) ($field['x'] ?? 0),
                'y' => (float) ($field['y'] ?? 0),
                'width' => max(2.0, (float) ($field['width'] ?? 40)),
                'height' => max(2.0, (float) ($field['height'] ?? 8)),
                'value' => is_bool($value) ? ($value ? 'Yes' : '') : (string) $value,
                'name' => (string) ($field['name'] ?? ''),
            ];
        }

        if ($prepared === []) {
            throw new RuntimeException('No non-empty field values to apply.');
        }

        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($pdfPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $template = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            foreach ($prepared as $field) {
                if ($field['page'] !== $pageNo) {
                    continue;
                }

                if (in_array($field['type'], ['checkbox', 'radio'], true)) {
                    $this->drawCheckmark($pdf, $field);
                } else {
                    $this->drawFieldText($pdf, $field);
                }
            }
        }

        $outputPath = $this->ensureDirectory('edited').'/'.Str::uuid().'.pdf';
        $pdf->Output('F', $outputPath);

        return $outputPath;
    }

    /**
     * @param  array{x: float, y: float, width: float, height: float, value: string}  $field
     */
    private function drawFieldText(Fpdi $pdf, array $field): void
    {
        $fontSize = max(7.0, min(18.0, $field['height'] * 0.75));
        $pdf->SetTextColor(17, 24, 39);
        $pdf->SetFont('Helvetica', '', $fontSize);
        $pdf->SetXY($field['x'] + 0.5, $field['y'] + max(0.2, ($field['height'] - $fontSize * 0.35) / 2));
        $pdf->Cell($field['width'] - 1.0, $fontSize * 0.4, $this->toPdfString($field['value']), 0, 0, 'L');
    }

    /**
     * @param  array{x: float, y: float, width: float, height: float}  $field
     */
    private function drawCheckmark(Fpdi $pdf, array $field): void
    {
        $pdf->SetDrawColor(17, 24, 39);
        $pdf->SetLineWidth(0.6);
        $x = $field['x'];
        $y = $field['y'];
        $w = $field['width'];
        $h = $field['height'];
        $pdf->Line($x + $w * 0.18, $y + $h * 0.55, $x + $w * 0.42, $y + $h * 0.82);
        $pdf->Line($x + $w * 0.42, $y + $h * 0.82, $x + $w * 0.82, $y + $h * 0.22);
    }

    private function toPdfString(string $text): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);

        return is_string($converted) ? $converted : $text;
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
