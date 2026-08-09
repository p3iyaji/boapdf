<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SignPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authenticated self-sign uses the policy in the controller.
        // Guest signing is authorized via a valid invitation token in the route.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('typed_signature') && blank($this->input('typed_texts'))) {
            $this->merge([
                'typed_texts' => [[
                    'image' => $this->input('typed_signature'),
                    'page' => $this->input('typed_page'),
                    'x' => $this->input('typed_x'),
                    'y' => $this->input('typed_y'),
                    'width' => $this->input('typed_width'),
                ]],
            ]);
        }
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
            // Legacy single typed overlay (normalized into typed_texts in prepareForValidation).
            'typed_signature' => ['nullable', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
            'typed_page' => ['nullable', 'integer', 'min:1'],
            'typed_x' => ['nullable', 'numeric', 'min:0'],
            'typed_y' => ['nullable', 'numeric', 'min:0'],
            'typed_width' => ['nullable', 'numeric', 'min:10', 'max:300'],
            'typed_texts' => ['nullable', 'array', 'max:20'],
            'typed_texts.*.image' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
            'typed_texts.*.page' => ['required', 'integer', 'min:1'],
            'typed_texts.*.x' => ['required', 'numeric', 'min:0'],
            'typed_texts.*.y' => ['required', 'numeric', 'min:0'],
            'typed_texts.*.width' => ['nullable', 'numeric', 'min:10', 'max:300'],
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
            $hasTypedTexts = is_array($this->input('typed_texts')) && count($this->input('typed_texts')) > 0;

            if (! $this->filled('signature') && ! $hasTypedTexts && ! $this->filled('logo')) {
                $v->errors()->add(
                    'sign',
                    'Please add at least a drawn signature, typed text, or a logo, place it on the PDF, then apply.',
                );
            }
        });
    }
}
