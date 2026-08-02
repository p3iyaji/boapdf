@extends('layouts.app')

@section('title', 'Merge PDFs - '.config('app.name'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Merge PDFs</h1>
    <p class="text-gray-600 mt-1">Choose PDFs to include, then drag them in the merge list or use the arrows until the order is correct.</p>
</div>

@if (count($mergeDocuments) < 2)
    <div class="bg-white rounded-xl shadow p-12 text-center">
        <p class="text-gray-500">You need at least two PDFs to merge. <a href="{{ route('pdf.index') }}" class="text-blue-600 hover:underline">Upload some first</a>.</p>
    </div>
@else
    <form method="POST" action="{{ route('pdf.merge.store') }}"
          x-data="mergeForm({ docs: @js($mergeDocuments) })"
          class="bg-white rounded-xl shadow p-6 space-y-6">
        @csrf

        <div>
            <h2 class="text-sm font-semibold text-gray-800">Include in merge</h2>
            <p class="text-xs text-gray-500 mt-1 mb-3">Click a row to add or remove it from the list below.</p>
            <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                <template x-for="d in docs" :key="d.id">
                    <button type="button"
                            @click="toggle(d.id)"
                            class="flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left transition"
                            :class="inOrder(d.id) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                        <div class="min-w-0 flex-1 pr-2">
                            <p class="font-medium text-gray-800 truncate" x-text="d.name"></p>
                            <p class="text-xs text-gray-500"><span x-text="d.pages"></span> pages · <span x-text="d.size"></span></p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold"
                              :class="inOrder(d.id) ? 'text-blue-700' : 'text-gray-400'"
                              x-text="inOrder(d.id) ? ('#' + (order.indexOf(d.id) + 1)) : 'Add'"></span>
                    </button>
                </template>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-gray-800">Merge order</h2>
            <p class="text-xs text-gray-500 mt-1 mb-3">Drag by the grip or use ↑ ↓. The first file becomes the start of the combined PDF.</p>

            <template x-for="id in order" :key="'doc-input-' + id">
                <input type="hidden" name="documents[]" :value="id">
            </template>

            <ul class="space-y-2 min-h-[3rem]" x-show="order.length > 0" x-cloak>
                <template x-for="(id, index) in order" :key="'row-' + id">
                    <li draggable="true"
                        @dragstart="onDragStart(id, $event)"
                        @dragover="onDragOver($event)"
                        @drop="onDrop(id, $event)"
                        @dragend="onDragEnd()"
                        class="flex items-center gap-2 rounded-lg border px-2 py-2 transition sm:gap-3 sm:px-3"
                        :class="dragFrom === id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50'">
                        <span class="cursor-grab touch-none select-none text-gray-400 hover:text-gray-600" title="Drag to reorder" aria-hidden="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4h2v2H7V4zm6 0h2v2h-2V4zM7 9h2v2H7V9zm6 0h2v2h-2V9zM7 14h2v2H7v-2zm6 0h2v2h-2v-2z"/></svg>
                        </span>
                        <span class="w-6 shrink-0 text-sm font-semibold tabular-nums text-blue-700" x-text="index + 1"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800" x-text="docById(id).name"></p>
                            <p class="text-xs text-gray-500">
                                <span x-text="docById(id).pages"></span> pages · <span x-text="docById(id).size"></span>
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col gap-0.5 sm:flex-row sm:gap-1">
                            <button type="button"
                                    @click="moveUp(id)"
                                    :disabled="index === 0"
                                    class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    title="Move up">↑</button>
                            <button type="button"
                                    @click="moveDown(id)"
                                    :disabled="index === order.length - 1"
                                    class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    title="Move down">↓</button>
                        </div>
                    </li>
                </template>
            </ul>
            <p class="text-sm text-gray-500 py-4 text-center border border-dashed border-gray-200 rounded-lg" x-show="order.length === 0">
                Select PDFs from the list above.
            </p>
        </div>

        <div>
            <label for="output_name" class="block text-sm font-medium text-gray-700 mb-1">Output filename (optional)</label>
            <input type="text" id="output_name" name="output_name" placeholder="my-combined-document"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
            <p class="text-sm text-gray-500">
                <span x-text="order.length"></span> in merge order
                <span class="text-gray-400">(min. 2)</span>
            </p>
            <button type="submit" :disabled="order.length < 2"
                    class="px-5 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Merge PDFs
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function mergeForm({ docs }) {
            return {
                docs,
                order: [],
                dragFrom: null,

                inOrder(id) {
                    return this.order.includes(id);
                },

                docById(id) {
                    return this.docs.find((d) => d.id === id) || { id, name: '', pages: 0, size: '' };
                },

                toggle(id) {
                    const i = this.order.indexOf(id);
                    if (i === -1) {
                        this.order.push(id);
                    } else {
                        this.order.splice(i, 1);
                    }
                },

                moveUp(id) {
                    const i = this.order.indexOf(id);
                    if (i <= 0) {
                        return;
                    }
                    const curr = this.order[i];
                    const prev = this.order[i - 1];
                    this.order.splice(i - 1, 2, curr, prev);
                },

                moveDown(id) {
                    const i = this.order.indexOf(id);
                    if (i === -1 || i >= this.order.length - 1) {
                        return;
                    }
                    const curr = this.order[i];
                    const next = this.order[i + 1];
                    this.order.splice(i, 2, next, curr);
                },

                onDragStart(id, event) {
                    this.dragFrom = id;
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(id));
                },

                onDragOver(event) {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                },

                onDrop(targetId, event) {
                    event.preventDefault();
                    const fromId = this.dragFrom;
                    this.dragFrom = null;
                    if (fromId == null || fromId === targetId) {
                        return;
                    }
                    const fromIdx = this.order.indexOf(fromId);
                    if (fromIdx === -1) {
                        return;
                    }
                    this.order.splice(fromIdx, 1);
                    const toIdx = this.order.indexOf(targetId);
                    if (toIdx === -1) {
                        return;
                    }
                    this.order.splice(toIdx, 0, fromId);
                },

                onDragEnd() {
                    this.dragFrom = null;
                },
            };
        }
    </script>
    @endpush
@endif
@endsection
