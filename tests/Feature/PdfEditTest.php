<?php

use App\Models\Document;
use App\Models\User;
use App\Services\PdfConversionService;
use App\Services\PdfEditService;
use App\Services\PdfFormFillService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('shows the edit page for an owned pdf', function () {
    Storage::disk('local')->put('uploads/doc.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/doc.pdf',
    ]);

    $this->actingAs($this->user)
        ->get(route('pdf.edit.create', $doc))
        ->assertOk()
        ->assertSee('Annotate / write')
        ->assertSee('Fill form fields');
});

it('forbids editing another users document', function () {
    $owner = User::factory()->create();
    Storage::disk('local')->put('uploads/doc.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($owner)->create([
        'file_path' => 'uploads/doc.pdf',
    ]);

    $this->actingAs($this->user)
        ->get(route('pdf.edit.create', $doc))
        ->assertForbidden();
});

it('applies annotations and creates an edited document', function () {
    Storage::disk('local')->put('uploads/doc.pdf', '%PDF-1.4');
    Storage::disk('local')->put('edited/out.pdf', '%PDF-1.4 edited');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/doc.pdf',
    ]);

    $this->mock(PdfEditService::class)
        ->shouldReceive('applyAnnotations')
        ->once()
        ->andReturn(Storage::disk('local')->path('edited/out.pdf'));

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->postJson(route('pdf.edit.store', $doc), [
            'annotations' => [
                [
                    'type' => 'text',
                    'page' => 1,
                    'x' => 20,
                    'y' => 40,
                    'width' => 60,
                    'text' => 'Hello',
                    'font_size' => 12,
                ],
            ],
        ])
        ->assertRedirect();

    $edited = Document::query()
        ->where('operation_type', Document::OP_EDITED)
        ->where('user_id', $this->user->id)
        ->first();

    expect($edited)->not->toBeNull()
        ->and($edited->status)->toBe(Document::STATUS_COMPLETED)
        ->and($edited->parent_document_id)->toBe($doc->id)
        ->and((int) ($edited->metadata['annotation_count'] ?? 0))->toBe(1);
});

it('fills form fields onto a new document', function () {
    Storage::disk('local')->put('uploads/form.pdf', '%PDF-1.4');
    Storage::disk('local')->put('edited/filled.pdf', '%PDF-1.4 filled');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/form.pdf',
    ]);

    $this->mock(PdfFormFillService::class)
        ->shouldReceive('fillFields')
        ->once()
        ->andReturn(Storage::disk('local')->path('edited/filled.pdf'));

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->postJson(route('pdf.edit.form', $doc), [
            'fields' => [
                [
                    'name' => 'full_name',
                    'type' => 'text',
                    'page' => 1,
                    'x' => 20,
                    'y' => 50,
                    'width' => 80,
                    'height' => 10,
                    'value' => 'Ada Lovelace',
                ],
            ],
        ])
        ->assertRedirect();

    $filled = Document::query()
        ->where('operation_type', Document::OP_FORM_FILLED)
        ->where('user_id', $this->user->id)
        ->first();

    expect($filled)->not->toBeNull()
        ->and($filled->status)->toBe(Document::STATUS_COMPLETED)
        ->and($filled->metadata['fields'][0]['value'] ?? null)->toBe('Ada Lovelace');
});

it('rejects empty annotation payloads', function () {
    Storage::disk('local')->put('uploads/doc.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/doc.pdf',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('pdf.edit.store', $doc), [
            'annotations' => [],
        ])
        ->assertUnprocessable();
});
