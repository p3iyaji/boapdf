<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InviteSignersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed|string>|string>
     */
    public function rules(): array
    {
        return [
            'signers' => ['required', 'array', 'min:1', 'max:20'],
            'signers.*.email' => ['required', 'email', 'max:255'],
            'signers.*.name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $emails = collect($this->input('signers', []))
                ->pluck('email')
                ->filter()
                ->map(fn ($email) => strtolower(trim((string) $email)));

            if ($emails->count() !== $emails->unique()->count()) {
                $v->errors()->add('signers', 'Each signer email must be unique.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signers.required' => 'Add at least one person to request a signature.',
            'signers.min' => 'Add at least one person to request a signature.',
            'signers.*.email.required' => 'Each signer needs an email address.',
            'signers.*.email.email' => 'Enter a valid email address for each signer.',
        ];
    }
}
