<?php

use App\Models\Document;
use App\Models\User;
use App\Services\PdfConversionService;
use App\Services\PdfCreateService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('shows the create pdf page', function () {
    $this->actingAs($this->user)
        ->get(route('pdf.create.create'))
        ->assertOk()
        ->assertSee('Create PDF');
});

it('creates a new pdf document from page elements', function () {
    Storage::disk('local')->put('created/new.pdf', '%PDF-1.4 created');

    $this->mock(PdfCreateService::class)
        ->shouldReceive('create')
        ->once()
        ->andReturn(Storage::disk('local')->path('created/new.pdf'));

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->postJson(route('pdf.create.store'), [
            'title' => 'Briefing',
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
                            'text' => 'Hello world',
                            'font_size' => 14,
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $created = Document::query()
        ->where('operation_type', Document::OP_CREATED)
        ->where('user_id', $this->user->id)
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->original_name)->toBe('Briefing.pdf')
        ->and($created->status)->toBe(Document::STATUS_COMPLETED)
        ->and($created->pages)->toBe(1);
});

it('rejects create payloads without content', function () {
    $this->actingAs($this->user)
        ->postJson(route('pdf.create.store'), [
            'pages' => [
                ['elements' => []],
            ],
        ])
        ->assertUnprocessable();
});
