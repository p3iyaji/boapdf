<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CapturePdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = min((int) config('pdf.max_file_size', 51200), 5120);

        return [
            'title' => ['nullable', 'string', 'max:200'],
            'images' => ['required', 'array', 'min:1', 'max:30'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:'.$maxKb],
        ];
    }
}
