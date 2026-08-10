<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters documents with search status and operation scopes', function () {
    $user = User::factory()->create();

    $match = Document::factory()->uploaded()->for($user)->create([
        'original_name' => 'quarterly-report.pdf',
        'status' => Document::STATUS_COMPLETED,
    ]);
    Document::factory()->merged()->processing()->for($user)->create([
        'original_name' => 'other.pdf',
    ]);

    $results = Document::query()
        ->forUser($user->id)
        ->search('quarterly')
        ->status(Document::STATUS_COMPLETED)
        ->operation(Document::OP_UPLOAD)
        ->pluck('id');

    expect($results->all())->toBe([$match->id]);
});

it('limits completedPdfs to finished pdf mime types', function () {
    $user = User::factory()->create();

    $ready = Document::factory()->uploaded()->for($user)->create([
        'status' => Document::STATUS_COMPLETED,
        'mime_type' => 'application/pdf',
    ]);
    Document::factory()->uploaded()->processing()->for($user)->create([
        'mime_type' => 'application/pdf',
    ]);
    Document::factory()->uploaded()->for($user)->create([
        'status' => Document::STATUS_COMPLETED,
        'mime_type' => 'text/plain',
        'original_name' => 'notes.txt',
    ]);

    $ids = Document::query()->forUser($user->id)->completedPdfs()->pluck('id');

    expect($ids->all())->toBe([$ready->id]);
});
