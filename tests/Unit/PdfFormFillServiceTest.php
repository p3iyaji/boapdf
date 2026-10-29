<?php

use App\Services\PdfFormFillService;
use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function makeBlankPdfForFormTests(): string
{
    $disk = DocumentsDisk::disk();
    if (! $disk->exists('uploads')) {
        $disk->makeDirectory('uploads');
    }

    $relative = 'uploads/blank-form.pdf';
    $path = $disk->path($relative);
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->Output('F', $path);

    return $path;
}

it('fills text and checkbox fields onto a pdf', function () {
    $source = makeBlankPdfForFormTests();

    $out = app(PdfFormFillService::class)->fillFields($source, [
        [
            'name' => 'name',
            'type' => 'text',
            'page' => 1,
            'x' => 20,
            'y' => 40,
            'width' => 80,
            'height' => 10,
            'value' => 'Test User',
        ],
        [
            'name' => 'agree',
            'type' => 'checkbox',
            'page' => 1,
            'x' => 20,
            'y' => 60,
            'width' => 8,
            'height' => 8,
            'value' => true,
        ],
    ]);

    expect($out)->toBeReadableFile()
        ->and(file_get_contents($out))->toStartWith('%PDF-');

    DocumentsDisk::disk()->assertExists('edited/'.basename($out));
});

it('rejects form fills with only empty values', function () {
    $source = makeBlankPdfForFormTests();

    app(PdfFormFillService::class)->fillFields($source, [
        [
            'type' => 'text',
            'page' => 1,
            'x' => 10,
            'y' => 10,
            'width' => 40,
            'height' => 8,
            'value' => '',
        ],
    ]);
})->throws(RuntimeException::class, 'No non-empty field values to apply.');
