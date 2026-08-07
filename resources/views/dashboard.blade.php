@extends('layouts.app')

@section('title', 'Dashboard - '.config('app.name'))

@section('content')
<div class="mb-6 md:mb-8">
    <h1 class="font-display text-balance text-2xl font-bold tracking-tight text-brand-950 sm:text-4xl">Welcome back, {{ Auth::user()->name }}.</h1>
    <p class="mt-2 text-sm leading-relaxed text-brand-900/70 sm:text-base">Pick a tool below or open your library of PDFs.</p>
</div>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:mb-8 lg:grid-cols-4">
    <a href="{{ route('pdf.index') }}" class="block rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-md shadow-brand-950/5 ring-1 ring-brand-900/5 transition hover:border-accent-400/40 hover:shadow-lg sm:p-5">
        <p class="text-xs text-brand-800/70 sm:text-sm">Total documents</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-brand-950 sm:text-3xl">{{ $stats['total'] }}</p>
    </a>
    <div class="rounded-xl border border-brand-900/10 border-t-accent-500/90 bg-white/95 p-4 shadow-md shadow-brand-950/5 sm:p-5">
        <p class="text-xs text-brand-800/70 sm:text-sm">Merged</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-brand-950 sm:text-3xl">{{ $stats['merged'] }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 border-t-accent-500/90 bg-white/95 p-4 shadow-md shadow-brand-950/5 sm:p-5">
        <p class="text-xs text-brand-800/70 sm:text-sm">Compressed</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-brand-950 sm:text-3xl">{{ $stats['compressed'] }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 border-t-accent-500/90 bg-white/95 p-4 shadow-md shadow-brand-950/5 sm:p-5">
        <p class="text-xs text-brand-800/70 sm:text-sm">Signed</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-brand-950 sm:text-3xl">{{ $stats['signed'] }}</p>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:mb-8 lg:grid-cols-3">
    <a href="{{ route('pdf.index') }}" class="block rounded-xl bg-white p-5 shadow transition hover:shadow-lg sm:p-6">
        <div class="mb-3 flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
            <h3 class="font-semibold text-gray-800">Upload &amp; view</h3>
        </div>
        <p class="text-sm leading-relaxed text-gray-600">Drop in a PDF, open it in the in-browser viewer, and grab a download link.</p>
    </a>

    <a href="{{ route('pdf.merge.create') }}" class="block rounded-xl bg-white p-5 shadow transition hover:shadow-lg sm:p-6">
        <div class="mb-3 flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7l4-4m0 0l4 4m-4-4v18"></path></svg>
            </div>
            <h3 class="font-semibold text-gray-800">Merge</h3>
        </div>
        <p class="text-sm leading-relaxed text-gray-600">Combine multiple PDFs into a single document, in any order.</p>
    </a>

    <a href="{{ route('pdf.compress.create') }}" class="block rounded-xl bg-white p-5 shadow transition hover:shadow-lg sm:p-6">
        <div class="mb-3 flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
            </div>
            <h3 class="font-semibold text-gray-800">Compress</h3>
        </div>
        <p class="text-sm leading-relaxed text-gray-600">Shrink PDFs for email and sharing — pick a quality level and see how much you save.</p>
    </a>

    <a href="{{ route('pdf.index') }}" class="block rounded-xl bg-white p-5 shadow transition hover:shadow-lg sm:p-6">
        <div class="mb-3 flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
            </div>
            <h3 class="font-semibold text-gray-800">Sign</h3>
        </div>
        <p class="text-sm leading-relaxed text-gray-600">Sign a PDF yourself or invite others by email—each person gets a secure signing link.</p>
    </a>
</div>

<div class="rounded-xl bg-white p-4 shadow sm:p-6">
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
        <h3 class="text-lg font-semibold text-gray-800">Recent documents</h3>
        <a href="{{ route('pdf.index') }}" class="shrink-0 text-sm font-medium text-blue-600 hover:text-blue-700">View all</a>
    </div>
    @if ($recent->isEmpty())
        <p class="text-sm text-gray-500">No PDFs yet. <a class="font-medium text-blue-600 hover:underline" href="{{ route('pdf.index') }}">Upload your first one</a>.</p>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($recent as $doc)
                <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('pdf.show', $doc) }}" class="block truncate font-medium text-gray-800 hover:text-blue-600">{{ $doc->original_name }}</a>
                        <p class="mt-0.5 text-xs leading-relaxed text-gray-500">
                            {{ ucfirst($doc->operation_type) }} &middot; {{ $doc->pages }} pages &middot; {{ $doc->human_file_size }} &middot; {{ $doc->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <a href="{{ route('pdf.show', $doc) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 px-3 text-sm font-medium text-blue-700 hover:bg-blue-100 sm:border-0 sm:bg-transparent sm:px-0 sm:text-blue-600 sm:hover:bg-transparent sm:hover:text-blue-700">Open</a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
