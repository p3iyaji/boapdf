<?php

use App\Services\PdfEditService;
use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function makeBlankPdfForEditTests(): string
{
    $disk = DocumentsDisk::disk();
    if (! $disk->exists('uploads')) {
        $disk->makeDirectory('uploads');
    }

    $relative = 'uploads/blank-edit.pdf';
    $path = $disk->path($relative);
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Text(20, 20, 'Source');
    $pdf->Output('F', $path);

    return $path;
}

it('stamps vector text onto a pdf without losing the pdf header', function () {
    $source = makeBlankPdfForEditTests();

    $out = app(PdfEditService::class)->applyAnnotations($source, [
        [
            'type' => 'text',
            'page' => 1,
            'x' => 25,
            'y' => 40,
            'width' => 100,
            'text' => 'Annotated',
            'font_size' => 14,
            'color' => '#111827',
        ],
    ]);

    expect($out)->toBeReadableFile()
        ->and(file_get_contents($out))->toStartWith('%PDF-');

    DocumentsDisk::disk()->assertExists('edited/'.basename($out));
});

it('rejects an empty annotation list', function () {
    $source = makeBlankPdfForEditTests();

    app(PdfEditService::class)->applyAnnotations($source, []);
})->throws(RuntimeException::class, 'At least one annotation is required.');
