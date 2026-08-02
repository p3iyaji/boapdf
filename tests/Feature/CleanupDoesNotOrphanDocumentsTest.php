<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('does not delete library-linked files during pdf:cleanup', function () {
    $user = User::factory()->create();

    Storage::disk('local')->put('merged/linked.pdf', 'linked');
    touch(Storage::disk('local')->path('merged/linked.pdf'), now()->subDays(2)->getTimestamp());

    Document::factory()->for($user)->create([
        'file_path' => 'merged/linked.pdf',
        'operation_type' => Document::OP_MERGED,
    ]);

    Storage::disk('local')->put('merged/orphan.pdf', 'orphan');
    touch(Storage::disk('local')->path('merged/orphan.pdf'), now()->subDays(2)->getTimestamp());

    $this->artisan('pdf:cleanup')->assertSuccessful();

    Storage::disk('local')->assertExists('merged/linked.pdf');
    Storage::disk('local')->assertMissing('merged/orphan.pdf');
    expect(Document::query()->where('file_path', 'merged/linked.pdf')->exists())->toBeTrue();
});
