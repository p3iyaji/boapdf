<?php

use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('removes documents and files older than the retention period', function () {
    config(['pdf.upload_retention_days' => 30]);

    Storage::disk('local')->put('uploads/stale.pdf', '%PDF-1.4');
    $stale = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/stale.pdf',
    ]);
    $stale->forceFill([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ])->saveQuietly();

    Storage::disk('local')->put('uploads/fresh.pdf', '%PDF-1.4');
    $fresh = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/fresh.pdf',
    ]);
    $fresh->forceFill([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ])->saveQuietly();

    $this->artisan('pdf:prune-expired-documents')->assertSuccessful();

    expect(Document::query()->whereKey($stale->id)->exists())->toBeFalse();
    expect(Document::query()->whereKey($fresh->id)->exists())->toBeTrue();
    Storage::disk('local')->assertMissing('uploads/stale.pdf');
    Storage::disk('local')->assertExists('uploads/fresh.pdf');
});

it('honours the --days option over config', function () {
    config(['pdf.upload_retention_days' => 90]);

    Storage::disk('local')->put('uploads/edge.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/edge.pdf',
    ]);
    $doc->forceFill([
        'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ])->saveQuietly();

    $this->artisan('pdf:prune-expired-documents', ['--days' => 15])->assertSuccessful();

    expect(Document::query()->whereKey($doc->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('uploads/edge.pdf');
});

it('skips pruning when upload retention days is zero', function () {
    config(['pdf.upload_retention_days' => 0]);

    Storage::disk('local')->put('uploads/old.pdf', '%PDF-1.4');
    $doc = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/old.pdf',
    ]);
    $doc->forceFill([
        'created_at' => now()->subDays(400),
        'updated_at' => now()->subDays(400),
    ])->saveQuietly();

    $this->artisan('pdf:prune-expired-documents')->assertSuccessful();

    expect(Document::query()->whereKey($doc->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists('uploads/old.pdf');
});

it('prunes expired documents for every user in one run', function () {
    config(['pdf.upload_retention_days' => 30]);

    $other = User::factory()->create();
    Storage::disk('local')->put('uploads/theirs.pdf', '%PDF-1.4');
    $theirs = Document::factory()->uploaded()->for($other)->create([
        'file_path' => 'uploads/theirs.pdf',
    ]);
    $theirs->forceFill([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ])->saveQuietly();

    Storage::disk('local')->put('uploads/mine.pdf', '%PDF-1.4');
    $mine = Document::factory()->uploaded()->for($this->user)->create([
        'file_path' => 'uploads/mine.pdf',
    ]);
    $mine->forceFill([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ])->saveQuietly();

    $this->artisan('pdf:prune-expired-documents')->assertSuccessful();

    expect(Document::query()->whereKey($mine->id)->exists())->toBeFalse();
    expect(Document::query()->whereKey($theirs->id)->exists())->toBeFalse();
});

it('cascades signature requests when a document is pruned', function () {
    config(['pdf.upload_retention_days' => 30]);

    Storage::disk('local')->put('uploads/signed.pdf', '%PDF-1.4');
    $signedDoc = Document::factory()->signed()->for($this->user)->create([
        'file_path' => 'uploads/signed.pdf',
    ]);
    $signedDoc->forceFill([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ])->saveQuietly();

    $request = SignatureRequest::factory()->create([
        'document_id' => $signedDoc->id,
        'status' => SignatureRequest::STATUS_SIGNED,
    ]);

    $this->artisan('pdf:prune-expired-documents')->assertSuccessful();

    expect(Document::query()->whereKey($signedDoc->id)->exists())->toBeFalse();
    expect(SignatureRequest::query()->whereKey($request->id)->exists())->toBeFalse();
});
