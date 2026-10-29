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

it('shows grouped formats and preselects a document from the query string', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $source = Document::factory()->for($user)->create([
        'original_name' => 'brief.pdf',
        'status' => Document::STATUS_COMPLETED,
        'mime_type' => 'application/pdf',
    ]);

    actingAs($user)
        ->get(route('pdf.convert.create', ['document' => $source->id]))
        ->assertOk()
        ->assertSee('Editable documents', false)
        ->assertSee('Page images', false)
        ->assertSee('Plain text', false)
        ->assertSee('Start conversion', false)
        ->assertSee('brief.pdf', false)
        ->assertSee('selectedId: '.$source->id, false);
});

it('stores conversion metadata and redirects to the progress page', function () {
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

    $converted = Document::query()
        ->where('parent_document_id', $source->id)
        ->where('operation_type', Document::OP_CONVERTED)
        ->sole();

    $response->assertRedirect(route('pdf.convert.progress', $converted));

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

it('shows conversion progress and status for the owner', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'original_name' => 'notes.docx',
        'file_path' => 'converted/notes.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => Document::STATUS_COMPLETED,
        'operation_type' => Document::OP_CONVERTED,
        'file_size' => 1200,
        'metadata' => ['target' => 'docx'],
    ]);
    Storage::disk('local')->put($document->file_path, 'docx-bytes');

    actingAs($user)
        ->get(route('pdf.convert.progress', $document))
        ->assertOk()
        ->assertSee('Download file', false)
        ->assertSee('function convertProgress(', false);

    actingAs($user)
        ->getJson(route('pdf.convert.status', $document))
        ->assertOk()
        ->assertJson([
            'status' => Document::STATUS_COMPLETED,
            'ready' => true,
            'failed' => false,
            'name' => 'notes.docx',
        ]);
});

it('does not preview converted non-PDF files in the PDF viewer', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'original_name' => 'brief.docx',
        'file_path' => 'converted/brief.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => Document::STATUS_COMPLETED,
        'operation_type' => Document::OP_CONVERTED,
        'file_size' => 2048,
        'metadata' => ['target' => 'docx'],
    ]);
    Storage::disk('local')->put($document->file_path, 'docx-bytes');

    actingAs($user)
        ->get(route('pdf.show', $document))
        ->assertOk()
        ->assertSee('Conversion ready')
        ->assertSee('Download file')
        ->assertDontSee('function pdfViewer(', false)
        ->assertDontSee('Invalid PDF structure');
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
        ->where('error_message', 'The PDF password is incorrect.')
        ->exists())->toBeTrue();
});
