<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('lists only the current user\'s documents', function () {
    Document::factory()->count(2)->uploaded()->for($this->user)->create();
    $other = User::factory()->create();
    Document::factory()->uploaded()->for($other)->create(['original_name' => 'not-mine.pdf']);

    $this->actingAs($this->user)
        ->get(route('pdf.index'))
        ->assertOk()
        ->assertDontSee('not-mine.pdf');
});

it('includes the camera capture alpine helper in the library page', function () {
    $this->actingAs($this->user)
        ->get(route('pdf.index'))
        ->assertOk()
        ->assertSee('function cameraCapture()', false)
        ->assertSee('x-data="cameraCapture()"', false)
        ->assertSee('capture="environment"', false)
        ->assertSee('useNativeCamera()', false)
        ->assertSee('Choose photos', false)
        ->assertSee('openEditor(', false)
        ->assertSee('applyEditor()', false)
        ->assertSee('Fill page', false)
        ->assertSee('Edit size', false)
        ->assertSee('crop &amp; zoom for full-screen signing', false);
});

it('accepts a PDF upload and stores a document row', function () {
    $file = UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf');

    $this->actingAs($this->user)
        ->post(route('pdf.upload'), ['file' => $file])
        ->assertRedirect();

    $doc = Document::where('user_id', $this->user->id)->latest()->first();
    expect($doc)->not->toBeNull();
    expect($doc->original_name)->toBe('contract.pdf');
    expect($doc->operation_type)->toBe(Document::OP_UPLOAD);
    Storage::disk('local')->assertExists($doc->file_path);
});

it('rejects non-PDF uploads', function () {
    $file = UploadedFile::fake()->create('virus.exe', 20, 'application/octet-stream');

    $this->actingAs($this->user)
        ->post(route('pdf.upload'), ['file' => $file])
        ->assertSessionHasErrors('file');
});

it('streams the PDF for the authenticated owner', function () {
    Storage::disk('local')->put('uploads/owned.pdf', '%PDF-1.4 test');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/owned.pdf',
    ]);

    $this->actingAs($this->user)
        ->get(route('pdf.stream', $doc))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('returns not found when streaming a processing document with a pending path', function () {
    $doc = Document::factory()->for($this->user)->create([
        'file_path' => 'compressed/pending-missing.pdf',
        'status' => Document::STATUS_PROCESSING,
        'operation_type' => Document::OP_COMPRESSED,
        'mime_type' => 'application/pdf',
        'file_size' => 0,
    ]);

    $this->actingAs($this->user)
        ->get(route('pdf.stream', $doc))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->get(route('pdf.download', $doc))
        ->assertNotFound();
});

it('shows a processing state instead of the viewer when the file is not ready', function () {
    $doc = Document::factory()->for($this->user)->create([
        'file_path' => 'compressed/pending-missing.pdf',
        'status' => Document::STATUS_PROCESSING,
        'operation_type' => Document::OP_COMPRESSED,
        'mime_type' => 'application/pdf',
        'original_name' => 'report-compressed.pdf',
        'file_size' => 0,
    ]);

    $this->actingAs($this->user)
        ->get(route('pdf.show', $doc))
        ->assertOk()
        ->assertSee('Still processing')
        ->assertDontSee('function pdfViewer(', false);
});

it('forbids streaming someone else\'s document', function () {
    $other = User::factory()->create();
    Storage::disk('local')->put('uploads/other.pdf', '%PDF-1.4 test');
    $theirs = Document::factory()->uploaded()->for($other)->create([
        'file_path' => 'uploads/other.pdf',
    ]);

    $this->actingAs($this->user)
        ->get(route('pdf.stream', $theirs))
        ->assertForbidden();
});

it('forbids viewing someone else\'s document', function () {
    $other = User::factory()->create();
    $theirs = Document::factory()->uploaded()->for($other)->create();

    $this->actingAs($this->user)
        ->get(route('pdf.show', $theirs))
        ->assertForbidden();
});

it('deletes a document and removes the underlying file', function () {
    Storage::disk('local')->put('uploads/del.pdf', '%PDF-1.4 fake');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/del.pdf',
    ]);

    $this->actingAs($this->user)
        ->delete(route('pdf.destroy', $doc))
        ->assertRedirect(route('pdf.index'));

    expect(Document::find($doc->id))->toBeNull();
    Storage::disk('local')->assertMissing('uploads/del.pdf');
});

it('creates a PDF from camera captures and stores a capture document', function () {
    $i1 = UploadedFile::fake()->image('p1.jpg', 640, 480);
    $i2 = UploadedFile::fake()->image('p2.jpg', 640, 480);

    $this->actingAs($this->user)
        ->post(route('pdf.upload.camera'), [
            'title' => 'Desk notes',
            'images' => [$i1, $i2],
        ])
        ->assertRedirect();

    $doc = Document::where('user_id', $this->user->id)
        ->where('operation_type', Document::OP_CAPTURE)
        ->latest()
        ->first();

    expect($doc)->not->toBeNull();
    expect($doc->original_name)->toBe('Desk notes.pdf');
    expect($doc->pages)->toBe(2);
    Storage::disk('local')->assertExists($doc->file_path);
});

it('validates camera uploads require at least one image', function () {
    $this->actingAs($this->user)
        ->post(route('pdf.upload.camera'), ['title' => 'x'])
        ->assertSessionHasErrors('images');
});

it('redirects guests from camera upload', function () {
    $this->post(route('pdf.upload.camera'), [])
        ->assertRedirect(route('login'));
});
