<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the change password form via profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Change password', false);
});

it('lets an authenticated user change their password', function () {
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
});

it('rejects password changes when the current password is wrong', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-secret'),
    ]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.password'), [
            'current_password' => 'wrong-secret',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('old-secret', $user->fresh()->password))->toBeTrue();
});

it('shows the admin change password form to admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.password.edit'))
        ->assertOk()
        ->assertSee('Change password', false)
        ->assertSee('Logout', false);
});

it('lets an admin change their password from the admin dashboard', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('old-secret'),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.password.update'), [
            'current_password' => 'old-secret',
            'password' => 'admin-new-pass',
            'password_confirmation' => 'admin-new-pass',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Hash::check('admin-new-pass', $admin->fresh()->password))->toBeTrue();
});

it('forbids non-admins from the admin change password page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.password.edit'))
        ->assertForbidden();
});

it('shows logout on the admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Logout', false)
        ->assertSee(route('logout'), false);
});

it('does not show the convert tile on the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('>Convert</h3>', false)
        ->assertDontSee(route('pdf.convert.create'), false);
});

it('does not show the create menu in the sidebar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('pdf.create.create'), false)
        ->assertSee(route('profile.edit'), false);
});
