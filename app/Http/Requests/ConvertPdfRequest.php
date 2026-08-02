<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Services\PdfConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertPdfRequest extends FormRequest
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
        return [
            'document_id' => [
                'required',
                'integer',
                Rule::exists('documents', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('status', Document::STATUS_COMPLETED)
                    ->where('mime_type', 'application/pdf'),
            ],
            'target' => ['required', Rule::in(PdfConversionService::SUPPORTED_TARGETS)],
            'password' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
