<?php

namespace App\Http\Controllers;

use App\Http\Requests\EditPdfPagesRequest;
use App\Http\Requests\EditPdfRequest;
use App\Models\Document;
use App\Services\DocumentEditService;
use App\Services\PdfEditException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditController extends Controller
{
    public function __construct(private DocumentEditService $editing) {}

    public function create(Document $document): View
    {
        $this->authorize('view', $document);

        if (! $document->isFileReady() || $document->mime_type !== 'application/pdf') {
            abort(404);
        }

        return view('pdf.edit', [
            'document' => $document,
        ]);
    }

    public function store(EditPdfRequest $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);
        $this->ensurePdf($document);

        try {
            $edited = $this->editing->queueContent(
                $request->user(),
                $document,
                $request->safe()->only([
                    'replacements',
                    'highlights',
                    'covers',
                    'redactions',
                    'texts',
                    'images',
                    'fields',
                    'flatten',
                ]),
            );
        } catch (PdfEditException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->editResponse($edited);
    }

    public function storePages(EditPdfPagesRequest $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);
        $this->ensurePdf($document);

        $edited = $this->editing->queuePages(
            $request->user(),
            $document,
            $request->validated('pages'),
        );

        return $this->editResponse($edited);
    }

    public function status(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        return response()->json([
            'status' => $document->status,
            'error' => $document->status === Document::STATUS_FAILED
                ? (string) ($document->metadata['error'] ?? 'Could not edit this PDF.')
                : null,
            'show_url' => route('pdf.show', $document),
        ]);
    }

    private function ensurePdf(Document $document): void
    {
        if (! $document->isFileReady() || $document->mime_type !== 'application/pdf') {
            abort(404);
        }
    }

    private function editResponse(Document $edited): JsonResponse
    {
        if ($edited->status === Document::STATUS_FAILED) {
            return response()->json([
                'message' => (string) ($edited->metadata['error'] ?? 'Could not edit this PDF.'),
            ], 422);
        }

        return response()->json([
            'id' => $edited->id,
            'status' => $edited->status,
            'show_url' => route('pdf.show', $edited),
            'status_url' => route('pdf.status', $edited),
        ]);
    }
}
