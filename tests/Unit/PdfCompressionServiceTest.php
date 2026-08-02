<?php

use App\Services\PdfCompressionService;
use Illuminate\Support\Facades\Storage;

it('compresses a valid pdf and returns sizes and a readable output file', function () {
    Storage::fake('local');

    Storage::disk('local')->makeDirectory('uploads');
    $inputAbsolute = Storage::disk('local')->path('uploads/source.pdf');

    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Compress me');
    $pdf->Output('F', $inputAbsolute);

    config(['pdf.ghostscript_path' => '/nonexistent/ghostscript-for-tests']);

    $service = app(PdfCompressionService::class);
    $result = $service->compress($inputAbsolute, 'recommended');

    expect($result)->toHaveKeys(['path', 'original_size', 'new_size', 'method']);
    expect($result['original_size'])->toBeGreaterThan(0);
    expect($result['new_size'])->toBeGreaterThan(0);
    expect(file_exists($result['path']))->toBeTrue();
    expect(str_starts_with($result['path'], Storage::disk('local')->path('compressed')))->toBeTrue();
    expect(in_array($result['method'], ['ghostscript', 'fpdi'], true))->toBeTrue();
});

it('throws when the source file is missing', function () {
    Storage::fake('local');

    $service = app(PdfCompressionService::class);
    $missing = Storage::disk('local')->path('uploads/nope.pdf');

    expect(fn () => $service->compress($missing))
        ->toThrow(RuntimeException::class, 'Source file not found');
});
