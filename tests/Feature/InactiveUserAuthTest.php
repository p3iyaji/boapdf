<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('rejects login for inactive users', function () {
    User::factory()->inactive()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('secret-pass'),
    ]);

    $this->post(route('authenticate'), [
        'email' => 'inactive@example.com',
        'password' => 'secret-pass',
    ])->assertRedirect()
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('logs out an inactive user when they hit an authenticated route', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $user->deactivate();

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});
