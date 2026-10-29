<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EditPdfPagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pages' => ['required', 'array', 'min:1', 'max:200'],
            'pages.*.source' => ['nullable', 'integer', 'min:1'],
            'pages.*.blank' => ['sometimes', 'boolean'],
            'pages.*.rotate' => ['required', 'integer', 'in:0,90,180,270'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('pages', []) as $index => $page) {
                    if (! is_array($page)) {
                        continue;
                    }

                    $blank = filter_var($page['blank'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $source = $page['source'] ?? null;

                    if (! $blank && ! is_numeric($source)) {
                        $validator->errors()->add("pages.$index.source", 'Each page needs a source page or must be blank.');
                    }
                }
            },
        ];
    }
}
