<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompressPdfRequest;
use App\Jobs\ProcessPdfCompressJob;
use App\Models\Document;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CompressController extends Controller
{
    public function create(Request $request): View
    {
        $documents = Document::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf')
            ->latest()
            ->get();

        return view('pdf.compress', [
            'documents' => $documents,
            'levels' => config('pdf.compression_levels'),
            'default' => config('pdf.default_compression'),
        ]);
    }

    public function store(CompressPdfRequest $request): RedirectResponse
    {
        /** @var Document $source */
        $source = Document::query()
            ->where('id', $request->integer('document_id'))
            ->where('user_id', $request->user()->id)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf')
            ->firstOrFail();

        $level = $request->input('level', config('pdf.default_compression'));

        try {
            $document = Document::create([
                'user_id' => $request->user()->id,
                'original_name' => pathinfo($source->original_name, PATHINFO_FILENAME).'-compressed.pdf',
                'file_path' => 'compressed/pending-'.uniqid('', true).'.pdf',
                'file_size' => 0,
                'mime_type' => 'application/pdf',
                'pages' => 0,
                'status' => Document::STATUS_PROCESSING,
                'operation_type' => Document::OP_COMPRESSED,
                'parent_document_id' => $source->id,
                'metadata' => ['level' => $level],
            ]);

            ProcessPdfCompressJob::dispatch($document->id, $source->id, $level);
            $document->refresh();

            if ($document->status === Document::STATUS_FAILED) {
                return back()->withErrors(['compress' => 'Could not compress this PDF. Please try again.']);
            }

            $message = $document->status === Document::STATUS_COMPLETED
                ? 'PDF compressed successfully.'
                : 'Compression queued. Refresh this page shortly for the result.';

            return redirect()->route('pdf.show', $document)->with('success', $message);
        } catch (Throwable $e) {
            Log::error('PDF compression failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['compress' => SafeUserMessage::from($e, 'Could not compress this PDF')]);
        }
    }
}
