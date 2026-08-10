<?php

use App\Models\User;
use App\Support\DisposableEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    DisposableEmail::flush();
    config([
        'disposable-email.enabled' => true,
        'disposable-email.domains_path' => null,
        'disposable-email.allow' => [],
        'disposable-email.deny' => ['mailinator.com', 'yopmail.com'],
    ]);
});

afterEach(function (): void {
    DisposableEmail::flush();
});

it('rejects registration with a disposable email', function () {
    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Temp User',
            'email' => 'temp@mailinator.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'terms' => '1',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'temp@mailinator.com')->exists())->toBeFalse()
        ->and(auth()->check())->toBeFalse();
});

it('rejects login with a disposable email', function () {
    User::factory()->create([
        'email' => 'temp@mailinator.com',
        'password' => Hash::make('secret-pass'),
    ]);

    $this->from(route('login'))
        ->post(route('authenticate'), [
            'email' => 'temp@mailinator.com',
            'password' => 'secret-pass',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('logs out a disposable-email user on authenticated routes', function () {
    $user = User::factory()->create([
        'email' => 'temp@yopmail.com',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('still allows registration with a normal email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
        'terms' => '1',
    ])->assertRedirect(route('verification.notice'));

    expect(User::where('email', 'ada@example.com')->exists())->toBeTrue();
});
