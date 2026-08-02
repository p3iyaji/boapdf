<?php

use App\Models\ConversionLog;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\PdfCompressionService;
use App\Services\PdfConversionService;
use App\Services\PdfMergeService;
use App\Services\PdfSignatureService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('merges two documents and saves a merged Document row', function () {
    Storage::disk('local')->put('uploads/a.pdf', '%PDF-1.4 A');
    Storage::disk('local')->put('uploads/b.pdf', '%PDF-1.4 B');
    Storage::disk('local')->put('merged/result.pdf', '%PDF-1.4 merged');

    $a = Document::factory()->uploaded()->for($this->user)->create(['file_path' => 'uploads/a.pdf']);
    $b = Document::factory()->uploaded()->for($this->user)->create(['file_path' => 'uploads/b.pdf']);

    $merged = Storage::disk('local')->path('merged/result.pdf');
    $this->mock(PdfMergeService::class)
        ->shouldReceive('merge')
        ->once()
        ->andReturn($merged);
    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(3);

    $this->actingAs($this->user)
        ->post(route('pdf.merge.store'), [
            'documents' => [$a->id, $b->id],
            'output_name' => 'combined',
        ])
        ->assertRedirect();

    $row = Document::where('user_id', $this->user->id)
        ->where('operation_type', Document::OP_MERGED)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->original_name)->toBe('combined.pdf');
    expect($row->pages)->toBe(3);
});

it('merges documents in the order given in the request', function () {
    Storage::disk('local')->put('uploads/a.pdf', '%PDF-1.4 A');
    Storage::disk('local')->put('uploads/b.pdf', '%PDF-1.4 B');
    Storage::disk('local')->put('merged/result.pdf', '%PDF-1.4 merged');

    $a = Document::factory()->uploaded()->for($this->user)->create(['file_path' => 'uploads/a.pdf']);
    $b = Document::factory()->uploaded()->for($this->user)->create(['file_path' => 'uploads/b.pdf']);

    $merged = Storage::disk('local')->path('merged/result.pdf');
    $receivedPaths = [];

    $this->mock(PdfMergeService::class)
        ->shouldReceive('merge')
        ->once()
        ->andReturnUsing(function (array $paths) use (&$receivedPaths, $merged) {
            $receivedPaths = $paths;

            return $merged;
        });
    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(2);

    $this->actingAs($this->user)
        ->post(route('pdf.merge.store'), [
            'documents' => [$b->id, $a->id],
        ])
        ->assertRedirect();

    expect($receivedPaths)->toHaveCount(2);
    expect($receivedPaths[0])->toBe($b->absolutePath());
    expect($receivedPaths[1])->toBe($a->absolutePath());
});

it('rejects a merge that includes another user\'s document', function () {
    $mine = Document::factory()->uploaded()->for($this->user)->create();
    $other = User::factory()->create();
    $theirs = Document::factory()->uploaded()->for($other)->create();

    $this->actingAs($this->user)
        ->post(route('pdf.merge.store'), [
            'documents' => [$mine->id, $theirs->id],
        ])
        ->assertSessionHasErrors('documents.1');
});

it('compresses a document and records a successful ConversionLog', function () {
    Storage::disk('local')->put('uploads/big.pdf', str_repeat('x', 200));
    Storage::disk('local')->put('compressed/small.pdf', str_repeat('x', 80));

    $source = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/big.pdf',
        'file_size' => 200,
    ]);

    $this->mock(PdfCompressionService::class)
        ->shouldReceive('compress')
        ->once()
        ->andReturn([
            'path' => Storage::disk('local')->path('compressed/small.pdf'),
            'original_size' => 200,
            'new_size' => 80,
            'method' => 'ghostscript',
        ]);
    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->post(route('pdf.compress.store'), [
            'document_id' => $source->id,
            'level' => 'recommended',
        ])
        ->assertRedirect();

    expect(
        Document::where('user_id', $this->user->id)
            ->where('operation_type', Document::OP_COMPRESSED)
            ->exists()
    )->toBeTrue();

    expect(ConversionLog::where('status', 'success')->exists())->toBeTrue();
});

it('returns compression errors when the pdf cannot be compressed', function () {
    Storage::disk('local')->put('uploads/invalid.pdf', 'not a valid pdf');

    $source = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/invalid.pdf',
        'file_size' => 20,
    ]);

    $this->actingAs($this->user)
        ->post(route('pdf.compress.store'), [
            'document_id' => $source->id,
            'level' => 'recommended',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('compress');

    expect(ConversionLog::where('status', 'failed')->exists())->toBeTrue();
});

it('signs a document and stores a SignatureRequest', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('temp/signature.png', 'fake-png');
    Storage::disk('local')->put('signed/result.pdf', '%PDF-1.4 signed');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldReceive('createSignatureFromDataUrl')
        ->andReturn(Storage::disk('local')->path('temp/signature.png'));
    $signer->shouldReceive('addSignature')
        ->andReturn(Storage::disk('local')->path('signed/result.pdf'));

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->post(route('pdf.sign.store', $doc), [
            'signature' => 'data:image/png;base64,iVBORw0KGgo=',
            'page' => 1,
            'x' => 20,
            'y' => 250,
            'width' => 60,
        ])
        ->assertRedirect();

    expect(SignatureRequest::where('status', SignatureRequest::STATUS_SIGNED)->exists())->toBeTrue();
    expect(
        Document::where('user_id', $this->user->id)
            ->where('operation_type', Document::OP_SIGNED)
            ->exists()
    )->toBeTrue();

    $signed = Document::where('operation_type', Document::OP_SIGNED)->first();
    expect($signed->metadata['signature']['page'] ?? null)->toBe(1);
    $req = SignatureRequest::where('status', SignatureRequest::STATUS_SIGNED)->first();
    expect($req->signature_position['signature']['page'] ?? null)->toBe(1);
});

it('signs a document with an optional logo', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('signed/a.pdf', '%PDF-1.4 a');
    Storage::disk('local')->put('signed/b.pdf', '%PDF-1.4 b');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $pngDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $sigImg = Storage::disk('local')->path('temp/signature.png');
    Storage::disk('local')->put('temp/signature.png', 'x');
    $logoImg = Storage::disk('local')->path('temp/logo.png');
    Storage::disk('local')->put('temp/logo.png', 'x');

    $pathA = Storage::disk('local')->path('signed/a.pdf');
    $pathB = Storage::disk('local')->path('signed/b.pdf');

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldReceive('createSignatureFromDataUrl')->once()->andReturn($sigImg);
    $signer->shouldReceive('createImageFromDataUrl')->once()->andReturn($logoImg);
    $calls = 0;
    $signer->shouldReceive('addSignature')
        ->twice()
        ->andReturnUsing(function (...$args) use (&$calls, $pathA, $pathB) {
            $calls++;
            if ($calls === 1) {
                expect($args[3] ?? 600)->toBe(600);

                return $pathA;
            }
            expect($args[3] ?? null)->toBe(800);

            return $pathB;
        });

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->post(route('pdf.sign.store', $doc), [
            'signature' => $pngDataUrl,
            'page' => 1,
            'x' => 20,
            'y' => 250,
            'width' => 60,
            'logo' => $pngDataUrl,
            'logo_page' => 1,
            'logo_x' => 40,
            'logo_y' => 200,
            'logo_width' => 25,
        ])
        ->assertRedirect();

    $signed = Document::where('operation_type', Document::OP_SIGNED)->first();
    expect($signed->metadata['logo']['page'] ?? null)->toBe(1);
    expect((float) ($signed->metadata['logo']['width'] ?? 0))->toBe(25.0);

    $req = SignatureRequest::where('status', SignatureRequest::STATUS_SIGNED)->first();
    expect($req->signature_position)->toHaveKey('signature');
    expect((float) ($req->signature_position['logo']['x'] ?? 0))->toBe(40.0);
});

it('signs with typed text only', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('temp/typed.png', 'x');
    Storage::disk('local')->put('signed/out.pdf', '%PDF-1.4 out');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $pngDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $typedImg = Storage::disk('local')->path('temp/typed.png');
    $outPath = Storage::disk('local')->path('signed/out.pdf');

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldNotReceive('createImageFromDataUrl');
    $signer->shouldReceive('createSignatureFromDataUrl')->once()->andReturn($typedImg);
    $signer->shouldReceive('addSignature')
        ->once()
        ->andReturnUsing(function (string $pdfPath, string $imagePath, array $position, int $maxRaster = 600) use ($outPath, $doc): string {
            expect($maxRaster)->toBe(600);
            expect($position['page'])->toBe(1);
            expect($pdfPath)->toBe($doc->absolutePath());

            return $outPath;
        });

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->post(route('pdf.sign.store', $doc), [
            'typed_signature' => $pngDataUrl,
            'typed_page' => 1,
            'typed_x' => 12,
            'typed_y' => 180,
            'typed_width' => 55,
        ])
        ->assertRedirect();

    $signed = Document::where('operation_type', Document::OP_SIGNED)->first();
    expect($signed->metadata['signature'] ?? null)->toBeNull();
    expect($signed->metadata['typed_text']['page'] ?? null)->toBe(1);

    $req = SignatureRequest::where('status', SignatureRequest::STATUS_SIGNED)->first();
    expect($req->signature_position)->toHaveKey('typed_text');
    expect($req->signature_position)->not->toHaveKey('signature');
});

it('requires logo placement fields when a logo is submitted', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $pngDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $this->actingAs($this->user)
        ->from(route('pdf.sign.create', $doc))
        ->post(route('pdf.sign.store', $doc), [
            'signature' => $pngDataUrl,
            'page' => 1,
            'x' => 20,
            'y' => 250,
            'width' => 60,
            'logo' => $pngDataUrl,
        ])
        ->assertRedirect(route('pdf.sign.create', $doc))
        ->assertSessionHasErrors(['logo_page', 'logo_x', 'logo_y', 'logo_width']);
});

it('signs with logo only when no signature is sent', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');
    Storage::disk('local')->put('signed/out.pdf', '%PDF-1.4 out');
    Storage::disk('local')->put('temp/logo.png', 'x');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $pngDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    $logoImg = Storage::disk('local')->path('temp/logo.png');
    $outPath = Storage::disk('local')->path('signed/out.pdf');

    $signer = $this->mock(PdfSignatureService::class);
    $signer->shouldNotReceive('createSignatureFromDataUrl');
    $signer->shouldReceive('createImageFromDataUrl')->once()->andReturn($logoImg);
    $signer->shouldReceive('addSignature')
        ->once()
        ->andReturnUsing(function (string $pdfPath, string $imagePath, array $position, int $maxRaster = 600) use ($outPath, $doc): string {
            expect($maxRaster)->toBe(800);
            expect($position['page'])->toBe(1);
            expect($pdfPath)->toBe($doc->absolutePath());

            return $outPath;
        });

    $this->mock(PdfConversionService::class)
        ->shouldReceive('countPages')
        ->andReturn(1);

    $this->actingAs($this->user)
        ->post(route('pdf.sign.store', $doc), [
            'logo' => $pngDataUrl,
            'logo_page' => 1,
            'logo_x' => 10,
            'logo_y' => 15,
            'logo_width' => 30,
        ])
        ->assertRedirect();

    $signed = Document::where('operation_type', Document::OP_SIGNED)->first();
    expect($signed->metadata['signature'] ?? null)->toBeNull();
    expect($signed->metadata['logo']['page'] ?? null)->toBe(1);

    $req = SignatureRequest::where('status', SignatureRequest::STATUS_SIGNED)->first();
    expect($req->signature_position)->toHaveKey('logo');
    expect($req->signature_position)->not->toHaveKey('page');
});

it('rejects apply when no drawing, typed text, or logo is submitted', function () {
    Storage::disk('local')->put('uploads/contract.pdf', '%PDF-1.4');

    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/contract.pdf',
    ]);

    $this->actingAs($this->user)
        ->from(route('pdf.sign.create', $doc))
        ->post(route('pdf.sign.store', $doc), [])
        ->assertRedirect(route('pdf.sign.create', $doc))
        ->assertSessionHasErrors('sign');
});

it('converts a PDF and records a ConversionLog', function () {
    Storage::disk('local')->put('uploads/doc.pdf', '%PDF-1.4');
    Storage::disk('local')->put('converted/out.txt', 'hello world');

    $source = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/doc.pdf',
    ]);

    $this->mock(PdfConversionService::class)
        ->shouldReceive('convertFromPdf')
        ->once()
        ->andReturn([
            'path' => Storage::disk('local')->path('converted/out.txt'),
            'pages' => 1,
            'extension' => 'txt',
            'mime_type' => 'text/plain; charset=UTF-8',
            'files' => [Storage::disk('local')->path('converted/out.txt')],
        ]);

    $this->actingAs($this->user)
        ->post(route('pdf.convert.store'), [
            'document_id' => $source->id,
            'target' => 'txt',
        ])
        ->assertOk(); // returns a download response

    expect(ConversionLog::where('target_format', 'txt')->where('status', 'success')->exists())->toBeTrue();
});

it('runs the pdf:cleanup command without errors', function () {
    Storage::disk('local')->put('merged/old.pdf', 'old');
    touch(Storage::disk('local')->path('merged/old.pdf'), now()->subDays(2)->getTimestamp());
    Storage::disk('local')->put('merged/new.pdf', 'new');
    Storage::disk('local')->put('converted/orphan.docx', 'editable document');
    touch(Storage::disk('local')->path('converted/orphan.docx'), now()->subDays(2)->getTimestamp());

    $this->artisan('pdf:cleanup')->assertExitCode(0);

    Storage::disk('local')->assertMissing('merged/old.pdf');
    Storage::disk('local')->assertExists('merged/new.pdf');
    Storage::disk('local')->assertMissing('converted/orphan.docx');
});
