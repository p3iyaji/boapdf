<?php

namespace App\Services;

use App\Jobs\ProcessPdfEditJob;
use App\Models\Document;
use App\Models\User;
use App\Support\DocumentsDisk;
use Illuminate\Support\Str;

class DocumentEditService
{
    /**
     * @param  array<string, mixed>  $spec
     */
    public function queueContent(User $user, Document $source, array $spec): Document
    {
        $spec['images'] = $this->persistImages($spec['images'] ?? []);

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
            'metadata' => [
                'mode' => 'content',
                'replacement_count' => count($spec['replacements'] ?? []),
                'mark_count' => count($spec['highlights'] ?? [])
                    + count($spec['covers'] ?? [])
                    + count($spec['redactions'] ?? [])
                    + count($spec['texts'] ?? [])
                    + count($spec['images'] ?? []),
                'field_count' => count($spec['fields'] ?? []),
            ],
        ]);

        ProcessPdfEditJob::dispatch($document->id, $source->id, 'content', $spec);

        return $document->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    public function queuePages(User $user, Document $source, array $pages): Document
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
            'metadata' => [
                'mode' => 'pages',
                'page_count' => count($pages),
            ],
        ]);

        ProcessPdfEditJob::dispatch($document->id, $source->id, 'pages', ['pages' => $pages]);

        return $document->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $images
     * @return list<array{page: int, rect: array<int, float>, path: string}>
     */
    private function persistImages(array $images): array
    {
        $stored = [];

        foreach ($images as $image) {
            $dataUrl = (string) ($image['image'] ?? '');

            if (! preg_match('#^data:image/(png|jpe?g);base64,#i', $dataUrl, $matches)) {
                throw new PdfEditException('An image is not a supported picture.');
            }

            $payload = substr($dataUrl, (int) strpos($dataUrl, ',') + 1);
            $raw = base64_decode($payload, true);

            if ($raw === false || strlen($raw) > 2_500_000) {
                throw new PdfEditException('An image is too large.');
            }

            $extension = str_contains(strtolower($matches[1]), 'png') ? 'png' : 'jpg';
            $relative = 'edited/assets/'.Str::uuid().'.'.$extension;
            $disk = DocumentsDisk::disk();

            if (! $disk->exists('edited/assets')) {
                $disk->makeDirectory('edited/assets');
            }

            $disk->put($relative, $raw);

            $stored[] = [
                'page' => (int) $image['page'],
                'rect' => array_map(floatval(...), $image['rect']),
                'path' => $disk->path($relative),
            ];
        }

        return $stored;
    }
}
