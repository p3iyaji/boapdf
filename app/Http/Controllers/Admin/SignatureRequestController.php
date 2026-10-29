<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SignatureRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SignatureRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = SignatureRequest::query()
            ->with(['document.user', 'sourceDocument'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('requester_email', 'like', $term)
                        ->orWhere('signer_email', 'like', $term)
                        ->orWhere('signer_name', 'like', $term);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.signature-requests.index', [
            'requests' => $requests,
            'search' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function show(SignatureRequest $signatureRequest): View
    {
        $signatureRequest->load(['document.user', 'sourceDocument']);

        return view('admin.signature-requests.show', [
            'signatureRequest' => $signatureRequest,
        ]);
    }

    public function destroy(SignatureRequest $signatureRequest): RedirectResponse
    {
        $signatureRequest->delete();

        return redirect()
            ->route('admin.signature-requests.index')
            ->with('success', 'Signature request deleted.');
    }
}
