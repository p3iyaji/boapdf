<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('shows the marketing homepage on /', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(config('app.name'), false)
        ->assertSee('Hold your documents steady', false);
});

it('shows the login page on /login', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in', false);
});

it('lets a user register, sends a verification email, and shows the notice', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
        'terms' => '1',
    ])->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects bad credentials on login', function () {
    User::factory()->create([
        'email' => 'alan@example.com',
        'password' => Hash::make('right-pass'),
    ]);

    $this->post(route('authenticate'), [
        'email' => 'alan@example.com',
        'password' => 'wrong-pass',
    ])->assertRedirect();

    expect(auth()->check())->toBeFalse();
});

it('accepts good credentials and lands on the dashboard', function () {
    User::factory()->create([
        'email' => 'grace@example.com',
        'password' => Hash::make('hopper-1947'),
    ]);

    $this->post(route('authenticate'), [
        'email' => 'grace@example.com',
        'password' => 'hopper-1947',
    ])->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue();
});

it('protects the pdf routes behind auth', function () {
    $this->get(route('pdf.index'))->assertRedirect(route('login'));
    $this->post(route('pdf.upload'), [])->assertRedirect(route('login'));
    $this->post(route('pdf.upload.camera'), [])->assertRedirect(route('login'));
    $this->get(route('pdf.merge.create'))->assertRedirect(route('login'));
    $this->get(route('pdf.compress.create'))->assertRedirect(route('login'));
    $this->get(route('pdf.convert.create'))->assertRedirect(route('login'));
});

it('shows the forgot password page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Forgot password', false);
});

it('sends a reset notification and completes password reset', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset-me@example.com',
        'password' => Hash::make('old-secret'),
    ]);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        return true;
    });

    $user->refresh();

    expect(Hash::check('new-secret-pass', $user->password))->toBeTrue();
});
