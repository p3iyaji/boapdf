<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('shows the profile page with password and delete sections', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Your profile', false)
        ->assertSee('Change password', false)
        ->assertSee('Delete account', false);
});

it('lets a user update their profile details', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'old@example.com',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success');

    expect($user->fresh()->name)->toBe('New Name');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'profile.updated',
        'user_id' => $user->id,
    ]);
});

it('lets a user change their password from the profile page', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-secret'),
    ]);

    $this->actingAs($user)
        ->put(route('profile.password'), [
            'current_password' => 'old-secret',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success');

    expect(Hash::check('new-secret-pass', $user->fresh()->password))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'password.changed',
        'user_id' => $user->id,
    ]);
});

it('deletes the account, documents, and anonymizes personal data', function () {
    Storage::fake(DocumentsDisk::name());

    $user = User::factory()->create([
        'name' => 'Erase Me',
        'email' => 'erase-me@example.com',
        'password' => Hash::make('secret-pass'),
    ]);

    $path = 'uploads/to-delete.pdf';
    Storage::disk(DocumentsDisk::name())->put($path, '%PDF-1.4 test');

    $document = Document::factory()->create([
        'user_id' => $user->id,
        'file_path' => $path,
        'status' => Document::STATUS_COMPLETED,
    ]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'secret-pass',
            'confirmation' => 'DELETE',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('success');

    expect(auth()->check())->toBeFalse();

    $this->assertSoftDeleted($user);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    expect(Storage::disk(DocumentsDisk::name())->exists($path))->toBeFalse();

    $trashed = User::onlyTrashed()->findOrFail($user->id);
    expect($trashed->name)->toBe('Deleted User')
        ->and($trashed->email)->toBe('deleted-'.$user->id.'@deleted.local')
        ->and($trashed->is_active)->toBeFalse();

    $log = AuditLog::query()->where('action', 'account.deleted')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_email)->toBe('erase-me@example.com')
        ->and($log->actor_name)->toBe('Erase Me')
        ->and($log->metadata['email'])->toBe('erase-me@example.com');
});

it('rejects account deletion without typing DELETE', function () {
    $user = User::factory()->create([
        'password' => Hash::make('secret-pass'),
    ]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'secret-pass',
            'confirmation' => 'please',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('confirmation');

    expect($user->fresh()->trashed())->toBeFalse();
});

it('redirects the old password page to the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.edit'))
        ->assertRedirect(route('profile.edit'));
});
