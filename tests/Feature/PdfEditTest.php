<?php

use App\Models\Document;
use App\Models\User;
use App\Services\PdfEditException;
use App\Services\PdfEditService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('shows the editor for a ready pdf', function () {
    Storage::disk('local')->put('uploads/brief.pdf', '%PDF-1.4 test');
    $document = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/brief.pdf',
        'original_name' => 'brief.pdf',
    ]);

    $this->actingAs($this->user)
        ->get(route('pdf.edit.create', $document))
        ->assertOk()
        ->assertSee('Edit PDF', false)
        ->assertSee('function pdfEditor(', false)
        ->assertSee('Replace', false)
        ->assertSee('Redact', false);

    $this->actingAs($this->user)
        ->get(route('pdf.show', $document))
        ->assertOk()
        ->assertSee(route('pdf.edit.create', $document), false);
});

it('saves content edits as a new document and leaves the original in place', function () {
    Storage::disk('local')->put('uploads/brief.pdf', '%PDF-1.4 test');
    Storage::disk('local')->put('edited/result.pdf', '%PDF-1.4 edited');
    $document = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/brief.pdf',
        'original_name' => 'brief.pdf',
        'pages' => 2,
    ]);

    $this->mock(PdfEditService::class)
        ->shouldReceive('applyContent')
        ->once()
        ->andReturn([
            'path' => Storage::disk('local')->path('edited/result.pdf'),
            'pages' => 2,
        ]);

    $this->actingAs($this->user)
        ->postJson(route('pdf.edit.store', $document), [
            'texts' => [[
                'page' => 1,
                'rect' => [72, 700, 260, 730],
                'text' => 'A note',
                'size' => 12,
                'color' => '#111827',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    $edited = Document::query()->where('operation_type', Document::OP_EDITED)->first();

    expect($edited)->not->toBeNull()
        ->and($edited->parent_document_id)->toBe($document->id)
        ->and($edited->status)->toBe(Document::STATUS_COMPLETED)
        ->and($edited->pages)->toBe(2)
        ->and($edited->original_name)->toBe('brief-edited.pdf');

    expect(Storage::disk('local')->exists('uploads/brief.pdf'))->toBeTrue();
});

it('returns the replacement overflow message and keeps the edit failed', function () {
    Storage::disk('local')->put('uploads/brief.pdf', '%PDF-1.4 test');
    $document = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/brief.pdf',
    ]);

    $this->mock(PdfEditService::class)
        ->shouldReceive('applyContent')
        ->once()
        ->andThrow(new PdfEditException('The replacement is longer than the selected line. Shorten it and try again.'));

    $this->actingAs($this->user)
        ->postJson(route('pdf.edit.store', $document), [
            'replacements' => [[
                'page' => 1,
                'rects' => [[72, 80, 200, 100]],
                'text' => 'This replacement is far too long for the selected line',
                'size' => 16,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The replacement is longer than the selected line. Shorten it and try again.');

    expect(Document::query()->where('operation_type', Document::OP_EDITED)->value('status'))
        ->toBe(Document::STATUS_FAILED);
});

it('saves a separate page plan', function () {
    Storage::disk('local')->put('uploads/brief.pdf', '%PDF-1.4 test');
    Storage::disk('local')->put('edited/pages.pdf', '%PDF-1.4 pages');
    $document = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/brief.pdf',
        'pages' => 2,
    ]);

    $this->mock(PdfEditService::class)
        ->shouldReceive('applyPages')
        ->once()
        ->andReturn([
            'path' => Storage::disk('local')->path('edited/pages.pdf'),
            'pages' => 3,
        ]);

    $this->actingAs($this->user)
        ->postJson(route('pdf.edit.pages', $document), [
            'pages' => [
                ['source' => 2, 'rotate' => 90],
                ['blank' => true, 'rotate' => 0],
                ['source' => 1, 'rotate' => 0],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    expect(Document::query()->where('operation_type', Document::OP_EDITED)->value('pages'))->toBe(3);
});

it('rejects an edit with nothing to apply', function () {
    Storage::disk('local')->put('uploads/brief.pdf', '%PDF-1.4 test');
    $document = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/brief.pdf',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('pdf.edit.store', $document), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('edit');
});

it('blocks another user and a guest from the editor', function () {
    Storage::disk('local')->put('uploads/brief.pdf', '%PDF-1.4 test');
    $document = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/brief.pdf',
    ]);
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('pdf.edit.create', $document))
        ->assertForbidden();

    $this->postJson(route('pdf.edit.store', $document), [
        'texts' => [[
            'page' => 1,
            'rect' => [10, 10, 80, 30],
            'text' => 'Nope',
        ]],
    ])->assertForbidden();
});
