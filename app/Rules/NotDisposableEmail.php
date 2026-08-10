<?php

namespace App\Rules;

use App\Support\DisposableEmail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NotDisposableEmail implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (DisposableEmail::isDisposable($value)) {
            $fail('Disposable email addresses are not allowed.');
        }
    }
}
