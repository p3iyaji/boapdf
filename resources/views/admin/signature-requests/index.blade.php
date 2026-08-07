@extends('layouts.app')

@section('title', 'Admin signatures - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6">
    <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Signature requests</h1>
    <p class="mt-1 text-sm text-brand-900/70">Pending invites and completed signatures.</p>
</div>

<form method="GET" action="{{ route('admin.signature-requests.index') }}" class="mb-4 flex flex-col gap-3 rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm sm:flex-row sm:items-end">
    <div class="min-w-0 flex-1">
        <label for="q" class="mb-1 block text-xs font-medium text-brand-800/80">Search</label>
        <input id="q" type="search" name="q" value="{{ $search }}" placeholder="Signer or requester email"
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="status" class="mb-1 block text-xs font-medium text-brand-800/80">Status</label>
        <select id="status" name="status" class="rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm">
            <option value="">Any</option>
            <option value="pending" @selected($status === 'pending')>Pending</option>
            <option value="signed" @selected($status === 'signed')>Signed</option>
        </select>
    </div>
    <button type="submit" class="rounded-lg border border-brand-900/15 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-950 hover:bg-brand-100">Filter</button>
</form>

<div class="overflow-x-auto rounded-xl border border-brand-900/10 bg-white/95 shadow-sm">
    <table class="min-w-full divide-y divide-brand-900/10 text-left text-sm">
        <thead class="bg-brand-50/80 text-xs uppercase tracking-wide text-brand-800/70">
            <tr>
                <th class="px-4 py-3 font-semibold">Signer</th>
                <th class="px-4 py-3 font-semibold">Requester</th>
                <th class="px-4 py-3 font-semibold">Document</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold">Created</th>
                <th class="px-4 py-3 font-semibold"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-brand-900/5">
            @forelse ($requests as $signatureRequest)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-medium text-brand-950">{{ $signatureRequest->signer_name ?: '—' }}</div>
                        <div class="text-xs text-brand-800/70">{{ $signatureRequest->signer_email }}</div>
                    </td>
                    <td class="px-4 py-3 text-brand-800/80">{{ $signatureRequest->requester_email }}</td>
                    <td class="px-4 py-3">
                        @if ($signatureRequest->document)
                            <a href="{{ route('admin.documents.show', $signatureRequest->document) }}" class="hover:text-accent-700">{{ $signatureRequest->document->original_name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $signatureRequest->status }}</td>
                    <td class="px-4 py-3 text-brand-800/70">{{ $signatureRequest->created_at?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.signature-requests.show', $signatureRequest) }}" class="text-sm font-semibold text-brand-900 hover:text-accent-700">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-brand-800/70">No signature requests found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $requests->links() }}</div>
@endsection
