<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SignPdfRequest extends FormRequest
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
            'signature' => ['nullable', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
            'page' => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn () => $this->filled('signature'))],
            'x' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn () => $this->filled('signature'))],
            'y' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn () => $this->filled('signature'))],
            'width' => ['nullable', 'numeric', 'min:10', 'max:300'],
            'typed_signature' => ['nullable', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
            'typed_page' => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn () => $this->filled('typed_signature'))],
            'typed_x' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn () => $this->filled('typed_signature'))],
            'typed_y' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn () => $this->filled('typed_signature'))],
            'typed_width' => ['nullable', 'numeric', 'min:10', 'max:300'],
            // Base64 inflates payload (~4/3 of raw bytes); allow roughly multi‑MB logos within typical post limits.
            'logo' => ['nullable', 'string', 'regex:/^data:image\/(png|jpe?g|webp|gif);base64,/i', 'max:4000000'],
            'logo_page' => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn () => $this->filled('logo'))],
            'logo_x' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn () => $this->filled('logo'))],
            'logo_y' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn () => $this->filled('logo'))],
            'logo_width' => ['nullable', 'numeric', 'min:5', 'max:300', Rule::requiredIf(fn () => $this->filled('logo'))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (! $this->filled('signature') && ! $this->filled('typed_signature') && ! $this->filled('logo')) {
                $v->errors()->add(
                    'sign',
                    'Please add at least a drawn signature, typed text, or a logo, place it on the PDF, then apply.',
                );
            }
        });
    }
}
