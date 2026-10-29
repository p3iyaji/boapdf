<?php

use App\Services\PdfCreateService;
use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('creates a multi-page pdf from text elements', function () {
    $path = app(PdfCreateService::class)->create([
        'page_size' => 'A4',
        'orientation' => 'P',
        'pages' => [
            [
                'elements' => [
                    [
                        'type' => 'text',
                        'x' => 20,
                        'y' => 30,
                        'width' => 160,
                        'text' => 'Page one',
                        'font_size' => 16,
                    ],
                ],
            ],
            [
                'elements' => [
                    [
                        'type' => 'text',
                        'x' => 20,
                        'y' => 30,
                        'width' => 160,
                        'text' => 'Page two',
                        'font_size' => 16,
                    ],
                ],
            ],
        ],
    ]);

    expect($path)->toBeReadableFile()
        ->and(file_get_contents($path))->toStartWith('%PDF-')
        ->and(strlen(file_get_contents($path)))->toBeGreaterThan(500);

    DocumentsDisk::disk()->assertExists('created/'.basename($path));
});

it('rejects create definitions without pages', function () {
    app(PdfCreateService::class)->create(['pages' => []]);
})->throws(RuntimeException::class, 'At least one page is required.');
