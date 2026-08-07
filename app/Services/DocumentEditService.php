<?php

namespace App\Services;

use App\Jobs\ProcessPdfCreateJob;
use App\Jobs\ProcessPdfEditJob;
use App\Jobs\ProcessPdfFormFillJob;
use App\Models\Document;
use App\Models\User;

class DocumentEditService
{
    /**
     * @param  list<array<string, mixed>>  $annotations
     */
    public function queueEdit(User $user, Document $source, array $annotations): Document
    {
        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => pathinfo($source->original_name, PATHINFO_FILENAME).'-edited.pdf',
            'file_path' => 'edited/pending-'.uniqid('', true).'.pdf',
            'file_size' => 0,
            'mime_type' => 'application/pdf',
            'pages' => 0,
            'status' => Document::STATUS_PROCESSING,
            'operation_type' => Document::OP_EDITED,
            'parent_document_id' => $source->id,
            'metadata' => ['annotation_count' => count($annotations)],
        ]);

        ProcessPdfEditJob::dispatch($document->id, $source->id, $annotations);

        return $document->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function queueFormFill(User $user, Document $source, array $fields): Document
    {
        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => pathinfo($source->original_name, PATHINFO_FILENAME).'-filled.pdf',
            'file_path' => 'edited/pending-'.uniqid('', true).'.pdf',
            'file_size' => 0,
            'mime_type' => 'application/pdf',
            'pages' => 0,
            'status' => Document::STATUS_PROCESSING,
            'operation_type' => Document::OP_FORM_FILLED,
            'parent_document_id' => $source->id,
            'metadata' => ['field_count' => count($fields)],
        ]);

        ProcessPdfFormFillJob::dispatch($document->id, $source->id, $fields);

        return $document->fresh();
    }

    /**
     * @param  array{
     *     title?: string,
     *     page_size?: string,
     *     orientation?: string,
     *     pages: list<array<string, mixed>>
     * }  $definition
     */
    public function queueCreate(User $user, array $definition): Document
    {
        $title = trim((string) ($definition['title'] ?? 'Untitled'));
        if ($title === '') {
            $title = 'Untitled';
        }
        if (! str_ends_with(strtolower($title), '.pdf')) {
            $title .= '.pdf';
        }

        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => $title,
            'file_path' => 'created/pending-'.uniqid('', true).'.pdf',
            'file_size' => 0,
            'mime_type' => 'application/pdf',
            'pages' => 0,
            'status' => Document::STATUS_PROCESSING,
            'operation_type' => Document::OP_CREATED,
            'parent_document_id' => null,
            'metadata' => [
                'page_size' => $definition['page_size'] ?? 'A4',
                'orientation' => $definition['orientation'] ?? 'P',
                'page_count' => count($definition['pages'] ?? []),
            ],
        ]);

        ProcessPdfCreateJob::dispatch($document->id, $definition);

        return $document->fresh();
    }
}
