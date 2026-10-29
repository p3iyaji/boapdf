<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EditPdfRequest extends FormRequest
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
            'replacements' => ['sometimes', 'array', 'max:30'],
            'replacements.*.page' => ['required', 'integer', 'min:1'],
            'replacements.*.rects' => ['required', 'array', 'min:1', 'max:40'],
            'replacements.*.rects.*' => ['array', 'size:4'],
            'replacements.*.rects.*.*' => ['numeric'],
            'replacements.*.text' => ['required', 'string', 'max:500'],
            'replacements.*.size' => ['nullable', 'numeric', 'min:6', 'max:72'],
            'highlights' => ['sometimes', 'array', 'max:40'],
            'highlights.*.page' => ['required', 'integer', 'min:1'],
            'highlights.*.rects' => ['required', 'array', 'min:1', 'max:80'],
            'highlights.*.rects.*' => ['array', 'size:4'],
            'highlights.*.rects.*.*' => ['numeric'],
            'covers' => ['sometimes', 'array', 'max:40'],
            'covers.*.page' => ['required', 'integer', 'min:1'],
            'covers.*.rect' => ['required', 'array', 'size:4'],
            'covers.*.rect.*' => ['numeric'],
            'redactions' => ['sometimes', 'array', 'max:40'],
            'redactions.*.page' => ['required', 'integer', 'min:1'],
            'redactions.*.rect' => ['required', 'array', 'size:4'],
            'redactions.*.rect.*' => ['numeric'],
            'texts' => ['sometimes', 'array', 'max:40'],
            'texts.*.page' => ['required', 'integer', 'min:1'],
            'texts.*.rect' => ['required', 'array', 'size:4'],
            'texts.*.rect.*' => ['numeric'],
            'texts.*.text' => ['required', 'string', 'max:2000'],
            'texts.*.size' => ['nullable', 'numeric', 'min:6', 'max:72'],
            'texts.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'images' => ['sometimes', 'array', 'max:15'],
            'images.*.page' => ['required', 'integer', 'min:1'],
            'images.*.rect' => ['required', 'array', 'size:4'],
            'images.*.rect.*' => ['numeric'],
            'images.*.image' => ['required', 'string', 'max:4000000'],
            'fields' => ['sometimes', 'array', 'max:200'],
            'fields.*.name' => ['required', 'string', 'max:200'],
            'fields.*.value' => ['nullable'],
            'flatten' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $groups = ['replacements', 'highlights', 'covers', 'redactions', 'texts', 'images', 'fields'];
                $count = 0;

                foreach ($groups as $group) {
                    $count += count($this->input($group, []));
                }

                if ($count === 0) {
                    $validator->errors()->add('edit', 'Add an edit before saving.');
                }

                foreach ($this->input('images', []) as $index => $image) {
                    if (! is_array($image)) {
                        continue;
                    }

                    $data = (string) ($image['image'] ?? '');

                    if (! preg_match('#^data:image/(png|jpe?g);base64,#i', $data)) {
                        $validator->errors()->add("images.$index.image", 'Images must be PNG or JPEG.');
                    }
                }

                foreach ($this->input('fields', []) as $index => $field) {
                    if (! is_array($field) || ! array_key_exists('value', $field) || $field['value'] === null) {
                        continue;
                    }

                    $value = $field['value'];

                    if (is_array($value) || (is_string($value) && strlen($value) > 2000)) {
                        $validator->errors()->add("fields.$index.value", 'That field value is too long.');
                    }
                }
            },
        ];
    }
}
