<?php

namespace App\Http\Controllers;

use App\Http\Requests\CapturePdfRequest;
use App\Http\Requests\UploadPdfRequest;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Services\PdfConversionService;
use App\Services\PdfFromImagesService;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfController extends Controller
{
    public function __construct(
        private PdfConversionService $conversion,
        private PdfFromImagesService $pdfFromImages,
    ) {}

    public function index(Request $request): View
    {
        $documents = Document::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return view('pdf.index', ['documents' => $documents]);
    }

    public function upload(UploadPdfRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $name = Str::uuid().'.pdf';
        $relativePath = $file->storeAs('uploads', $name, DocumentsDisk::name());
        $absolutePath = DocumentsDisk::disk()->path($relativePath);

        $pages = 0;
        try {
            $pages = $this->conversion->countPages($absolutePath);
        } catch (\Throwable) {
            // Ignore - file may be corrupt; status will reflect that.
        }

        $document = Document::create([
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $relativePath,
            'file_size' => $file->getSize(),
            'mime_type' => 'application/pdf',
            'pages' => $pages,
            'status' => $pages > 0 ? Document::STATUS_COMPLETED : Document::STATUS_FAILED,
            'operation_type' => Document::OP_UPLOAD,
        ]);

        return redirect()->route('pdf.show', $document)
            ->with('success', 'PDF uploaded successfully.');
    }

    public function storeFromCamera(CapturePdfRequest $request): RedirectResponse
    {
        $session = (string) Str::uuid();
        $tempPrefix = "temp/camera-{$session}";
        $paths = [];

        try {
            foreach ($request->file('images', []) as $file) {
                $ext = match ($file->getMimeType()) {
                    'image/png' => 'png',
                    default => 'jpg',
                };
                $stored = $file->storeAs($tempPrefix, Str::uuid().'.'.$ext, DocumentsDisk::name());
                $paths[] = DocumentsDisk::disk()->path($stored);
            }

            $relativePdf = $this->pdfFromImages->buildPdfFromImages($paths);
        } catch (\Throwable) {
            return back()->withErrors(['camera' => 'Could not build PDF from the captured pages. Try again with fewer or smaller photos.']);
        } finally {
            DocumentsDisk::disk()->deleteDirectory($tempPrefix);
        }

        $absolutePdf = DocumentsDisk::disk()->path($relativePdf);

        $pageCount = count($paths);

        $pages = 0;
        try {
            $pages = $this->conversion->countPages($absolutePdf);
        } catch (\Throwable) {
            DocumentsDisk::disk()->delete($relativePdf);

            return back()->withErrors(['camera' => 'Could not read the generated PDF. Try again with fewer pages.']);
        }

        $title = $request->string('title')->trim()->toString();
        $originalName = $title !== ''
            ? (str_ends_with(strtolower($title), '.pdf') ? $title : $title.'.pdf')
            : 'Camera-scan-'.now()->format('Y-m-d-His').'.pdf';

        $document = Document::create([
            'user_id' => $request->user()->id,
            'original_name' => $originalName,
            'file_path' => $relativePdf,
            'file_size' => DocumentsDisk::disk()->size($relativePdf),
            'mime_type' => 'application/pdf',
            'pages' => $pages,
            'status' => Document::STATUS_COMPLETED,
            'operation_type' => Document::OP_CAPTURE,
            'metadata' => [
                'source' => 'camera',
                'captured_pages' => $pageCount,
            ],
        ]);

        return redirect()->route('pdf.show', $document)
            ->with('success', 'PDF created from '.$pageCount.' camera page(s).');
    }

    public function show(Request $request, Document $document): View
    {
        $this->authorize('view', $document);

        $envelopeId = $document->operation_type === Document::OP_SIGNED && $document->parent_document_id
            ? $document->parent_document_id
            : $document->id;

        $signatureRequests = SignatureRequest::query()
            ->where('source_document_id', $envelopeId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('pdf.show', [
            'document' => $document,
            'signatureRequests' => $signatureRequests,
            'envelopeDocumentId' => $envelopeId,
        ]);
    }

    public function stream(Request $request, Document $document): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('view', $document);
        $this->ensureDocumentFileIsReady($document);

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $document->original_name,
            preg_replace('/[^\x20-\x7E]/', '_', $document->original_name) ?: 'document.pdf',
        );

        return response()->file($document->absolutePath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }

    public function download(Request $request, Document $document): BinaryFileResponse
    {
        $this->authorize('view', $document);
        $this->ensureDocumentFileIsReady($document);

        return response()->download($document->absolutePath(), $document->original_name);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        if (DocumentsDisk::disk()->exists($document->file_path)) {
            DocumentsDisk::disk()->delete($document->file_path);
        }
        $document->delete();

        return redirect()->route('pdf.index')->with('success', 'Document deleted.');
    }

    private function ensureDocumentFileIsReady(Document $document): void
    {
        if ($document->isFileReady()) {
            return;
        }

        $message = match ($document->status) {
            Document::STATUS_PROCESSING, Document::STATUS_PENDING => 'This document is still processing. Refresh in a moment.',
            Document::STATUS_FAILED => 'This document failed to process and has no file to download.',
            default => 'Document file is not available.',
        };

        abort(404, $message);
    }
}
