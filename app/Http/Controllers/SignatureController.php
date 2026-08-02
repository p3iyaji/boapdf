<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignPdfRequest;
use App\Jobs\ProcessPdfSignJob;
use App\Models\Document;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SignatureController extends Controller
{
    public function create(Request $request, Document $document): View
    {
        $this->authorize('view', $document);

        return view('pdf.sign', ['document' => $document]);
    }

    public function store(SignPdfRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $data = $request->validated();

        try {
            $signed = Document::create([
                'user_id' => $request->user()->id,
                'original_name' => pathinfo($document->original_name, PATHINFO_FILENAME).'-signed.pdf',
                'file_path' => 'signed/pending-'.uniqid('', true).'.pdf',
                'file_size' => 0,
                'mime_type' => 'application/pdf',
                'pages' => 0,
                'status' => Document::STATUS_PROCESSING,
                'operation_type' => Document::OP_SIGNED,
                'parent_document_id' => $document->id,
                'metadata' => [],
            ]);

            ProcessPdfSignJob::dispatch($signed->id, $document->id, [
                ...$data,
                'requester_email' => $request->user()->email,
                'signer_email' => $request->user()->email,
            ]);

            $signed->refresh();

            if ($signed->status === Document::STATUS_FAILED) {
                return back()->withErrors(['sign' => 'Could not update PDF. Please try again.']);
            }

            $message = $signed->status === Document::STATUS_COMPLETED
                ? 'PDF signed successfully.'
                : 'Signing queued. Refresh this page shortly for the result.';

            return redirect()->route('pdf.show', $signed)->with('success', $message);
        } catch (Throwable $e) {
            Log::error('PDF signing failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['sign' => SafeUserMessage::from($e, 'Could not update PDF')]);
        }
    }
}
