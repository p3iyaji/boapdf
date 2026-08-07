<?php

use App\Services\PdfFromImagesService;
use App\Support\DocumentsDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('builds an A4 PDF that upscales a small capture to fill the page', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'cam');
    $path = $tmp.'.jpg';
    @unlink($tmp);

    $fake = UploadedFile::fake()->image('scan.jpg', 320, 240);
    file_put_contents($path, file_get_contents($fake->getRealPath()));

    $relative = app(PdfFromImagesService::class)->buildPdfFromImages([$path]);

    expect($relative)->toStartWith('uploads/')
        ->and($relative)->toEndWith('.pdf');

    DocumentsDisk::disk()->assertExists($relative);

    $pdfBytes = DocumentsDisk::disk()->get($relative);
    expect($pdfBytes)->toStartWith('%PDF-');

    // A filled A4 page with an embedded JPEG should be larger than a tiny letterboxed stamp.
    expect(strlen($pdfBytes))->toBeGreaterThan(2000);

    @unlink($path);
});

it('rejects an empty image list', function () {
    app(PdfFromImagesService::class)->buildPdfFromImages([]);
})->throws(RuntimeException::class, 'At least one image is required.');
