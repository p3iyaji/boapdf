@extends('layouts.app')

@section('title', 'Signature request - Admin - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Signature request</h1>
        <p class="mt-1 text-sm text-brand-900/70">{{ $signatureRequest->signer_email }}</p>
    </div>
    <form method="POST" action="{{ route('admin.signature-requests.destroy', $signatureRequest) }}" onsubmit="return confirm('Delete this signature request?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-2 text-sm font-semibold text-danger-900 hover:bg-danger-100">Delete</button>
    </form>
</div>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Status</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $signatureRequest->status }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Requester</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $signatureRequest->requester_email }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Signer</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $signatureRequest->signer_name ?: '—' }}</p>
        <p class="text-xs text-brand-800/70">{{ $signatureRequest->signer_email }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Document</p>
        <p class="mt-1 font-semibold text-brand-950">
            @if ($signatureRequest->document)
                <a href="{{ route('admin.documents.show', $signatureRequest->document) }}" class="hover:text-accent-700">{{ $signatureRequest->document->original_name }}</a>
            @else
                —
            @endif
        </p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Expires</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $signatureRequest->expires_at?->toDayDateTimeString() ?: '—' }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Signed at</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $signatureRequest->signed_at?->toDayDateTimeString() ?: '—' }}</p>
    </div>
</div>
@endsection
