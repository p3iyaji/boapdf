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
        <a href="{{ route('pdf.download', $document) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 active:bg-gray-100">Download</a>
        <a href="{{ route('pdf.sign.create', $document) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 active:bg-emerald-800">Sign</a>
        <a href="{{ route('pdf.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 active:bg-blue-800">Back to library</a>
    </div>
</div>

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
@endsection

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
