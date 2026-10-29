@extends('layouts.app')

@section('title', 'Edit - '.$document->original_name)

@section('content')
<div
    x-data="pdfEditor({
        streamUrl: @js(route('pdf.stream', $document)),
        submitUrl: @js(route('pdf.edit.store', $document)),
        pagesUrl: @js(route('pdf.edit.pages', $document)),
        csrf: @js(csrf_token()),
        totalPages: {{ (int) $document->pages }},
    })"
    x-init="init()"
>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Edit PDF</h1>
            <p class="mt-1 truncate text-sm text-gray-500">{{ $document->original_name }}</p>
            <p class="mt-2 max-w-2xl text-sm text-gray-600">Select a sentence to replace it, or add a highlight, cover, or note on the page. Page order saves separately. The original file stays in your library.</p>
        </div>
        <a href="{{ route('pdf.show', $document) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Back to document</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <template x-for="item in tools" :key="item.id">
            <button type="button" @click="tool = item.id"
                    class="min-h-10 rounded-lg border px-3 text-sm font-medium"
                    :class="tool === item.id ? 'border-blue-600 bg-blue-50 text-blue-900' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                    :aria-pressed="tool === item.id"
                    x-text="item.label"></button>
        </template>
        <div class="ml-auto flex min-w-[12rem] items-center gap-2">
            <label class="text-sm text-gray-600" for="edit-zoom">Zoom</label>
            <input id="edit-zoom" type="range" min="0.6" max="2.2" step="0.1" x-model.number="zoom" @input="render()" class="w-28">
            <button type="button" @click="undo()" :disabled="!history.length" class="min-h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm disabled:opacity-40">Undo</button>
        </div>
    </div>

    <p class="mb-3 text-sm text-gray-600" x-text="hint()"></p>
    <p x-show="error" x-cloak class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></p>
    <p x-show="loading" class="mb-3 text-sm text-gray-500">Opening PDF…</p>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[11rem_minmax(0,1fr)_18rem]">
        <aside class="rounded-xl bg-white p-3 shadow">
            <div class="mb-2 flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-800">Pages</h2>
                <button type="button" @click="insertBlank()" class="text-xs font-medium text-blue-700 hover:text-blue-900">Insert blank</button>
            </div>
            <ul class="max-h-[70vh] space-y-2 overflow-auto">
                <template x-for="(item, index) in pagePlan" :key="item.id">
                    <li class="rounded-lg border p-2" :class="!item.blank && item.source === page ? 'border-blue-400 bg-blue-50' : 'border-gray-200'">
                        <button type="button" class="block w-full" @click="showPlanPage(item)">
                            <template x-if="item.thumb">
                                <img :src="item.thumb" alt="" class="mx-auto max-h-28 rounded border border-gray-200 bg-white">
                            </template>
                            <template x-if="!item.thumb">
                                <span class="flex h-20 items-center justify-center rounded border border-dashed border-gray-300 text-xs text-gray-500" x-text="item.blank ? 'Blank' : 'Page ' + item.source"></span>
                            </template>
                        </button>
                        <div class="mt-1 flex items-center justify-between gap-1 text-[11px] text-gray-600">
                            <span x-text="item.blank ? 'Blank' : ('Page ' + item.source + (item.rotate ? ' · ' + item.rotate + '°' : ''))"></span>
                            <span class="flex gap-1">
                                <button type="button" class="rounded px-1 hover:bg-gray-100" @click="movePlan(index, -1)" aria-label="Move page up">↑</button>
                                <button type="button" class="rounded px-1 hover:bg-gray-100" @click="movePlan(index, 1)" aria-label="Move page down">↓</button>
                                <button type="button" class="rounded px-1 hover:bg-gray-100" @click="rotatePlan(index)" aria-label="Rotate page">↻</button>
                                <button type="button" class="rounded px-1 text-red-700 hover:bg-red-50" @click="removePlan(index)" aria-label="Remove page">✕</button>
                            </span>
                        </div>
                    </li>
                </template>
            </ul>
            <button type="button" @click="savePages()" :disabled="!pagesDirty() || pagesBlocked() || submitting"
                    class="mt-3 w-full rounded-lg border border-gray-300 bg-white py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:opacity-40">
                Save pages
            </button>
            <p class="mt-1 text-[11px] leading-snug text-gray-500" x-show="pagesBlocked()">Save the marks on the page first. Page changes are a separate file.</p>
        </aside>

        <div class="min-w-0 rounded-xl bg-white p-3 shadow">
            <div class="mb-3 flex items-center justify-center gap-2">
                <button type="button" @click="prev()" :disabled="page <= 1" class="min-h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm disabled:opacity-40">← Prev</button>
                <span class="text-sm text-gray-700">Page <span x-text="page"></span> / <span x-text="totalPages"></span></span>
                <button type="button" @click="next()" :disabled="page >= totalPages" class="min-h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm disabled:opacity-40">Next →</button>
            </div>
            <div x-ref="scroller" class="max-h-[78vh] overflow-auto rounded-lg bg-gray-100 p-4">
                <div class="relative mx-auto w-max" x-ref="stage"
                     @pointerdown="onStagePointerDown($event)"
                     @pointermove="onStagePointerMove($event)"
                     @pointerup="onStagePointerUp($event)">
                    <canvas x-ref="canvas" class="block bg-white shadow"></canvas>
                    <div x-ref="textLayer" class="pdf-text-layer absolute left-0 top-0"
                         :class="tool === 'highlight' || tool === 'replace' ? 'z-30' : 'pointer-events-none'"
                         @mouseup="onTextMouseUp()"></div>
                    <template x-for="mark in marksOnPage()" :key="mark.id">
                        <div data-mark
                             class="absolute border"
                             :class="markClass(mark)"
                             :style="markStyle(mark)"
                             @pointerdown.stop="startMove(mark, $event)"
                             @dblclick.stop="selectedId = mark.id">
                            <template x-if="mark.type === 'text' || mark.type === 'replacement'">
                                <p class="pointer-events-none overflow-hidden px-1 text-xs leading-tight text-gray-900" x-text="mark.text || 'Text'"></p>
                            </template>
                            <template x-if="mark.type === 'image' || mark.type === 'draw'">
                                <img :src="mark.image" alt="" class="pointer-events-none h-full w-full object-contain">
                            </template>
                            <template x-if="mark.type === 'redact'">
                                <span class="pointer-events-none px-1 text-[10px] font-semibold uppercase text-red-800">Redact</span>
                            </template>
                            <button type="button" x-show="tool === 'select' && mark.rect"
                                    class="absolute -bottom-1.5 -right-1.5 h-3 w-3 rounded-sm border border-blue-700 bg-white"
                                    aria-label="Resize"
                                    @pointerdown.stop="startResize(mark, $event)"></button>
                        </div>
                    </template>
                    <template x-for="field in fieldsOnPage()" :key="field.id">
                        <div class="absolute z-40" x-show="tool === 'form'" :style="rectStyle(field.rect)">
                            <template x-if="field.type === 'checkbox'">
                                <input type="checkbox" x-model="field.value" class="h-4 w-4">
                            </template>
                            <template x-if="field.type === 'radio'">
                                <input type="radio" :name="'field-' + field.name" :value="field.buttonValue" :checked="field.value === field.buttonValue" @change="setRadio(field)" class="h-4 w-4">
                            </template>
                            <template x-if="field.type === 'choice'">
                                <select x-model="field.value" class="h-full w-full rounded border border-blue-400 bg-white/90 text-xs">
                                    <template x-for="option in field.options" :key="option.value">
                                        <option :value="option.value" x-text="option.label"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="field.type === 'text'">
                                <input type="text" x-model="field.value" class="h-full w-full rounded border border-blue-400 bg-white/90 px-1 text-xs">
                            </template>
                        </div>
                    </template>
                    <div x-show="dragRect" x-cloak class="pointer-events-none absolute z-40 border-2 border-blue-600 bg-blue-200/30" :style="rectStyle(dragRect)"></div>
                    <canvas x-ref="ink" class="absolute left-0 top-0 z-30 touch-none"
                            :class="tool === 'draw' ? 'cursor-crosshair' : 'pointer-events-none'"
                            @pointerdown.stop="inkDown($event)"
                            @pointermove.stop="inkMove($event)"
                            @pointerup.stop="inkUp()"></canvas>
                </div>
            </div>
        </div>

        <aside class="space-y-4 rounded-xl bg-white p-4 shadow">
            <div x-show="draftReplace" x-cloak class="space-y-2 rounded-lg border border-violet-200 bg-violet-50 p-3">
                <h2 class="text-sm font-semibold text-violet-950">Replace selected text</h2>
                <textarea x-model="draftReplace.text" rows="4" maxlength="500" class="w-full rounded-lg border border-violet-200 px-3 py-2 text-sm"></textarea>
                <div class="flex gap-2">
                    <button type="button" @click="confirmReplace()" class="min-h-10 flex-1 rounded-lg bg-violet-700 text-sm font-semibold text-white hover:bg-violet-800">Use this text</button>
                    <button type="button" @click="draftReplace = null" class="min-h-10 rounded-lg border border-gray-300 px-3 text-sm">Cancel</button>
                </div>
            </div>

            <div x-show="selected()" class="space-y-2">
                <h2 class="text-sm font-semibold text-gray-800" x-text="selected() ? selected().type : ''"></h2>
                <template x-if="selected() && (selected().type === 'text' || selected().type === 'replacement')">
                    <div class="space-y-2">
                        <textarea x-model="selected().text" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                        <label class="flex items-center justify-between text-sm text-gray-700">Size
                            <input type="number" min="6" max="72" x-model.number="selected().size" class="w-20 rounded border border-gray-300 px-2 py-1 text-right">
                        </label>
                        <label class="flex items-center justify-between text-sm text-gray-700" x-show="selected().type === 'text'">Color
                            <input type="color" x-model="selected().color" class="h-9 w-14">
                        </label>
                    </div>
                </template>
                <button type="button" @click="removeMark(selectedId)" class="text-sm font-medium text-red-700">Remove</button>
            </div>

            <div x-show="tool === 'image'" class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Image</label>
                <input type="file" accept="image/png,image/jpeg" @change="loadImage($event)" class="block w-full text-sm">
                <img x-show="imageData" :src="imageData" alt="" class="h-16 max-w-full rounded border object-contain">
            </div>

            <div x-show="tool === 'form'" class="space-y-2">
                <h2 class="text-sm font-semibold text-gray-800">Form fields</h2>
                <p class="text-sm text-gray-600" x-show="!fields.length">No fillable fields were found. Use Text to write on the page.</p>
                <label class="flex items-center gap-2 text-sm text-gray-700" x-show="fields.length">
                    <input type="checkbox" x-model="flatten" class="rounded border-gray-300">
                    Flatten so values print as page content
                </label>
            </div>

            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">On this document</p>
                <ul class="mt-2 max-h-40 space-y-1 overflow-auto text-xs text-gray-600">
                    <template x-for="mark in marks" :key="mark.id">
                        <li class="flex justify-between gap-2">
                            <button type="button" class="truncate text-left hover:text-blue-800" @click="page = mark.page; selectedId = mark.id; render()" x-text="mark.type + ' · p' + mark.page"></button>
                            <button type="button" class="text-red-600" @click="removeMark(mark.id)">Remove</button>
                        </li>
                    </template>
                </ul>
            </div>

            <button type="button" @click="saveEdits()" :disabled="!canSaveEdits() || submitting"
                    class="w-full rounded-lg bg-blue-600 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-40">
                <span x-text="submitting ? statusText : 'Save edits'"></span>
            </button>
        </aside>
    </div>
</div>
@endsection

@push('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
    .pdf-text-layer span {
        position: absolute;
        color: transparent;
        white-space: pre;
        transform-origin: 0 0;
        cursor: text;
    }
    .pdf-text-layer span::selection {
        background: rgba(250, 204, 21, 0.55);
        color: transparent;
    }
</style>
@endpush

@push('scripts')
<script>
    function pdfEditor({ streamUrl, submitUrl, pagesUrl, csrf, totalPages }) {
        let pdfDoc = null;
        let viewport = null;
        let renderTask = null;
        let inkActive = false;

        return {
            streamUrl, submitUrl, pagesUrl, csrf, totalPages,
            page: 1,
            zoom: 1,
            tool: 'select',
            tools: [
                { id: 'select', label: 'Select' },
                { id: 'replace', label: 'Replace' },
                { id: 'highlight', label: 'Highlight' },
                { id: 'text', label: 'Text' },
                { id: 'cover', label: 'Cover' },
                { id: 'redact', label: 'Redact' },
                { id: 'draw', label: 'Draw' },
                { id: 'image', label: 'Image' },
                { id: 'form', label: 'Form' },
            ],
            loading: true,
            submitting: false,
            statusText: 'Saving…',
            error: '',
            marks: [],
            nextId: 1,
            history: [],
            selectedId: null,
            draftReplace: null,
            dragRect: null,
            dragStart: null,
            imageData: '',
            fields: [],
            fieldSnapshot: '[]',
            flatten: false,
            pagePlan: [],
            pagePlanOriginal: '[]',
            nextPlanId: 1,
            textItems: [],
            gesture: null,

            async init() {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                window.addEventListener('keydown', (event) => this.onKey(event));
                try {
                    const response = await fetch(this.streamUrl, { credentials: 'same-origin' });
                    if (!response.ok) {
                        throw new Error('open');
                    }
                    const buffer = await response.arrayBuffer();
                    pdfDoc = await window.pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                    this.totalPages = pdfDoc.numPages;
                    for (let number = 1; number <= pdfDoc.numPages; number++) {
                        const pdfPage = await pdfDoc.getPage(number);
                        this.pagePlan.push({
                            id: number,
                            source: number,
                            rotate: (pdfPage.rotate || 0) % 360,
                            blank: false,
                            thumb: '',
                        });
                    }
                    this.nextPlanId = pdfDoc.numPages + 1;
                    this.pagePlanOriginal = this.planSignature();
                    await this.detectFields();
                    this.fieldSnapshot = JSON.stringify(this.fieldPayload());
                    await this.render();
                    this.renderThumbs();
                } catch (e) {
                    this.error = 'Could not open this PDF.';
                } finally {
                    this.loading = false;
                }
            },

            hint() {
                const hints = {
                    select: 'Click a mark to move it. Drag the corner to resize. Double-click text to edit it.',
                    replace: 'Drag across the sentence, then type the replacement. If it is too long, shorten it before saving.',
                    highlight: 'Drag across the words you want to highlight.',
                    text: 'Click the page to place text, then edit it in the panel.',
                    cover: 'Drag a white box over a mistake. The text underneath can still be copied.',
                    redact: 'Drag over text to delete it from the file so it cannot be copied out.',
                    draw: 'Draw on the page. Lift your pointer to place the drawing.',
                    image: 'Choose an image, then click the page to place it.',
                    form: 'Fill the fields on the page. Values are written into the form.',
                };
                return hints[this.tool] || '';
            },

            async render() {
                if (!pdfDoc) {
                    return;
                }
                if (renderTask) {
                    try { renderTask.cancel(); } catch (e) {}
                }
                const pdfPage = await pdfDoc.getPage(this.page);
                const base = pdfPage.getViewport({ scale: 1 });
                const scroller = this.$refs.scroller;
                const maxWidth = Math.max(280, (scroller?.clientWidth || 800) - 32);
                const scale = Math.min(2.75, Math.max(0.45, (maxWidth / base.width) * this.zoom));
                viewport = pdfPage.getViewport({ scale });
                const canvas = this.$refs.canvas;
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                renderTask = pdfPage.render({ canvasContext: canvas.getContext('2d'), viewport });
                await renderTask.promise.catch(() => {});
                const ink = this.$refs.ink;
                if (ink) {
                    ink.width = viewport.width;
                    ink.height = viewport.height;
                }
                await this.renderTextLayer(pdfPage);
            },

            async renderTextLayer(pdfPage) {
                const layer = this.$refs.textLayer;
                if (!layer) {
                    return;
                }
                layer.style.width = viewport.width + 'px';
                layer.style.height = viewport.height + 'px';
                layer.innerHTML = '';
                const content = await pdfPage.getTextContent();
                this.textItems = [];
                content.items.forEach((item) => {
                    if (!item.str) {
                        return;
                    }
                    const fontSize = Math.hypot(item.transform[2], item.transform[3]) || 12;
                    const x = item.transform[4];
                    const baseline = item.transform[5];
                    const pdfRect = [x, baseline - fontSize * 0.25, x + item.width, baseline + fontSize * 0.85];
                    const index = this.textItems.length;
                    this.textItems.push({ str: item.str, rect: pdfRect, size: fontSize, hasEOL: !!item.hasEOL });
                    const box = this.rectBox(pdfRect);
                    if (!box) {
                        return;
                    }
                    const span = document.createElement('span');
                    span.textContent = item.str;
                    span.dataset.item = String(index);
                    span.style.left = box.left + 'px';
                    span.style.top = box.top + 'px';
                    span.style.width = box.width + 'px';
                    span.style.height = box.height + 'px';
                    span.style.fontSize = box.height + 'px';
                    span.style.lineHeight = box.height + 'px';
                    layer.appendChild(span);
                });
            },

            rectBox(rect) {
                if (!viewport || !rect) {
                    return null;
                }
                const [x0, y0] = viewport.convertToViewportPoint(rect[0], rect[3]);
                const [x1, y1] = viewport.convertToViewportPoint(rect[2], rect[1]);
                return {
                    left: Math.min(x0, x1),
                    top: Math.min(y0, y1),
                    width: Math.max(2, Math.abs(x1 - x0)),
                    height: Math.max(2, Math.abs(y1 - y0)),
                };
            },

            rectStyle(rect) {
                const box = this.rectBox(rect);
                if (!box) {
                    return 'display:none';
                }
                return `left:${box.left}px;top:${box.top}px;width:${box.width}px;height:${box.height}px;`;
            },

            marksOnPage() {
                return this.marks.filter((mark) => mark.page === this.page);
            },

            markStyle(mark) {
                if (mark.rects?.length) {
                    return this.rectStyle(this.union(mark.rects));
                }
                return this.rectStyle(mark.rect);
            },

            markClass(mark) {
                const selected = mark.id === this.selectedId ? 'ring-2 ring-blue-600' : '';
                const tones = {
                    highlight: 'border-amber-400 bg-amber-200/50',
                    replacement: 'border-violet-500 bg-white/80',
                    text: 'border-blue-500 bg-white/80',
                    cover: 'border-gray-400 bg-white',
                    redact: 'border-red-500 bg-white',
                    image: 'border-sky-500 bg-white/40',
                    draw: 'border-emerald-500 bg-transparent',
                };
                return `${tones[mark.type] || 'border-gray-400'} ${selected} ${this.tool === 'select' ? 'z-20 cursor-move' : 'pointer-events-none z-10'}`;
            },

            union(rects) {
                return rects.reduce((box, rect) => [
                    Math.min(box[0], rect[0]),
                    Math.min(box[1], rect[1]),
                    Math.max(box[2], rect[2]),
                    Math.max(box[3], rect[3]),
                ], [...rects[0]]);
            },

            eventToPdf(event) {
                const canvas = this.$refs.canvas;
                const bounds = canvas.getBoundingClientRect();
                const x = (event.clientX - bounds.left) * (canvas.width / bounds.width);
                const y = (event.clientY - bounds.top) * (canvas.height / bounds.height);
                const [pdfX, pdfY] = viewport.convertToPdfPoint(x, y);
                return { x: pdfX, y: pdfY };
            },

            selected() {
                return this.marks.find((mark) => mark.id === this.selectedId) || null;
            },

            remember() {
                this.history.push(JSON.stringify(this.marks));
                if (this.history.length > 40) {
                    this.history.shift();
                }
            },

            undo() {
                if (!this.history.length) {
                    return;
                }
                this.marks = JSON.parse(this.history.pop());
                this.selectedId = null;
            },

            onKey(event) {
                const tag = event.target?.tagName;
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                    return;
                }
                if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'z') {
                    event.preventDefault();
                    this.undo();
                }
                if ((event.key === 'Backspace' || event.key === 'Delete') && this.selectedId) {
                    event.preventDefault();
                    this.removeMark(this.selectedId);
                }
            },

            async prev() {
                if (this.page > 1) {
                    this.page -= 1;
                    await this.render();
                }
            },

            async next() {
                if (this.page < this.totalPages) {
                    this.page += 1;
                    await this.render();
                }
            },

            onTextMouseUp() {
                if (this.tool !== 'highlight' && this.tool !== 'replace') {
                    return;
                }
                const selection = window.getSelection();
                if (!selection || selection.isCollapsed || !selection.rangeCount) {
                    return;
                }
                const range = selection.getRangeAt(0);
                const picked = [];
                this.$refs.textLayer.querySelectorAll('[data-item]').forEach((span) => {
                    try {
                        if (range.intersectsNode(span)) {
                            picked.push(this.textItems[Number(span.dataset.item)]);
                        }
                    } catch (e) {}
                });
                selection.removeAllRanges();
                if (!picked.length) {
                    return;
                }
                if (this.tool === 'highlight') {
                    this.remember();
                    this.marks.push({
                        id: this.nextId++,
                        type: 'highlight',
                        page: this.page,
                        rects: picked.map((item) => item.rect),
                    });
                    return;
                }
                this.draftReplace = {
                    page: this.page,
                    rects: picked.map((item) => item.rect),
                    text: this.joinText(picked),
                    size: picked[0].size || 12,
                };
            },

            joinText(items) {
                let text = '';
                items.forEach((item, index) => {
                    if (index > 0) {
                        const previous = items[index - 1];
                        if (previous.hasEOL) {
                            text += '\n';
                        } else if (item.rect[0] - previous.rect[2] > 1) {
                            text += ' ';
                        }
                    }
                    text += item.str;
                });
                return text;
            },

            confirmReplace() {
                const text = (this.draftReplace?.text || '').trim();
                if (!text) {
                    this.error = 'Type the replacement sentence.';
                    return;
                }
                this.remember();
                this.marks.push({
                    id: this.nextId++,
                    type: 'replacement',
                    page: this.draftReplace.page,
                    rects: this.draftReplace.rects,
                    text,
                    size: this.draftReplace.size,
                });
                this.selectedId = this.nextId - 1;
                this.draftReplace = null;
                this.error = '';
            },

            onStagePointerDown(event) {
                if (!viewport || event.target.closest('[data-mark]') || event.target.closest('input, select, textarea, button')) {
                    return;
                }
                if (this.tool === 'select') {
                    this.selectedId = null;
                    return;
                }
                const point = this.eventToPdf(event);
                if (this.tool === 'text') {
                    this.remember();
                    const mark = {
                        id: this.nextId++,
                        type: 'text',
                        page: this.page,
                        rect: [point.x, point.y - 22, point.x + 180, point.y],
                        text: '',
                        size: 12,
                        color: '#111827',
                    };
                    this.marks.push(mark);
                    this.selectedId = mark.id;
                    return;
                }
                if (this.tool === 'image') {
                    if (!this.imageData) {
                        this.error = 'Choose an image first.';
                        return;
                    }
                    this.remember();
                    this.marks.push({
                        id: this.nextId++,
                        type: 'image',
                        page: this.page,
                        rect: [point.x, point.y - 90, point.x + 140, point.y],
                        image: this.imageData,
                    });
                    this.error = '';
                    return;
                }
                if (this.tool === 'cover' || this.tool === 'redact') {
                    this.dragStart = point;
                    this.dragRect = [point.x, point.y, point.x, point.y];
                }
            },

            onStagePointerMove(event) {
                if (!this.dragStart) {
                    return;
                }
                const point = this.eventToPdf(event);
                this.dragRect = [
                    Math.min(this.dragStart.x, point.x),
                    Math.min(this.dragStart.y, point.y),
                    Math.max(this.dragStart.x, point.x),
                    Math.max(this.dragStart.y, point.y),
                ];
            },

            onStagePointerUp() {
                if (!this.dragRect || !this.dragStart) {
                    this.dragRect = null;
                    this.dragStart = null;
                    return;
                }
                const rect = this.normalize(this.dragRect);
                const wide = rect[2] - rect[0] > 4;
                const tall = rect[3] - rect[1] > 4;
                if (wide && tall && (this.tool === 'cover' || this.tool === 'redact')) {
                    this.remember();
                    this.marks.push({
                        id: this.nextId++,
                        type: this.tool,
                        page: this.page,
                        rect,
                    });
                }
                this.dragRect = null;
                this.dragStart = null;
            },

            normalize(rect) {
                return [
                    Math.min(rect[0], rect[2]),
                    Math.min(rect[1], rect[3]),
                    Math.max(rect[0], rect[2]),
                    Math.max(rect[1], rect[3]),
                ];
            },

            startMove(mark, event) {
                if (this.tool !== 'select') {
                    return;
                }
                this.selectedId = mark.id;
                this.beginGesture(mark, event, 'move');
            },

            startResize(mark, event) {
                if (this.tool !== 'select' || !mark.rect) {
                    return;
                }
                this.selectedId = mark.id;
                this.beginGesture(mark, event, 'resize');
            },

            beginGesture(mark, event, type) {
                const point = this.eventToPdf(event);
                this.gesture = {
                    type,
                    id: mark.id,
                    start: point,
                    rect: mark.rect ? [...mark.rect] : null,
                    rects: mark.rects ? mark.rects.map((rect) => [...rect]) : null,
                    pushed: false,
                };
                const move = (moveEvent) => this.applyGesture(moveEvent);
                const up = () => {
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', up);
                    const current = this.marks.find((item) => item.id === mark.id);
                    if (current?.rect) {
                        current.rect = this.normalize(current.rect);
                    }
                    this.gesture = null;
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            },

            applyGesture(event) {
                if (!this.gesture || !viewport) {
                    return;
                }
                const point = this.eventToPdf(event);
                const dx = point.x - this.gesture.start.x;
                const dy = point.y - this.gesture.start.y;
                const mark = this.marks.find((item) => item.id === this.gesture.id);
                if (!mark) {
                    return;
                }
                if (!this.gesture.pushed) {
                    this.remember();
                    this.gesture.pushed = true;
                }
                if (this.gesture.type === 'move') {
                    if (this.gesture.rect) {
                        mark.rect = this.gesture.rect.map((value, index) => value + (index % 2 === 0 ? dx : dy));
                    }
                    if (this.gesture.rects) {
                        mark.rects = this.gesture.rects.map((rect) => [rect[0] + dx, rect[1] + dy, rect[2] + dx, rect[3] + dy]);
                    }
                    return;
                }
                if (this.gesture.rect) {
                    mark.rect = [this.gesture.rect[0], this.gesture.rect[1], point.x, point.y];
                }
            },

            removeMark(id) {
                this.remember();
                this.marks = this.marks.filter((mark) => mark.id !== id);
                if (this.selectedId === id) {
                    this.selectedId = null;
                }
            },

            loadImage(event) {
                const file = event.target.files?.[0];
                if (!file) {
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => {
                    this.imageData = reader.result;
                };
                reader.readAsDataURL(file);
            },

            inkDown(event) {
                if (this.tool !== 'draw') {
                    return;
                }
                const ink = this.$refs.ink;
                const context = ink.getContext('2d');
                const point = this.inkPoint(event);
                inkActive = true;
                context.strokeStyle = '#111827';
                context.lineWidth = 2;
                context.lineCap = 'round';
                context.beginPath();
                context.moveTo(point.x, point.y);
                ink.setPointerCapture(event.pointerId);
            },

            inkMove(event) {
                if (!inkActive) {
                    return;
                }
                const point = this.inkPoint(event);
                const context = this.$refs.ink.getContext('2d');
                context.lineTo(point.x, point.y);
                context.stroke();
            },

            inkUp() {
                if (!inkActive) {
                    return;
                }
                inkActive = false;
                const ink = this.$refs.ink;
                const context = ink.getContext('2d');
                const pixels = context.getImageData(0, 0, ink.width, ink.height).data;
                let minX = ink.width;
                let minY = ink.height;
                let maxX = 0;
                let maxY = 0;
                let found = false;
                for (let y = 0; y < ink.height; y += 2) {
                    for (let x = 0; x < ink.width; x += 2) {
                        if (pixels[(y * ink.width + x) * 4 + 3] > 12) {
                            found = true;
                            minX = Math.min(minX, x);
                            minY = Math.min(minY, y);
                            maxX = Math.max(maxX, x);
                            maxY = Math.max(maxY, y);
                        }
                    }
                }
                if (!found) {
                    context.clearRect(0, 0, ink.width, ink.height);
                    return;
                }
                minX = Math.max(0, minX - 4);
                minY = Math.max(0, minY - 4);
                maxX = Math.min(ink.width, maxX + 4);
                maxY = Math.min(ink.height, maxY + 4);
                const crop = document.createElement('canvas');
                crop.width = Math.max(1, maxX - minX);
                crop.height = Math.max(1, maxY - minY);
                crop.getContext('2d').drawImage(ink, minX, minY, crop.width, crop.height, 0, 0, crop.width, crop.height);
                const [x0, y0] = viewport.convertToPdfPoint(minX, maxY);
                const [x1, y1] = viewport.convertToPdfPoint(maxX, minY);
                this.remember();
                this.marks.push({
                    id: this.nextId++,
                    type: 'draw',
                    page: this.page,
                    rect: [Math.min(x0, x1), Math.min(y0, y1), Math.max(x0, x1), Math.max(y0, y1)],
                    image: crop.toDataURL('image/png'),
                });
                context.clearRect(0, 0, ink.width, ink.height);
            },

            inkPoint(event) {
                const ink = this.$refs.ink;
                const bounds = ink.getBoundingClientRect();
                return {
                    x: (event.clientX - bounds.left) * (ink.width / bounds.width),
                    y: (event.clientY - bounds.top) * (ink.height / bounds.height),
                };
            },

            async detectFields() {
                const found = [];
                for (let number = 1; number <= pdfDoc.numPages; number++) {
                    const pdfPage = await pdfDoc.getPage(number);
                    const annotations = await pdfPage.getAnnotations();
                    annotations.forEach((annotation) => {
                        if (annotation.subtype !== 'Widget' || !annotation.rect || annotation.fieldType === 'Sig') {
                            return;
                        }
                        let type = 'text';
                        if (annotation.radioButton) {
                            type = 'radio';
                        } else if (annotation.checkBox) {
                            type = 'checkbox';
                        } else if (annotation.fieldType === 'Ch') {
                            type = 'choice';
                        }
                        const options = (annotation.options || []).map((option) => {
                            if (typeof option === 'string') {
                                return { value: option, label: option };
                            }
                            return {
                                value: option.exportValue || option.displayValue || '',
                                label: option.displayValue || option.exportValue || '',
                            };
                        });
                        const checked = annotation.fieldValue && annotation.fieldValue !== 'Off';
                        found.push({
                            id: found.length + 1,
                            name: annotation.fieldName || '',
                            type,
                            page: number,
                            rect: annotation.rect,
                            value: type === 'checkbox' ? !!checked : (annotation.fieldValue || ''),
                            buttonValue: annotation.buttonValue || annotation.exportValue || 'Yes',
                            options,
                        });
                    });
                }
                this.fields = found;
            },

            fieldsOnPage() {
                return this.fields.filter((field) => field.page === this.page);
            },

            setRadio(field) {
                this.fields.forEach((item) => {
                    if (item.name === field.name && item.type === 'radio') {
                        item.value = field.buttonValue;
                    }
                });
            },

            fieldPayload() {
                const sent = new Map();
                this.fields.forEach((field) => {
                    if (!field.name) {
                        return;
                    }
                    if (field.type === 'radio') {
                        if (field.value && String(field.value) === String(field.buttonValue)) {
                            sent.set(field.name, field.buttonValue);
                        } else if (!sent.has(field.name)) {
                            sent.set(field.name, '');
                        }
                        return;
                    }
                    if (field.type === 'checkbox') {
                        sent.set(field.name, !!field.value);
                        return;
                    }
                    sent.set(field.name, field.value ?? '');
                });
                return [...sent.entries()].map(([name, value]) => ({ name, value }));
            },

            fieldsDirty() {
                return JSON.stringify(this.fieldPayload()) !== this.fieldSnapshot;
            },

            canSaveEdits() {
                const texts = this.marks.some((mark) => (mark.type === 'text' || mark.type === 'replacement') && String(mark.text || '').trim() !== '');
                const others = this.marks.some((mark) => !['text', 'replacement'].includes(mark.type));
                return texts || others || this.fieldsDirty();
            },

            payload() {
                const mapRects = (mark) => mark.rects;
                return {
                    replacements: this.marks.filter((mark) => mark.type === 'replacement' && String(mark.text || '').trim()).map((mark) => ({
                        page: mark.page,
                        rects: mapRects(mark),
                        text: mark.text.trim(),
                        size: mark.size || 12,
                    })),
                    highlights: this.marks.filter((mark) => mark.type === 'highlight').map((mark) => ({
                        page: mark.page,
                        rects: mark.rects,
                    })),
                    covers: this.marks.filter((mark) => mark.type === 'cover').map((mark) => ({ page: mark.page, rect: mark.rect })),
                    redactions: this.marks.filter((mark) => mark.type === 'redact').map((mark) => ({ page: mark.page, rect: mark.rect })),
                    texts: this.marks.filter((mark) => mark.type === 'text' && String(mark.text || '').trim()).map((mark) => ({
                        page: mark.page,
                        rect: mark.rect,
                        text: mark.text.trim(),
                        size: mark.size || 12,
                        color: mark.color || '#111827',
                    })),
                    images: this.marks.filter((mark) => mark.type === 'image' || mark.type === 'draw').map((mark) => ({
                        page: mark.page,
                        rect: mark.rect,
                        image: mark.image,
                    })),
                    fields: this.fieldsDirty() ? this.fieldPayload() : [],
                    flatten: this.flatten,
                };
            },

            async saveEdits() {
                await this.submit(this.submitUrl, this.payload(), 'Saving edits…');
            },

            planSignature() {
                return JSON.stringify(this.pagePlan.map((item) => ({
                    source: item.blank ? null : item.source,
                    rotate: item.rotate || 0,
                    blank: !!item.blank,
                })));
            },

            pagesDirty() {
                return this.planSignature() !== this.pagePlanOriginal;
            },

            pagesBlocked() {
                return this.marks.length > 0 || this.fieldsDirty();
            },

            showPlanPage(item) {
                if (item.blank || !item.source) {
                    return;
                }
                this.page = item.source;
                this.render();
            },

            movePlan(index, delta) {
                const next = index + delta;
                if (next < 0 || next >= this.pagePlan.length) {
                    return;
                }
                const copy = [...this.pagePlan];
                const [item] = copy.splice(index, 1);
                copy.splice(next, 0, item);
                this.pagePlan = copy;
            },

            rotatePlan(index) {
                const item = this.pagePlan[index];
                item.rotate = ((item.rotate || 0) + 90) % 360;
                item.thumb = '';
                this.pagePlan = [...this.pagePlan];
                this.renderThumbs();
            },

            removePlan(index) {
                if (this.pagePlan.length <= 1) {
                    this.error = 'A PDF needs at least one page.';
                    return;
                }
                this.pagePlan.splice(index, 1);
                this.pagePlan = [...this.pagePlan];
            },

            insertBlank() {
                const index = this.pagePlan.findIndex((item) => !item.blank && item.source === this.page);
                const blank = { id: this.nextPlanId++, source: null, rotate: 0, blank: true, thumb: '' };
                const copy = [...this.pagePlan];
                copy.splice(index + 1, 0, blank);
                this.pagePlan = copy;
            },

            async renderThumbs() {
                if (!pdfDoc) {
                    return;
                }
                for (const item of this.pagePlan) {
                    if (item.blank || item.thumb) {
                        continue;
                    }
                    const pdfPage = await pdfDoc.getPage(item.source);
                    const thumbViewport = pdfPage.getViewport({ scale: 0.16, rotation: item.rotate || 0 });
                    const canvas = document.createElement('canvas');
                    canvas.width = thumbViewport.width;
                    canvas.height = thumbViewport.height;
                    await pdfPage.render({ canvasContext: canvas.getContext('2d'), viewport: thumbViewport }).promise;
                    item.thumb = canvas.toDataURL('image/jpeg', 0.72);
                    this.pagePlan = [...this.pagePlan];
                }
            },

            async savePages() {
                const pages = this.pagePlan.map((item) => item.blank
                    ? { blank: true, rotate: 0 }
                    : { source: item.source, rotate: item.rotate || 0 });
                await this.submit(this.pagesUrl, { pages }, 'Saving pages…');
            },

            async submit(url, body, statusText) {
                this.submitting = true;
                this.statusText = statusText;
                this.error = '';
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(body),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        this.error = data.message || 'Could not save this edit.';
                        return;
                    }
                    if (data.status === 'completed') {
                        window.location = data.show_url;
                        return;
                    }
                    this.statusText = 'Preparing the new PDF…';
                    await this.poll(data.status_url);
                } catch (e) {
                    this.error = 'Could not save this edit.';
                } finally {
                    this.submitting = false;
                }
            },

            async poll(url) {
                for (let attempt = 0; attempt < 40; attempt++) {
                    await new Promise((resolve) => setTimeout(resolve, 1500));
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const data = await response.json().catch(() => ({}));
                    if (data.status === 'completed') {
                        window.location = data.show_url;
                        return;
                    }
                    if (data.status === 'failed') {
                        this.error = data.error || 'Could not edit this PDF.';
                        return;
                    }
                }
                this.error = 'The edit is still running. It will show in your library when it finishes.';
            },
        };
    }
</script>
@endpush
