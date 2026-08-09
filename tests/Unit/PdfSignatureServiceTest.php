<?php

use App\Services\PdfSignatureService;
use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

beforeEach(function () {
    Storage::fake('local');
});

function makeBlankPdfForSignatureTests(): string
{
    $disk = DocumentsDisk::disk();
    if (! $disk->exists('uploads')) {
        $disk->makeDirectory('uploads');
    }

    $relative = 'uploads/blank-sign.pdf';
    $path = $disk->path($relative);
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Text(20, 20, 'Source');
    $pdf->Output('F', $path);

    return $path;
}

function makePngForSignatureTests(string $name = 'mark.png'): string
{
    $disk = DocumentsDisk::disk();
    if (! $disk->exists('temp')) {
        $disk->makeDirectory('temp');
    }

    $path = $disk->path('temp/'.$name);
    $image = imagecreatetruecolor(40, 20);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, 40, 20, $white);
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

it('stamps multiple overlays in a single pass', function () {
    $source = makeBlankPdfForSignatureTests();
    $markA = makePngForSignatureTests('a.png');
    $markB = makePngForSignatureTests('b.png');

    $out = app(PdfSignatureService::class)->addSignatures($source, [
        [
            'image_path' => $markA,
            'page' => 1,
            'x' => 20,
            'y' => 40,
            'width' => 30,
        ],
        [
            'image_path' => $markB,
            'page' => 1,
            'x' => 60,
            'y' => 80,
            'width' => 25,
        ],
    ]);

    expect($out)->toBeReadableFile()
        ->and(file_get_contents($out))->toStartWith('%PDF-');

    DocumentsDisk::disk()->assertExists('signed/'.basename($out));
});

it('opens readable pdfs without creating a rewrite temp file', function () {
    $source = makeBlankPdfForSignatureTests();

    [$pdf, $pages, $temp] = app(PdfSignatureService::class)->fpdiFromPath($source);

    expect($pages)->toBe(1)
        ->and($pdf)->toBeInstanceOf(Fpdi::class)
        ->and($temp)->toBeNull();
});
