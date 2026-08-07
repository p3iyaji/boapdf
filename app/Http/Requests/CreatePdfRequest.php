<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreatePdfRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:180'],
            'page_size' => ['nullable', 'string', 'in:A4,LETTER'],
            'orientation' => ['nullable', 'string', 'in:P,L'],
            'pages' => ['required', 'array', 'min:1', 'max:30'],
            'pages.*.elements' => ['nullable', 'array', 'max:100'],
            'pages.*.elements.*.type' => ['required', 'string', 'in:text,image'],
            'pages.*.elements.*.x' => ['required', 'numeric', 'min:0'],
            'pages.*.elements.*.y' => ['required', 'numeric', 'min:0'],
            'pages.*.elements.*.width' => ['required', 'numeric', 'min:5', 'max:400'],
            'pages.*.elements.*.height' => ['nullable', 'numeric', 'min:2', 'max:400'],
            'pages.*.elements.*.text' => ['nullable', 'string', 'max:5000'],
            'pages.*.elements.*.font_size' => ['nullable', 'numeric', 'min:6', 'max:72'],
            'pages.*.elements.*.color' => ['nullable', 'string', 'max:20'],
            'pages.*.elements.*.image' => ['nullable', 'string', 'max:4000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasContent = false;
            foreach ($this->input('pages', []) as $pi => $page) {
                foreach ($page['elements'] ?? [] as $ei => $element) {
                    if (! is_array($element)) {
                        continue;
                    }
                    $type = $element['type'] ?? '';
                    if ($type === 'text' && filled(trim((string) ($element['text'] ?? '')))) {
                        $hasContent = true;
                    }
                    if ($type === 'image') {
                        $image = (string) ($element['image'] ?? '');
                        if ($image === '') {
                            $v->errors()->add("pages.$pi.elements.$ei.image", 'Image elements require an image.');
                        } elseif (! preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,/i', $image)) {
                            $v->errors()->add("pages.$pi.elements.$ei.image", 'Image must be a valid data URL.');
                        } else {
                            $hasContent = true;
                        }
                    }
                }
            }

            if (! $hasContent) {
                $v->errors()->add('pages', 'Add at least one text block or image before creating the PDF.');
            }
        });
    }
}
