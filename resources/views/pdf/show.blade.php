@extends('layouts.app')

@section('title', $document->original_name)

@section('content')
<div class="mb-4 flex min-w-0 max-w-full flex-col gap-4 sm:mb-6 md:flex-row md:items-start md:justify-between md:gap-6">
    <div class="min-w-0 flex-1">
        <h1 class="break-words text-xl font-bold text-gray-800 sm:text-2xl md:text-3xl">{{ $document->original_name }}</h1>
        <p class="mt-1 text-xs leading-relaxed text-gray-500 sm:text-sm">
            {{ ucfirst($document->operation_type) }} &middot; {{ $document->pages }} pages &middot; {{ $document->human_file_size }} &middot; {{ $document->created_at->diffForHumans() }}
        </p>
    </div>
    <div class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end md:w-auto md:max-w-none md:shrink-0">
        @if ($document->isFileReady())
            <a href="{{ route('pdf.download', $document) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 active:bg-gray-100">Download</a>
        @endif
        @if ($document->status === \App\Models\Document::STATUS_COMPLETED && $document->mime_type === 'application/pdf')
            <a href="{{ route('pdf.compress.create', ['document' => $document->id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-accent-300 bg-accent-50 px-4 text-sm font-medium text-accent-900 hover:bg-accent-100 active:bg-accent-200">Compress</a>
        @endif
        @if ($document->isFileReady() && $document->mime_type === 'application/pdf')
            <a href="{{ route('pdf.edit.create', $document) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-blue-300 bg-blue-50 px-4 text-sm font-medium text-blue-900 hover:bg-blue-100 active:bg-blue-200">Edit / fill</a>
            <a href="{{ route('pdf.sign.create', $document->operation_type === \App\Models\Document::OP_SIGNED && $document->parent_document_id ? $document->parent_document_id : $document) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 active:bg-emerald-800">Sign / invite</a>
        @endif
        <a href="{{ route('pdf.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 active:bg-blue-800">Back to library</a>
    </div>
</div>

@php
    $compressionOriginal = (int) ($document->metadata['original_size'] ?? 0);
    $compressionNew = (int) ($document->metadata['new_size'] ?? 0);
    $showCompressionSavings = $document->operation_type === \App\Models\Document::OP_COMPRESSED
        && $compressionOriginal > 0
        && $compressionNew > 0;
    $compressionSaved = $showCompressionSavings ? max(0, $compressionOriginal - $compressionNew) : 0;
    $compressionPercent = $showCompressionSavings && $compressionOriginal > 0
        ? (int) round((1 - ($compressionNew / $compressionOriginal)) * 100)
        : 0;
@endphp

@if ($showCompressionSavings)
    <div class="mb-4 rounded-xl border border-accent-200/80 bg-accent-50/90 p-4 shadow-sm sm:mb-6 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Compression result</h2>
                <p class="mt-0.5 text-xs text-gray-600">
                    @if ($compressionPercent > 0)
                        This file is {{ $compressionPercent }}% smaller than the original.
                    @else
                        Size barely changed — the original was already well optimized.
                    @endif
                    @if (! empty($document->metadata['level']))
                        · Level: {{ ucfirst((string) $document->metadata['level']) }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <div class="rounded-lg bg-white/80 px-3 py-2 text-center shadow-sm">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500">Before</p>
                    <p class="font-semibold tabular-nums text-gray-800">{{ \Illuminate\Support\Number::fileSize($compressionOriginal) }}</p>
                </div>
                <span class="hidden text-gray-400 sm:inline" aria-hidden="true">&rarr;</span>
                <div class="rounded-lg bg-white/80 px-3 py-2 text-center shadow-sm">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500">After</p>
                    <p class="font-semibold tabular-nums text-gray-800">{{ \Illuminate\Support\Number::fileSize($compressionNew) }}</p>
                </div>
                @if ($compressionSaved > 0)
                    <div class="rounded-lg bg-emerald-600 px-3 py-2 text-center text-white shadow-sm">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-100">Saved</p>
                        <p class="font-semibold tabular-nums">{{ \Illuminate\Support\Number::fileSize($compressionSaved) }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

@if (($signatureRequests ?? collect())->isNotEmpty())
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:mb-6 sm:p-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-gray-800">Signature requests</h2>
            <a href="{{ route('pdf.sign.create', $envelopeDocumentId ?? $document) }}?tab=invite" class="text-sm font-medium text-emerald-700 hover:text-emerald-900">Manage invites</a>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($signatureRequests as $req)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-800">{{ $req->signer_name ?: $req->signer_email }}</p>
                        @if ($req->signer_name)
                            <p class="truncate text-xs text-gray-500">{{ $req->signer_email }}</p>
                        @endif
                    </div>
                    @if ($req->status === \App\Models\SignatureRequest::STATUS_SIGNED)
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                            Signed{{ $req->signed_at ? ' · '.$req->signed_at->diffForHumans() : '' }}
                        </span>
                    @elseif ($req->isExpired())
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Expired</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Waiting</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if (! $document->isFileReady())
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center shadow-sm"
         @if (in_array($document->status, [\App\Models\Document::STATUS_PROCESSING, \App\Models\Document::STATUS_PENDING], true))
             x-data
             x-init="setTimeout(() => window.location.reload(), 3000)"
         @endif>
        @if (in_array($document->status, [\App\Models\Document::STATUS_PROCESSING, \App\Models\Document::STATUS_PENDING], true))
            <p class="text-base font-semibold text-amber-950">Still processing</p>
            <p class="mt-2 text-sm text-amber-900/80">This PDF is being prepared. This page will refresh automatically.</p>
            <a href="{{ route('pdf.show', $document) }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-amber-800 px-4 text-sm font-semibold text-white hover:bg-amber-900">Refresh now</a>
        @elseif ($document->status === \App\Models\Document::STATUS_FAILED)
            <p class="text-base font-semibold text-red-900">Processing failed</p>
            <p class="mt-2 text-sm text-red-800/80">{{ $document->metadata['error'] ?? 'Something went wrong while preparing this file.' }}</p>
            <a href="{{ route('pdf.index') }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-red-800 px-4 text-sm font-semibold text-white hover:bg-red-900">Back to library</a>
        @else
            <p class="text-base font-semibold text-gray-900">File unavailable</p>
            <p class="mt-2 text-sm text-gray-600">The file for this document could not be found.</p>
        @endif
    </div>
@else
<div class="min-w-0 max-w-full rounded-xl bg-white p-3 shadow sm:p-4"
     x-data="pdfViewer({
        url: @js(route('pdf.stream', $document)),
        totalPages: {{ (int) $document->pages }},
     })"
     x-init="init()">
    <div class="mb-3 flex flex-col gap-3 rounded-lg bg-gray-50 p-3 sm:mb-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
        <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
            <button type="button" @click="prev()" :disabled="page <= 1 || loading"
                    class="inline-flex min-h-10 min-w-[2.75rem] items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium disabled:opacity-50">&larr; Prev</button>
            <span class="flex flex-wrap items-center justify-center gap-1.5 text-sm text-gray-700">
                <span class="hidden sm:inline">Page</span>
                <input type="number" min="1" :max="totalPages" x-model.number="page" @change="render()"
                            :disabled="loading"
                            class="h-10 w-14 rounded-lg border border-gray-300 px-2 text-center text-sm tabular-nums disabled:opacity-50 sm:w-16"
                            inputmode="numeric" aria-label="Current page">
                <span class="tabular-nums">/ <span x-text="totalPages"></span></span>
            </span>
            <button type="button" @click="next()" :disabled="page >= totalPages || loading"
                    class="inline-flex min-h-10 min-w-[2.75rem] items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium disabled:opacity-50">Next &rarr;</button>
        </div>
        <div class="flex min-w-0 flex-col gap-2 sm:max-w-md sm:flex-1 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
            <div class="flex min-w-0 flex-1 items-center gap-2 sm:max-w-xs">
                <label class="shrink-0 text-sm text-gray-700" for="pdf-zoom">Zoom</label>
                <input id="pdf-zoom" type="range" min="50" max="300" step="5" x-model.number="zoom" @input="render()" :disabled="loading" class="min-w-0 flex-1 touch-pan-y disabled:opacity-50">
                <span class="w-12 shrink-0 text-right text-sm tabular-nums text-gray-700 sm:w-14" x-text="zoom + '%'"></span>
            </div>
            <a href="{{ route('pdf.stream', $document) }}" target="_blank" rel="noopener"
               class="inline-flex min-h-10 items-center justify-center text-center text-sm font-medium text-blue-600 hover:text-blue-800">Open in new tab</a>
        </div>
    </div>

    <p x-show="error" x-cloak class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></p>

    <div x-show="loading" x-cloak class="flex min-h-[40vh] items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 text-gray-600 sm:min-h-[50vh]">
        Loading PDF&hellip;
    </div>

    <div x-show="!loading && !error"
         class="min-h-[min(50dvh,520px)] w-full min-w-0 max-w-full overflow-x-auto overflow-y-auto overscroll-contain rounded-lg border border-gray-200 bg-gray-100 p-2 max-h-[min(75dvh,900px)] sm:min-h-[min(55dvh,640px)] sm:max-h-[min(85dvh,1200px)] sm:p-4 md:p-6">
        {{-- Inner sizes to the canvas so wide pages scroll inside this box instead of clipping against main --}}
        <div class="mx-auto w-max">
            <canvas x-ref="canvas" class="block max-w-none touch-manipulation shadow"></canvas>
        </div>
    </div>
</div>
@endif
@endsection

@if ($document->isFileReady())
@push('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
@endpush

@push('scripts')
<script>
    function pdfViewer({ url, totalPages }) {
        /** PDF.js instances use private fields; Alpine's Proxy breaks them — keep outside reactive state. */
        let pdfDoc = null;
        let renderTask = null;

        const narrow = typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches;

        return {
            url,
            totalPages,
            page: 1,
            zoom: narrow ? 100 : 125,
            loading: true,
            error: null,

            async init() {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                this.loading = true;
                this.error = null;
                try {
                    const response = await fetch(this.url, { credentials: 'same-origin' });
                    if (!response.ok) {
                        throw new Error('Could not load this PDF (' + response.status + '). Try “Open in new tab”.');
                    }
                    const buffer = await response.arrayBuffer();
                    pdfDoc = await window.pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                    this.totalPages = pdfDoc.numPages;
                    await this.render();
                } catch (e) {
                    this.error = e instanceof Error ? e.message : 'Failed to load PDF.';
                    console.error(e);
                } finally {
                    this.loading = false;
                }
            },

            async render() {
                if (!pdfDoc) return;
                if (this.page < 1) this.page = 1;
                if (this.page > this.totalPages) this.page = this.totalPages;

                const page = await pdfDoc.getPage(this.page);
                const canvas = this.$refs.canvas;
                const ctx = canvas.getContext('2d');
                const outputScale = window.devicePixelRatio || 1;
                const baseScale = this.zoom / 100;
                const viewport = page.getViewport({ scale: baseScale * outputScale });
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.style.width = Math.floor(viewport.width / outputScale) + 'px';
                canvas.style.height = Math.floor(viewport.height / outputScale) + 'px';

                if (renderTask) { renderTask.cancel(); }
                renderTask = page.render({ canvasContext: ctx, viewport });
                try { await renderTask.promise; } catch (e) { /* cancelled */ }
            },

            prev() { if (this.page > 1) { this.page--; this.render(); } },
            next() { if (this.page < this.totalPages) { this.page++; this.render(); } },
        };
    }
</script>
@endpush
@endif
