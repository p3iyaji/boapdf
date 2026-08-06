@extends('layouts.app')

@section('title', 'Compress PDF - '.config('app.name'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Compress PDF</h1>
    <p class="mt-1 text-gray-600">Shrink a PDF for email or sharing. Pick a file, choose how much quality to trade for size, then compress.</p>
</div>

@if (count($compressDocuments) === 0)
    <div class="rounded-xl bg-white p-12 text-center shadow">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-accent-100 text-accent-600">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
            </svg>
        </div>
        <p class="font-medium text-gray-800">No PDFs to compress yet</p>
        <p class="mt-1 text-sm text-gray-500">Upload a PDF to your library, then come back here to shrink it.</p>
        <a href="{{ route('pdf.index') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700">Upload a PDF</a>
    </div>
@else
    <form method="POST" action="{{ route('pdf.compress.store') }}"
          x-data="compressForm({
              docs: @js($compressDocuments),
              levels: @js($levelOptions),
              selectedId: @js($selectedId),
              defaultLevel: @js($default),
          })"
          @submit="submitting = true"
          class="space-y-5">
        @csrf

        @error('compress')
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror

        <div class="rounded-xl bg-white p-5 shadow sm:p-6">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">1. Choose a PDF</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Click a file to select it.</p>
                </div>
                <label class="sr-only" for="compress-search">Search PDFs</label>
                <input id="compress-search" type="search" x-model="query" placeholder="Search by name&hellip;"
                       class="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 sm:w-56">
            </div>

            <input type="hidden" name="document_id" :value="selectedId || ''" required>

            <div class="max-h-64 space-y-2 overflow-y-auto pr-1" role="listbox" aria-label="PDF documents">
                <template x-for="d in filteredDocs" :key="d.id">
                    <button type="button"
                            role="option"
                            :aria-selected="selectedId === d.id"
                            @click="selectedId = d.id"
                            class="flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-3 text-left transition"
                            :class="selectedId === d.id
                                ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-gray-800" x-text="d.name"></p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                <span x-text="d.pages"></span> pages · <span x-text="d.size"></span>
                            </p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold"
                              :class="selectedId === d.id ? 'text-blue-700' : 'text-gray-400'"
                              x-text="selectedId === d.id ? 'Selected' : 'Select'"></span>
                    </button>
                </template>
                <p class="rounded-lg border border-dashed border-gray-200 py-6 text-center text-sm text-gray-500"
                   x-show="filteredDocs.length === 0">
                    No PDFs match your search.
                </p>
            </div>
            @error('document_id')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-xl bg-white p-5 shadow sm:p-6">
            <h2 class="text-sm font-semibold text-gray-800">2. Compression level</h2>
            <p class="mt-0.5 mb-4 text-xs text-gray-500">Higher compression means a smaller file and slightly lower image quality.</p>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <template x-for="lvl in levels" :key="lvl.value">
                    <label class="relative flex cursor-pointer flex-col rounded-xl border p-4 transition"
                           :class="level === lvl.value
                               ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                               : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                        <input type="radio" name="level" :value="lvl.value" x-model="level" class="sr-only">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-gray-800" x-text="lvl.label"></span>
                            <span x-show="lvl.value === defaultLevel && level === lvl.value"
                                  class="rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                Default
                            </span>
                        </div>
                        <span class="mt-1 text-xs leading-relaxed text-gray-500" x-text="lvl.hint"></span>
                        <div class="mt-3" aria-hidden="true">
                            <div class="mb-1 flex justify-between text-[10px] font-medium uppercase tracking-wide text-gray-400">
                                <span>Quality</span>
                                <span>Size</span>
                            </div>
                            <div class="flex h-1.5 overflow-hidden rounded-full bg-gray-200">
                                <div class="bg-emerald-500 transition-all" :style="'width:' + lvl.qualityPct + '%'"></div>
                                <div class="bg-accent-500 transition-all" :style="'width:' + lvl.sizePct + '%'"></div>
                            </div>
                        </div>
                    </label>
                </template>
            </div>
            @error('level')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800" x-show="selectedDoc">
                    Compressing
                    <span class="font-semibold" x-text="selectedDoc?.name"></span>
                    <span class="font-normal text-gray-500">(<span x-text="selectedDoc?.size"></span>)</span>
                </p>
                <p class="text-sm text-gray-500" x-show="!selectedDoc">Select a PDF above to continue.</p>
                <p class="mt-0.5 text-xs text-gray-500" x-show="selectedDoc">
                    Level: <span class="font-medium text-gray-700" x-text="selectedLevel?.label"></span>
                    · Scans and photo-heavy PDFs shrink the most
                </p>
            </div>
            <button type="submit"
                    :disabled="!selectedId || submitting"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                <svg x-show="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="submitting ? 'Compressing…' : 'Compress PDF'"></span>
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function compressForm({ docs, levels, selectedId, defaultLevel }) {
            const validIds = docs.map((d) => d.id);
            const initial = validIds.includes(selectedId) ? selectedId : null;

            return {
                docs,
                levels,
                query: '',
                selectedId: initial,
                level: defaultLevel,
                defaultLevel,
                submitting: false,

                get filteredDocs() {
                    const q = this.query.trim().toLowerCase();
                    if (!q) {
                        return this.docs;
                    }
                    return this.docs.filter((d) => d.name.toLowerCase().includes(q));
                },

                get selectedDoc() {
                    return this.docs.find((d) => d.id === this.selectedId) || null;
                },

                get selectedLevel() {
                    return this.levels.find((l) => l.value === this.level) || null;
                },
            };
        }
    </script>
    @endpush
@endif
@endsection
