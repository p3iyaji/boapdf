<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePdfRequest;
use App\Models\Document;
use App\Services\DocumentEditService;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateController extends Controller
{
    public function __construct(private DocumentEditService $editing) {}

    public function create(): View
    {
        return view('pdf.create');
    }

    public function store(CreatePdfRequest $request): RedirectResponse
    {
        try {
            $document = $this->editing->queueCreate($request->user(), $request->validated());

            if ($document->status === Document::STATUS_FAILED) {
                return back()->withErrors(['create' => 'Could not create this PDF. Please try again.']);
            }

            $message = $document->status === Document::STATUS_COMPLETED
                ? 'PDF created successfully.'
                : 'PDF creation queued. Refresh this page shortly for the result.';

            return redirect()->route('pdf.show', $document)->with('success', $message);
        } catch (Throwable $e) {
            Log::error('PDF create failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['create' => SafeUserMessage::from($e, 'Could not create this PDF')]);
        }
    }
}
