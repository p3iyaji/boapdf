<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FillPdfFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed|string>|string>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1', 'max:200'],
            'fields.*.name' => ['nullable', 'string', 'max:200'],
            'fields.*.type' => ['required', 'string', 'in:text,textarea,checkbox,radio,dropdown,combobox'],
            'fields.*.page' => ['required', 'integer', 'min:1'],
            'fields.*.x' => ['required', 'numeric', 'min:0'],
            'fields.*.y' => ['required', 'numeric', 'min:0'],
            'fields.*.width' => ['required', 'numeric', 'min:2', 'max:400'],
            'fields.*.height' => ['required', 'numeric', 'min:2', 'max:400'],
            'fields.*.value' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasValue = false;
            foreach ($this->input('fields', []) as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $type = $field['type'] ?? 'text';
                $value = $field['value'] ?? null;
                if (in_array($type, ['checkbox', 'radio'], true)) {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || in_array($value, ['Yes', 'On', 1, '1', true], true)) {
                        $hasValue = true;
                    }
                } elseif ($value !== null && trim((string) $value) !== '') {
                    $hasValue = true;
                }
            }

            if (! $hasValue) {
                $v->errors()->add('fields', 'Enter at least one field value before saving.');
            }
        });
    }
}
