<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('lets an admin create a user', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'is_admin' => '0',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $created = User::where('email', 'new.person@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('New Person')
        ->and($created->is_admin)->toBeFalse()
        ->and($created->is_active)->toBeTrue();
});

it('lets an admin deactivate and reactivate a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.activation', $user), ['activate' => '0'])
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.users.activation', $user), ['activate' => '1'])
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeTrue();
});

it('soft deletes a user and can restore them', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['email' => 'soft.delete@example.com']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'soft.delete@example.com')->exists())->toBeFalse()
        ->and(User::onlyTrashed()->where('email', 'soft.delete@example.com')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.users.restore', $user->id))
        ->assertRedirect();

    expect(User::where('email', 'soft.delete@example.com')->exists())->toBeTrue();
});

it('prevents deleting the last active admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.users.show', $admin))
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect()
        ->assertSessionHasErrors('user');

    expect($admin->fresh())->not->toBeNull()
        ->and($admin->fresh()->trashed())->toBeFalse();
});

it('prevents an admin from deactivating themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.users.show', $admin))
        ->patch(route('admin.users.activation', $admin), ['activate' => '0'])
        ->assertRedirect()
        ->assertSessionHasErrors('user');

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('lets an admin update a user password', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'password' => Hash::make('old-secret'),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
            'is_admin' => '0',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.users.show', $user));

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});
