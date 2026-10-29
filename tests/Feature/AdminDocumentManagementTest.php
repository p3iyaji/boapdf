<?php

use App\Models\Document;
use App\Models\User;
use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Storage;

it('lists documents for admins across users', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    Document::factory()->for($owner)->create(['original_name' => 'owned-by-other.pdf']);

    $this->actingAs($admin)
        ->get(route('admin.documents.index'))
        ->assertOk()
        ->assertSee('owned-by-other.pdf', false);
});

it('lets an admin delete any document and its file', function () {
    Storage::fake('local');
    DocumentsDisk::disk()->put('uploads/admin-delete.pdf', '%PDF-1.4');

    $admin = User::factory()->admin()->create();
    $document = Document::factory()->create([
        'file_path' => 'uploads/admin-delete.pdf',
        'original_name' => 'admin-delete.pdf',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.documents.destroy', $document))
        ->assertRedirect(route('admin.documents.index'));

    expect(Document::find($document->id))->toBeNull();
    DocumentsDisk::disk()->assertMissing('uploads/admin-delete.pdf');
});

it('forbids regular users from the admin documents index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.documents.index'))
        ->assertForbidden();
});
