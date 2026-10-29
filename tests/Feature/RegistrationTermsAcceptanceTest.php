<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('requires accepting the terms to register', function () {
    Notification::fake();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'No Terms',
            'email' => 'no-terms@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('terms');

    expect(User::where('email', 'no-terms@example.com')->exists())->toBeFalse();
});

it('registers when terms are accepted', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'With Terms',
        'email' => 'with-terms@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
        'terms' => '1',
    ])->assertRedirect(route('verification.notice'));

    expect(User::where('email', 'with-terms@example.com')->exists())->toBeTrue();
});
