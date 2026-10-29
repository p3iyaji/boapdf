<?php

use App\Support\DisposableEmail;

beforeEach(function (): void {
    DisposableEmail::flush();
    config([
        'disposable-email.enabled' => true,
        'disposable-email.domains_path' => null,
        'disposable-email.allow' => [],
        'disposable-email.deny' => ['trashmail.test', 'throwaway.example'],
    ]);
});

afterEach(function (): void {
    DisposableEmail::flush();
});

it('detects denied disposable domains', function () {
    expect(DisposableEmail::isDisposable('user@trashmail.test'))->toBeTrue()
        ->and(DisposableEmail::isDisposable('user@mail.throwaway.example'))->toBeTrue();
});

it('allows normal email domains', function () {
    expect(DisposableEmail::isDisposable('ada@example.com'))->toBeFalse()
        ->and(DisposableEmail::isDisposable('grace@company.org'))->toBeFalse();
});

it('respects the allow list over deny', function () {
    config(['disposable-email.allow' => ['trashmail.test']]);
    DisposableEmail::flush();

    expect(DisposableEmail::isDisposable('user@trashmail.test'))->toBeFalse();
});

it('can be disabled via config', function () {
    config(['disposable-email.enabled' => false]);

    expect(DisposableEmail::isDisposable('user@trashmail.test'))->toBeFalse();
});

it('loads domains from the blocklist file', function () {
    $path = sys_get_temp_dir().'/disposable-email-test-'.uniqid('', true).'.txt';
    file_put_contents($path, "tempbox.test\n# comment\n\n");

    config([
        'disposable-email.domains_path' => $path,
        'disposable-email.deny' => [],
    ]);
    DisposableEmail::flush();

    expect(DisposableEmail::isDisposable('a@tempbox.test'))->toBeTrue()
        ->and(DisposableEmail::isDisposable('a@example.com'))->toBeFalse();

    unlink($path);
});
