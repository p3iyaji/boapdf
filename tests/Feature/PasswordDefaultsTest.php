<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

it('does not reject commonly leaked passwords that meet complexity rules', function () {
    App::detectEnvironment(fn (): string => 'production');

    $validator = Validator::make(
        ['password' => 'Password1'],
        ['password' => ['required', Password::defaults()]],
    );

    expect($validator->passes())->toBeTrue();
});

it('still requires mixed case and numbers in production', function () {
    App::detectEnvironment(fn (): string => 'production');

    $validator = Validator::make(
        ['password' => 'password'],
        ['password' => ['required', Password::defaults()]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('password'))->toBeTrue();
});
