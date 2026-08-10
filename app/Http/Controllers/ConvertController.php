<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertPdfRequest;
use App\Jobs\ProcessPdfConvertJob;
use App\Models\Document;
use App\Services\PdfConversionService;
use App\Support\DocumentsDisk;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ConvertController extends Controller
{
    public function create(Request $request): View
    {
        $documents = Document::query()
            ->forUser($request->user()->id)
            ->completedPdfs()
            ->latest()
            ->get();

        $convertDocuments = $documents->map(fn (Document $d): array => [
            'id' => $d->id,
            'name' => $d->original_name,
            'pages' => $d->pages,
            'size' => $d->human_file_size,
        ])->values()->all();

        return view('pdf.convert', [
            'documents' => $documents,
            'convertDocuments' => $convertDocuments,
            'targets' => PdfConversionService::SUPPORTED_TARGETS,
        ]);
    }

    public function store(ConvertPdfRequest $request): BinaryFileResponse|RedirectResponse
    {
        /** @var Document $source */
        $source = Document::query()
            ->where('id', $request->integer('document_id'))
            ->where('user_id', $request->user()->id)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf')
            ->firstOrFail();

        $target = (string) $request->input('target');
        $password = $request->string('password')->toString() ?: null;
        $baseName = pathinfo($source->original_name, PATHINFO_FILENAME);

        try {
            $document = Document::create([
                'user_id' => $request->user()->id,
                'original_name' => $baseName.'.'.$target,
                'file_path' => 'converted/pending-'.uniqid('', true).'.'.$target,
                'file_size' => 0,
                'mime_type' => 'application/octet-stream',
                'pages' => 0,
                'status' => Document::STATUS_PROCESSING,
                'operation_type' => Document::OP_CONVERTED,
                'parent_document_id' => $source->id,
                'metadata' => ['target' => $target],
            ]);

            ProcessPdfConvertJob::dispatch($document->id, $source->id, $target, $password);
            $document->refresh();

            if ($document->status === Document::STATUS_FAILED) {
                return back()->withErrors(['convert' => 'Could not convert this PDF. Please try again.']);
            }

            if ($document->status === Document::STATUS_COMPLETED && DocumentsDisk::disk()->exists($document->file_path)) {
                return response()->download($document->absolutePath(), $document->original_name);
            }

            return redirect()->route('pdf.show', $document)
                ->with('success', 'Conversion queued. Refresh this page shortly, then download from the library.');
        } catch (Throwable $e) {
            Log::error('PDF conversion failed', [
                'document_id' => $source->id,
                'target' => $target,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['convert' => SafeUserMessage::from($e, 'Could not convert this PDF')]);
        }
    }
}
