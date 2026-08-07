<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EditPdfRequest extends FormRequest
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
            'annotations' => ['required', 'array', 'min:1', 'max:50'],
            'annotations.*.type' => ['required', 'string', 'in:text,image,draw,highlight'],
            'annotations.*.page' => ['required', 'integer', 'min:1'],
            'annotations.*.x' => ['required', 'numeric', 'min:0'],
            'annotations.*.y' => ['required', 'numeric', 'min:0'],
            'annotations.*.width' => ['required', 'numeric', 'min:2', 'max:400'],
            'annotations.*.height' => ['nullable', 'numeric', 'min:2', 'max:400'],
            'annotations.*.text' => ['nullable', 'string', 'max:2000'],
            'annotations.*.font_size' => ['nullable', 'numeric', 'min:6', 'max:72'],
            'annotations.*.color' => ['nullable', 'string', 'max:20'],
            'annotations.*.image' => ['nullable', 'string', 'max:4000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            foreach ($this->input('annotations', []) as $i => $annotation) {
                if (! is_array($annotation)) {
                    continue;
                }
                $type = $annotation['type'] ?? '';
                if ($type === 'text' && blank($annotation['text'] ?? null)) {
                    $v->errors()->add("annotations.$i.text", 'Text annotations require text.');
                }
                if (in_array($type, ['image', 'draw'], true) && blank($annotation['image'] ?? null)) {
                    $v->errors()->add("annotations.$i.image", 'Image annotations require an image.');
                }
                if (in_array($type, ['image', 'draw'], true)
                    && filled($annotation['image'] ?? null)
                    && ! preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,/i', (string) $annotation['image'])
                ) {
                    $v->errors()->add("annotations.$i.image", 'Image must be a valid data URL.');
                }
                if ($type === 'draw'
                    && filled($annotation['image'] ?? null)
                    && ! str_starts_with((string) $annotation['image'], 'data:image/png;base64,')
                ) {
                    $v->errors()->add("annotations.$i.image", 'Drawings must be PNG data URLs.');
                }
            }
        });
    }
}
