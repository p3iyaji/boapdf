@extends('layouts.app')

@section('title', 'Convert PDF - '.config('app.name'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Convert PDF</h1>
    <p class="mt-1 text-gray-600">
        Export a PDF to an editable Word or HTML document, or render every page as a
        print-quality image.
    </p>
</div>

@if ($documents->isEmpty())
    <div class="rounded-xl bg-white p-12 text-center shadow">
        <p class="text-gray-500">
            You don't have any PDFs yet.
            <a href="{{ route('pdf.index') }}" class="text-blue-600 hover:underline">Upload one first</a>.
        </p>
    </div>
@else
    <form method="POST" action="{{ route('pdf.convert.store') }}"
          x-data="convertForm({ docs: @js($convertDocuments) })"
          class="space-y-5">
        @csrf

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
            <h2 class="mb-2 text-sm font-semibold text-gray-800">2. Target format</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-7">
                @foreach ($targets as $format)
                    <label class="flex cursor-pointer flex-col items-center rounded-lg border p-3 transition hover:bg-blue-50 has-checked:border-blue-500 has-checked:bg-blue-50">
                        <span class="font-semibold uppercase text-gray-800">{{ $format }}</span>
                        <span class="mt-1 text-xs text-gray-500">
                            @switch($format)
                                @case('html') Web page @break
                                @case('docx') Word doc @break
                                @case('doc') Legacy Word @break
                                @case('jpg') JPEG image @break
                                @case('jpeg') JPEG image @break
                                @case('png') PNG image @break
                                @case('txt') Plain text @break
                            @endswitch
                        </span>
                        <input type="radio" name="target" value="{{ $format }}" x-model="target" class="sr-only" required>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500"
               x-show="target === 'jpg' || target === 'jpeg' || target === 'png'"
               x-cloak>
                Pages are rendered at 300 DPI. Multi-page PDFs download as a ZIP containing one image per page.
            </p>
            <p class="mt-2 text-xs text-gray-500"
               x-show="target === 'docx' || target === 'doc' || target === 'html'"
               x-cloak>
                Scanned pages are OCR processed before editable document reconstruction.
            </p>
            @error('target')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-xl bg-white p-5 shadow sm:p-6">
            <label for="password" class="mb-1 block text-sm font-medium text-gray-700">
                PDF password <span class="font-normal text-gray-500">(only if encrypted)</span>
            </label>
            <input id="password" name="password" type="password" autocomplete="off" maxlength="1024"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   aria-describedby="password-help">
            <p id="password-help" class="mt-1 text-xs text-gray-500">
                The password is used only during this conversion and is never stored.
            </p>
        </div>

        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <p class="min-w-0 text-sm text-gray-500" x-show="!selectedDoc">Select a PDF above to continue.</p>
            <p class="min-w-0 text-sm font-medium text-gray-800" x-show="selectedDoc">
                Converting
                <span class="font-semibold" x-text="selectedDoc?.name"></span>
            </p>
            <button type="submit"
                    :disabled="!selectedId || !target"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                Convert &amp; download
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function convertForm({ docs }) {
            return {
                docs,
                query: '',
                selectedId: null,
                target: '',

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
            };
        }
    </script>
    @endpush
@endif
@endsection
