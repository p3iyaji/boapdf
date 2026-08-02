<?php

use App\Models\ConversionLog;
use App\Models\Document;
use App\Models\User;
use App\Services\PdfConversionException;
use App\Services\PdfConversionService;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;
use function Pest\Laravel\post;

beforeEach(function () {
    Storage::fake('local');
});

it('stores conversion metadata and downloads the generated artifact', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $source = Document::factory()->for($user)->create([
        'original_name' => 'quarterly-report.pdf',
        'file_path' => 'uploads/quarterly-report.pdf',
    ]);
    Storage::disk('local')->put($source->file_path, '%PDF source');
    Storage::disk('local')->put('converted/result.zip', 'zip content');
    $outputPath = Storage::disk('local')->path('converted/result.zip');

    mock(PdfConversionService::class)
        ->shouldReceive('convertFromPdf')
        ->once()
        ->with($source->absolutePath(), 'png', 'secret')
        ->andReturn([
            'path' => $outputPath,
            'pages' => 3,
            'extension' => 'zip',
            'mime_type' => 'application/zip',
            'files' => ['page-0001.png', 'page-0002.png', 'page-0003.png'],
        ]);

    actingAs($user);
    $response = post(route('pdf.convert.store'), [
        'document_id' => $source->id,
        'target' => 'png',
        'password' => 'secret',
    ]);

    $response->assertSuccessful()
        ->assertDownload('quarterly-report-png-pages.zip');

    $converted = Document::query()
        ->where('parent_document_id', $source->id)
        ->where('operation_type', Document::OP_CONVERTED)
        ->sole();
    $metadata = json_decode($converted->getRawOriginal('metadata'), true, flags: JSON_THROW_ON_ERROR);

    expect($converted)
        ->original_name->toBe('quarterly-report-png-pages.zip')
        ->mime_type->toBe('application/zip')
        ->pages->toBe(3)
        ->and($metadata['target'])->toBe('png')
        ->and($metadata['packaged'])->toBeTrue()
        ->and($metadata['artifact_count'])->toBe(3)
        ->and(ConversionLog::query()->where('document_id', $source->id)->where('status', 'success')->exists())
        ->toBeTrue();
});

it('rejects unsupported conversion targets', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $source = Document::factory()->for($user)->create();

    actingAs($user);
    post(route('pdf.convert.store'), [
        'document_id' => $source->id,
        'target' => 'rtf',
    ])->assertSessionHasErrors('target');
});

it('does not allow converting another users document', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $source = Document::factory()->create();

    actingAs($user);
    post(route('pdf.convert.store'), [
        'document_id' => $source->id,
        'target' => 'docx',
    ])->assertSessionHasErrors('document_id');
});

it('does not allow converted non-PDF documents to be used as PDF sources', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $source = Document::factory()->for($user)->create([
        'original_name' => 'already-converted.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'operation_type' => Document::OP_CONVERTED,
    ]);

    actingAs($user);
    post(route('pdf.convert.store'), [
        'document_id' => $source->id,
        'target' => 'png',
    ])->assertSessionHasErrors('document_id');
});

it('reports conversion failures and records the failed attempt', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $source = Document::factory()->for($user)->create([
        'file_path' => 'uploads/encrypted.pdf',
    ]);
    Storage::disk('local')->put($source->file_path, '%PDF encrypted');

    mock(PdfConversionService::class)
        ->shouldReceive('convertFromPdf')
        ->once()
        ->andThrow(new PdfConversionException('The PDF password is incorrect.'));

    actingAs($user);
    post(route('pdf.convert.store'), [
        'document_id' => $source->id,
        'target' => 'docx',
        'password' => 'wrong',
    ])
        ->assertSessionHasErrors(['convert' => 'Could not convert this PDF']);

    expect(ConversionLog::query()
        ->where('document_id', $source->id)
        ->where('status', 'failed')
        ->where('error_message', 'Conversion failed.')
        ->exists())->toBeTrue();
});
