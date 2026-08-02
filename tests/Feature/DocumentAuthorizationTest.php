<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('forbids downloading another users document', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    Storage::disk('local')->put('uploads/secret.pdf', '%PDF-1.4');
    $document = Document::factory()->for($owner)->create(['file_path' => 'uploads/secret.pdf']);

    $this->actingAs($intruder)
        ->get(route('pdf.download', $document))
        ->assertForbidden();
});

it('forbids deleting another users document', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    Storage::disk('local')->put('uploads/secret.pdf', '%PDF-1.4');
    $document = Document::factory()->for($owner)->create(['file_path' => 'uploads/secret.pdf']);

    $this->actingAs($intruder)
        ->delete(route('pdf.destroy', $document))
        ->assertForbidden();
});

it('forbids signing another users document', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    Storage::disk('local')->put('uploads/secret.pdf', '%PDF-1.4');
    $document = Document::factory()->for($owner)->create(['file_path' => 'uploads/secret.pdf']);

    $this->actingAs($intruder)
        ->get(route('pdf.sign.create', $document))
        ->assertForbidden();
});
