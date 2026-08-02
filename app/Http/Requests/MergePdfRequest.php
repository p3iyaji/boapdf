<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergePdfRequest extends FormRequest
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
            'documents' => ['required', 'array', 'min:2'],
            'documents.*' => [
                'integer',
                Rule::exists('documents', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('status', Document::STATUS_COMPLETED)
                    ->where('mime_type', 'application/pdf'),
            ],
            'output_name' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Return documents in the order the user supplied them in the request.
     *
     * @return array<int, Document>
     */
    public function documents(): array
    {
        $ids = array_map('intval', (array) $this->input('documents'));

        $byId = Document::query()
            ->whereIn('id', $ids)
            ->where('user_id', $this->user()->id)
            ->get()
            ->keyBy('id');

        return array_values(array_filter(array_map(
            fn (int $id): ?Document => $byId->get($id),
            $ids,
        )));
    }
}
