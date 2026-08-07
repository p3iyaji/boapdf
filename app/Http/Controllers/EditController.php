<?php

namespace App\Http\Controllers;

use App\Http\Requests\EditPdfRequest;
use App\Http\Requests\FillPdfFormRequest;
use App\Models\Document;
use App\Services\DocumentEditService;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EditController extends Controller
{
    public function __construct(private DocumentEditService $editing) {}

    public function create(Request $request, Document $document): View
    {
        $this->authorize('view', $document);

        if (! $document->isFileReady() || $document->mime_type !== 'application/pdf') {
            abort(404);
        }

        return view('pdf.edit', [
            'document' => $document,
            'tab' => $request->query('tab') === 'form' ? 'form' : 'annotate',
        ]);
    }

    public function store(EditPdfRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        if (! $document->isFileReady() || $document->mime_type !== 'application/pdf') {
            abort(404);
        }

        try {
            $edited = $this->editing->queueEdit(
                $request->user(),
                $document,
                $request->validated('annotations')
            );

            if ($edited->status === Document::STATUS_FAILED) {
                return back()->withErrors(['edit' => 'Could not edit this PDF. Please try again.']);
            }

            $message = $edited->status === Document::STATUS_COMPLETED
                ? 'PDF updated successfully.'
                : 'Edit queued. Refresh this page shortly for the result.';

            return redirect()->route('pdf.show', $edited)->with('success', $message);
        } catch (Throwable $e) {
            Log::error('PDF edit failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['edit' => SafeUserMessage::from($e, 'Could not edit this PDF')]);
        }
    }

    public function storeForm(FillPdfFormRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        if (! $document->isFileReady() || $document->mime_type !== 'application/pdf') {
            abort(404);
        }

        try {
            $filled = $this->editing->queueFormFill(
                $request->user(),
                $document,
                $request->validated('fields')
            );

            if ($filled->status === Document::STATUS_FAILED) {
                return back()->withErrors(['form' => 'Could not fill this PDF form. Please try again.']);
            }

            $message = $filled->status === Document::STATUS_COMPLETED
                ? 'Form filled successfully.'
                : 'Form fill queued. Refresh this page shortly for the result.';

            return redirect()->route('pdf.show', $filled)->with('success', $message);
        } catch (Throwable $e) {
            Log::error('PDF form fill failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['form' => SafeUserMessage::from($e, 'Could not fill this PDF form')]);
        }
    }
}
