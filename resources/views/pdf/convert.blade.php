@extends('layouts.app')

@section('title', 'Convert PDF - '.config('app.name'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Convert PDF</h1>
    <p class="mt-1 text-gray-600">
        Turn a library PDF into an editable Word file, a web page, plain text, or print-quality images.
    </p>
</div>

@if ($documents->isEmpty())
    <div class="rounded-xl bg-white p-12 text-center shadow">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blue-100 text-blue-600">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 11h8M8 15h5M6 3h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2z"/>
            </svg>
        </div>
        <p class="font-medium text-gray-800">No PDFs to convert yet</p>
        <p class="mt-1 text-sm text-gray-500">Upload a PDF to your library, then come back here to export it.</p>
        <a href="{{ route('pdf.index') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700">Upload a PDF</a>
    </div>
@else
    <form method="POST" action="{{ route('pdf.convert.store') }}"
          x-data="convertForm({
              docs: @js($convertDocuments),
              groups: @js($formatGroups),
              selectedId: @js($selectedId),
              defaultTarget: @js($defaultTarget),
          })"
          @submit="submitting = true"
          class="space-y-5">
        @csrf

        @error('convert')
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror

        <div class="rounded-xl bg-white p-5 shadow sm:p-6">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">1. Choose a PDF</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Click a file to select it.</p>
                </div>
                <label class="sr-only" for="convert-search">Search PDFs</label>
                <input id="convert-search" type="search" x-model="query" placeholder="Search by name&hellip;"
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
            <h2 class="text-sm font-semibold text-gray-800">2. Choose an output format</h2>
            <p class="mt-0.5 mb-4 text-xs text-gray-500">DOCX is recommended for editing. Images are best when you need a visual snapshot of each page.</p>

            <div class="space-y-5">
                <template x-for="group in groups" :key="group.id">
                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="group.label"></h3>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <template x-for="fmt in group.formats" :key="fmt.value">
                                <label class="relative flex cursor-pointer flex-col rounded-xl border p-4 transition"
                                       :class="target === fmt.value
                                           ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                                           : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                                    <input type="radio" name="target" :value="fmt.value" x-model="target" class="sr-only" required>
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="font-semibold text-gray-800" x-text="fmt.label"></span>
                                        <span x-show="fmt.recommended"
                                              class="rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                            Recommended
                                        </span>
                                    </div>
                                    <span class="mt-1 text-xs leading-relaxed text-gray-500" x-text="fmt.hint"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-4 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 text-xs text-gray-600"
                 x-show="selectedFormat"
                 x-cloak>
                <p x-show="isEditableTarget">
                    Text, tables, and bullet lists stay editable. Flowchart or ERD pages may be preserved as images.
                </p>
                <p x-show="isImageTarget">
                    Pages render at 300 DPI. Multi-page PDFs download as a ZIP with one image per page.
                </p>
                <p x-show="target === 'txt'">
                    Layout and images are dropped—only the extractable text is kept.
                </p>
            </div>
            @error('target')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-xl bg-white p-5 shadow sm:p-6" x-data="{ open: {{ old('password') ? 'true' : 'false' }} }">
            <button type="button"
                    class="flex w-full items-center justify-between gap-3 text-left"
                    @click="open = !open"
                    :aria-expanded="open.toString()">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">3. Password <span class="font-normal text-gray-500">(optional)</span></h2>
                    <p class="mt-0.5 text-xs text-gray-500">Only needed if the PDF is encrypted.</p>
                </div>
                <svg class="h-5 w-5 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-4" x-show="open" x-cloak>
                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">PDF password</label>
                <input id="password" name="password" type="password" autocomplete="off" maxlength="1024"
                       value="{{ old('password') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                       aria-describedby="password-help">
                <p id="password-help" class="mt-1 text-xs text-gray-500">
                    Used only for this conversion and never stored.
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800" x-show="selectedDoc">
                    Convert
                    <span class="font-semibold" x-text="selectedDoc?.name"></span>
                    <span class="font-normal text-gray-500">(<span x-text="selectedDoc?.pages"></span> pages · <span x-text="selectedDoc?.size"></span>)</span>
                </p>
                <p class="text-sm text-gray-500" x-show="!selectedDoc">Select a PDF above to continue.</p>
                <p class="mt-0.5 text-xs text-gray-500" x-show="selectedDoc && selectedFormat">
                    Output:
                    <span class="font-medium text-gray-700" x-text="selectedFormat?.label"></span>
                </p>
            </div>
            <button type="submit"
                    :disabled="!selectedId || !target || submitting"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                <svg x-show="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="submitting ? 'Starting…' : 'Start conversion'"></span>
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function convertForm({ docs, groups, selectedId, defaultTarget }) {
            const validIds = docs.map((d) => d.id);
            const flatFormats = groups.flatMap((g) => g.formats);
            const validTargets = flatFormats.map((f) => f.value);
            const initialTarget = validTargets.includes(defaultTarget) ? defaultTarget : 'docx';

            return {
                docs,
                groups,
                query: '',
                selectedId: validIds.includes(selectedId) ? selectedId : null,
                target: initialTarget,
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

                get selectedFormat() {
                    return flatFormats.find((f) => f.value === this.target) || null;
                },

                get isEditableTarget() {
                    return ['docx', 'doc', 'html'].includes(this.target);
                },

                get isImageTarget() {
                    return ['png', 'jpg', 'jpeg'].includes(this.target);
                },
            };
        }
    </script>
    @endpush
@endif
@endsection
