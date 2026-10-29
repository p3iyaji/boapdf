@extends('layouts.app')

@section('title', 'Edit - '.$document->original_name)

@section('content')
<div class="mb-6" x-data="{ tab: @js($tab) }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Edit PDF</h1>
            <p class="mt-1 truncate text-sm text-gray-500">{{ $document->original_name }}</p>
            <p class="mt-2 text-gray-600">Annotate on top of the original pages, or fill detected form fields. Edits save as a new library document.</p>
        </div>
        <a href="{{ route('pdf.show', $document) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Back to document</a>
    </div>

    <div class="mt-5 flex flex-wrap gap-2 border-b border-gray-200 pb-px">
        <button type="button" @click="tab = 'annotate'"
                class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition"
                :class="tab === 'annotate' ? 'border border-b-white border-gray-200 bg-white text-blue-800' : 'border border-transparent text-gray-600 hover:text-gray-900'">
            Annotate / write
        </button>
        <button type="button" @click="tab = 'form'"
                class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition"
                :class="tab === 'form' ? 'border border-b-white border-gray-200 bg-white text-blue-800' : 'border border-transparent text-gray-600 hover:text-gray-900'">
            Fill form fields
        </button>
    </div>

    <div x-show="tab === 'annotate'" x-cloak class="mt-4"
         x-data="pdfAnnotator({
            streamUrl: @js(route('pdf.stream', $document)),
            submitUrl: @js(route('pdf.edit.store', $document)),
            csrf: @js(csrf_token()),
            totalPages: {{ (int) $document->pages }},
         })"
         x-init="init()">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow lg:col-span-2">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 p-3">
                    <div class="flex items-center space-x-2">
                        <button type="button" @click="prev()" :disabled="page <= 1" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm disabled:opacity-50">&larr; Prev</button>
                        <span class="text-sm text-gray-700">Page <span x-text="page"></span> / <span x-text="totalPages"></span></span>
                        <button type="button" @click="next()" :disabled="page >= totalPages" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm disabled:opacity-50">Next &rarr;</button>
                    </div>
                    <p class="text-xs text-gray-500" x-text="hint()"></p>
                </div>
                <div class="relative flex max-h-[calc(100vh-10rem)] min-h-[min(60vh,800px)] justify-center overflow-auto rounded-lg bg-gray-100 p-4">
                    <div class="relative" x-ref="stage">
                        <canvas x-ref="canvas" @click="onPdfClick($event)"
                                :class="tool === 'highlight' || tool === 'text' || tool === 'image' || tool === 'draw' ? 'cursor-crosshair' : 'cursor-default'"></canvas>
                        <template x-for="(item, index) in annotations.filter(a => a.page === page)" :key="item.id">
                            <div class="absolute z-10 rounded border-2 shadow-sm"
                                 :class="item.type === 'highlight' ? 'border-amber-400 bg-amber-200/50' : (item.type === 'text' ? 'border-violet-500 bg-white/70' : 'border-sky-500 bg-white/40')"
                                 :style="boxStyle(item)"
                                 @click.stop>
                                <button type="button" class="absolute -left-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-800 text-xs text-white" @click="removeAnnotation(item.id)">&times;</button>
                                <template x-if="item.type === 'text'">
                                    <p class="pointer-events-none truncate px-1 text-xs text-gray-900" x-text="item.text"></p>
                                </template>
                                <template x-if="item.type === 'image' || item.type === 'draw'">
                                    <img :src="item.image" alt="" class="pointer-events-none h-full w-full object-contain">
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <aside class="space-y-4 rounded-xl bg-white p-5 shadow">
                <h2 class="font-semibold text-gray-800">Tools</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="tool = 'text'" class="rounded-lg border px-3 py-1.5 text-sm" :class="tool === 'text' ? 'border-violet-600 bg-violet-50 text-violet-900' : 'border-gray-300'">Text</button>
                    <button type="button" @click="tool = 'draw'" class="rounded-lg border px-3 py-1.5 text-sm" :class="tool === 'draw' ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-gray-300'">Draw</button>
                    <button type="button" @click="tool = 'highlight'" class="rounded-lg border px-3 py-1.5 text-sm" :class="tool === 'highlight' ? 'border-amber-600 bg-amber-50 text-amber-900' : 'border-gray-300'">Highlight</button>
                    <button type="button" @click="tool = 'image'" class="rounded-lg border px-3 py-1.5 text-sm" :class="tool === 'image' ? 'border-sky-600 bg-sky-50 text-sky-900' : 'border-gray-300'">Image</button>
                </div>

                <div x-show="tool === 'text'" class="space-y-2">
                    <textarea x-model="draftText" rows="3" maxlength="2000" placeholder="Text to place…" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                    <label class="flex items-center justify-between text-sm text-gray-700">
                        <span>Font size</span>
                        <input type="number" min="6" max="72" x-model.number="fontSize" class="w-20 rounded border border-gray-300 px-2 py-1 text-right">
                    </label>
                    <label class="flex items-center justify-between text-sm text-gray-700">
                        <span>Width (mm)</span>
                        <input type="number" min="10" max="200" x-model.number="textWidthMm" class="w-20 rounded border border-gray-300 px-2 py-1 text-right">
                    </label>
                </div>

                <div x-show="tool === 'draw'" class="space-y-2">
                    <canvas x-ref="pad" width="400" height="140" class="w-full cursor-crosshair touch-none rounded-lg border border-gray-300 bg-white"></canvas>
                    <div class="flex justify-between">
                        <button type="button" @click="clearPad()" class="text-sm text-gray-600">Clear</button>
                        <label class="flex items-center gap-2 text-sm">Width <input type="number" min="10" max="200" x-model.number="drawWidthMm" class="w-16 rounded border px-2 py-1 text-right"></label>
                    </div>
                </div>

                <div x-show="tool === 'highlight'" class="space-y-2 text-sm text-gray-600">
                    <p>Click the PDF to place a highlight bar.</p>
                    <label class="flex items-center justify-between text-gray-700">
                        <span>Width (mm)</span>
                        <input type="number" min="10" max="200" x-model.number="highlightWidthMm" class="w-20 rounded border border-gray-300 px-2 py-1 text-right">
                    </label>
                    <label class="flex items-center justify-between text-gray-700">
                        <span>Height (mm)</span>
                        <input type="number" min="2" max="40" x-model.number="highlightHeightMm" class="w-20 rounded border border-gray-300 px-2 py-1 text-right">
                    </label>
                </div>

                <div x-show="tool === 'image'" class="space-y-2">
                    <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" @change="loadImage($event)" class="block w-full text-sm">
                    <img x-show="imageData" :src="imageData" alt="" class="h-16 max-w-full rounded border object-contain">
                    <label class="flex items-center justify-between text-sm text-gray-700">
                        <span>Width (mm)</span>
                        <input type="number" min="5" max="200" x-model.number="imageWidthMm" class="w-20 rounded border border-gray-300 px-2 py-1 text-right">
                    </label>
                </div>

                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Placed</p>
                    <p class="mt-1 text-sm text-gray-800"><span x-text="annotations.length"></span> annotation(s)</p>
                    <ul class="mt-2 max-h-40 space-y-1 overflow-auto text-xs text-gray-600">
                        <template x-for="item in annotations" :key="item.id">
                            <li class="flex justify-between gap-2">
                                <span x-text="item.type + ' · p' + item.page"></span>
                                <button type="button" class="text-red-600" @click="removeAnnotation(item.id)">Remove</button>
                            </li>
                        </template>
                    </ul>
                </div>

                <button type="button" @click="submit()" :disabled="!annotations.length || submitting"
                        class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!submitting">Save edited PDF</span>
                    <span x-show="submitting">Saving…</span>
                </button>
                <p class="text-xs text-red-600" x-show="errorMessage" x-text="errorMessage"></p>
            </aside>
        </div>
    </div>

    <div x-show="tab === 'form'" x-cloak class="mt-4"
         x-data="pdfFormFiller({
            streamUrl: @js(route('pdf.stream', $document)),
            submitUrl: @js(route('pdf.edit.form', $document)),
            csrf: @js(csrf_token()),
            totalPages: {{ (int) $document->pages }},
         })"
         x-init="init()">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow lg:col-span-2">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 p-3">
                    <div class="flex items-center space-x-2">
                        <button type="button" @click="prev()" :disabled="page <= 1" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm disabled:opacity-50">&larr; Prev</button>
                        <span class="text-sm text-gray-700">Page <span x-text="page"></span> / <span x-text="totalPages"></span></span>
                        <button type="button" @click="next()" :disabled="page >= totalPages" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm disabled:opacity-50">Next &rarr;</button>
                    </div>
                    <p class="text-xs text-gray-500" x-text="statusText"></p>
                </div>
                <div class="relative flex max-h-[calc(100vh-10rem)] min-h-[min(60vh,800px)] justify-center overflow-auto rounded-lg bg-gray-100 p-4">
                    <div class="relative" x-ref="stage">
                        <canvas x-ref="canvas"></canvas>
                        <template x-for="field in fieldsOnPage()" :key="field.id">
                            <div class="absolute z-10 rounded border border-blue-500/70 bg-blue-200/20"
                                 :style="fieldBoxStyle(field)"
                                 :title="field.name || field.type"></div>
                        </template>
                    </div>
                </div>
            </div>

            <aside class="space-y-4 rounded-xl bg-white p-5 shadow">
                <h2 class="font-semibold text-gray-800">Form fields</h2>
                <p class="text-sm text-gray-600" x-show="!loading && fields.length === 0">No fillable fields were detected in this PDF. Use Annotate / write to add text instead.</p>
                <p class="text-sm text-gray-500" x-show="loading">Scanning PDF for form fields…</p>
                <div class="max-h-[28rem] space-y-3 overflow-auto" x-show="fields.length">
                    <template x-for="field in fields" :key="field.id">
                        <label class="block rounded-lg border border-gray-100 bg-gray-50/80 p-3">
                            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500"
                                  x-text="(field.name || 'Field') + ' · p' + field.page + ' · ' + field.type"></span>
                            <template x-if="field.type === 'checkbox' || field.type === 'radio'">
                                <input type="checkbox" x-model="field.value" class="rounded border-gray-300 text-blue-600">
                            </template>
                            <template x-if="field.type !== 'checkbox' && field.type !== 'radio'">
                                <input type="text" x-model="field.value" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" :placeholder="'Enter ' + (field.name || 'value')">
                            </template>
                        </label>
                    </template>
                </div>
                <button type="button" @click="submit()" :disabled="!canSubmit() || submitting"
                        class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!submitting">Save filled PDF</span>
                    <span x-show="submitting">Saving…</span>
                </button>
                <p class="text-xs text-red-600" x-show="errorMessage" x-text="errorMessage"></p>
            </aside>
        </div>
    </div>
</div>
@endsection

@push('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
@endpush

@push('scripts')
<script>
    const PT_PER_MM = 72 / 25.4;

    function pdfAnnotator({ streamUrl, submitUrl, csrf, totalPages }) {
        let pdfDoc = null;
        let renderTask = null;
        let padDrawing = false;

        return {
            streamUrl, submitUrl, csrf, totalPages,
            page: 1,
            pageViewport: null,
            tool: 'text',
            draftText: '',
            fontSize: 12,
            textWidthMm: 60,
            drawWidthMm: 50,
            highlightWidthMm: 60,
            highlightHeightMm: 6,
            imageData: '',
            imageWidthMm: 40,
            annotations: [],
            nextId: 1,
            submitting: false,
            errorMessage: '',
            hasPadInk: false,

            async init() {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                const res = await fetch(this.streamUrl, { credentials: 'same-origin' });
                const buffer = await res.arrayBuffer();
                pdfDoc = await window.pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                this.totalPages = pdfDoc.numPages;
                this.setupPad();
                await this.render();
            },

            setupPad() {
                this.$nextTick(() => {
                    const pad = this.$refs.pad;
                    if (!pad) return;
                    const ctx = pad.getContext('2d');
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111827';
                    const pos = (e) => {
                        const rect = pad.getBoundingClientRect();
                        const src = e.touches ? e.touches[0] : e;
                        return {
                            x: (src.clientX - rect.left) * (pad.width / rect.width),
                            y: (src.clientY - rect.top) * (pad.height / rect.height),
                        };
                    };
                    const start = (e) => { padDrawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); };
                    const move = (e) => {
                        if (!padDrawing) return;
                        const p = pos(e);
                        ctx.lineTo(p.x, p.y);
                        ctx.stroke();
                        this.hasPadInk = true;
                        e.preventDefault();
                    };
                    const end = () => { padDrawing = false; };
                    pad.addEventListener('mousedown', start);
                    pad.addEventListener('mousemove', move);
                    window.addEventListener('mouseup', end);
                    pad.addEventListener('touchstart', start, { passive: false });
                    pad.addEventListener('touchmove', move, { passive: false });
                    pad.addEventListener('touchend', end);
                });
            },

            clearPad() {
                const pad = this.$refs.pad;
                if (!pad) return;
                pad.getContext('2d').clearRect(0, 0, pad.width, pad.height);
                this.hasPadInk = false;
            },

            async loadImage(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = () => { this.imageData = reader.result; };
                reader.readAsDataURL(file);
            },

            hint() {
                if (this.tool === 'text') return this.draftText.trim() ? 'Click the PDF to place text.' : 'Enter text, then click the PDF.';
                if (this.tool === 'draw') return this.hasPadInk ? 'Click the PDF to place your drawing.' : 'Draw on the pad, then click the PDF.';
                if (this.tool === 'image') return this.imageData ? 'Click the PDF to place the image.' : 'Upload an image, then click the PDF.';
                return 'Click the PDF to place a highlight.';
            },

            async render() {
                if (!pdfDoc) return;
                if (renderTask) { try { renderTask.cancel(); } catch (e) {} }
                const page = await pdfDoc.getPage(this.page);
                const unscaled = page.getViewport({ scale: 1 });
                const container = this.$refs.stage?.parentElement;
                const maxW = Math.max(320, (container?.clientWidth || 800) - 32);
                const scale = Math.min(2.2, maxW / unscaled.width);
                const viewport = page.getViewport({ scale });
                this.pageViewport = viewport;
                const canvas = this.$refs.canvas;
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                renderTask = page.render({ canvasContext: canvas.getContext('2d'), viewport });
                await renderTask.promise;
            },

            async prev() { if (this.page > 1) { this.page--; await this.render(); } },
            async next() { if (this.page < this.totalPages) { this.page++; await this.render(); } },

            canvasToMm(clientX, clientY) {
                const canvas = this.$refs.canvas;
                const rect = canvas.getBoundingClientRect();
                const xCss = clientX - rect.left;
                const yCss = clientY - rect.top;
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;
                const xPx = xCss * scaleX;
                const yPx = yCss * scaleY;
                const scale = this.pageViewport.scale;
                return {
                    x: (xPx / scale) / PT_PER_MM,
                    y: (yPx / scale) / PT_PER_MM,
                };
            },

            mmToCssBox(xMm, yMm, wMm, hMm) {
                const canvas = this.$refs.canvas;
                const rect = canvas.getBoundingClientRect();
                const scale = this.pageViewport.scale;
                const cssScaleX = rect.width / canvas.width;
                const cssScaleY = rect.height / canvas.height;
                return {
                    left: ((xMm * PT_PER_MM * scale) * cssScaleX) + 'px',
                    top: ((yMm * PT_PER_MM * scale) * cssScaleY) + 'px',
                    width: ((wMm * PT_PER_MM * scale) * cssScaleX) + 'px',
                    height: ((hMm * PT_PER_MM * scale) * cssScaleY) + 'px',
                };
            },

            boxStyle(item) {
                const h = item.height || (item.type === 'text' ? item.font_size * 0.5 : item.width * 0.4);
                const box = this.mmToCssBox(item.x, item.y, item.width, h);
                return `left:${box.left};top:${box.top};width:${box.width};height:${box.height};`;
            },

            onPdfClick(event) {
                if (!this.pageViewport) return;
                const mm = this.canvasToMm(event.clientX, event.clientY);
                if (this.tool === 'text') {
                    if (!this.draftText.trim()) { this.errorMessage = 'Enter text first.'; return; }
                    this.annotations.push({
                        id: this.nextId++,
                        type: 'text',
                        page: this.page,
                        x: mm.x,
                        y: mm.y,
                        width: this.textWidthMm,
                        height: this.fontSize * 0.5,
                        text: this.draftText.trim(),
                        font_size: this.fontSize,
                        color: '#111827',
                    });
                    this.errorMessage = '';
                    return;
                }
                if (this.tool === 'highlight') {
                    this.annotations.push({
                        id: this.nextId++,
                        type: 'highlight',
                        page: this.page,
                        x: mm.x,
                        y: mm.y,
                        width: this.highlightWidthMm,
                        height: this.highlightHeightMm,
                        color: '#FDE047',
                    });
                    return;
                }
                if (this.tool === 'image') {
                    if (!this.imageData) { this.errorMessage = 'Upload an image first.'; return; }
                    this.annotations.push({
                        id: this.nextId++,
                        type: 'image',
                        page: this.page,
                        x: mm.x,
                        y: mm.y,
                        width: this.imageWidthMm,
                        image: this.imageData,
                    });
                    this.errorMessage = '';
                    return;
                }
                if (this.tool === 'draw') {
                    const pad = this.$refs.pad;
                    if (!pad || !this.hasPadInk) { this.errorMessage = 'Draw something first.'; return; }
                    this.annotations.push({
                        id: this.nextId++,
                        type: 'draw',
                        page: this.page,
                        x: mm.x,
                        y: mm.y,
                        width: this.drawWidthMm,
                        image: pad.toDataURL('image/png'),
                    });
                    this.errorMessage = '';
                }
            },

            removeAnnotation(id) {
                this.annotations = this.annotations.filter(a => a.id !== id);
            },

            async submit() {
                this.submitting = true;
                this.errorMessage = '';
                try {
                    const payload = {
                        annotations: this.annotations.map(({ id, ...rest }) => rest),
                    };
                    const res = await fetch(this.submitUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                        credentials: 'same-origin',
                    });
                    if (res.redirected) {
                        window.location = res.url;
                        return;
                    }
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const first = data.message || Object.values(data.errors || {})[0]?.[0] || 'Could not save edits.';
                        this.errorMessage = first;
                        return;
                    }
                    if (data.redirect) {
                        window.location = data.redirect;
                    } else {
                        window.location.reload();
                    }
                } catch (e) {
                    this.errorMessage = 'Could not save edits.';
                } finally {
                    this.submitting = false;
                }
            },
        };
    }

    function pdfFormFiller({ streamUrl, submitUrl, csrf, totalPages }) {
        let pdfDoc = null;
        let renderTask = null;

        return {
            streamUrl, submitUrl, csrf, totalPages,
            page: 1,
            pageViewport: null,
            fields: [],
            loading: true,
            statusText: '',
            submitting: false,
            errorMessage: '',

            async init() {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                const res = await fetch(this.streamUrl, { credentials: 'same-origin' });
                const buffer = await res.arrayBuffer();
                pdfDoc = await window.pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                this.totalPages = pdfDoc.numPages;
                await this.detectFields();
                await this.render();
                this.loading = false;
            },

            mapFieldType(raw) {
                const t = String(raw || 'Tx').toLowerCase();
                if (t === 'btn' || t.includes('check')) return 'checkbox';
                if (t.includes('radio')) return 'radio';
                if (t === 'ch' || t.includes('combo') || t.includes('list')) return 'dropdown';
                return 'text';
            },

            async detectFields() {
                const found = [];
                let id = 1;
                for (let p = 1; p <= pdfDoc.numPages; p++) {
                    const page = await pdfDoc.getPage(p);
                    const annotations = await page.getAnnotations();
                    const viewport = page.getViewport({ scale: 1 });
                    for (const ann of annotations) {
                        if (ann.subtype !== 'Widget' || !ann.rect) continue;
                        const [x1, y1, x2, y2] = ann.rect;
                        // PDF coords are bottom-left; convert to top-left mm for our stamp pipeline.
                        const xMm = Math.min(x1, x2) / PT_PER_MM;
                        const widthMm = Math.abs(x2 - x1) / PT_PER_MM;
                        const heightMm = Math.abs(y2 - y1) / PT_PER_MM;
                        const topMm = (viewport.height - Math.max(y1, y2)) / PT_PER_MM;
                        const type = this.mapFieldType(ann.fieldType || ann.checkBox && 'Btn');
                        found.push({
                            id: id++,
                            name: ann.fieldName || ann.alternativeText || '',
                            type,
                            page: p,
                            x: xMm,
                            y: topMm,
                            width: Math.max(2, widthMm),
                            height: Math.max(2, heightMm),
                            value: type === 'checkbox' || type === 'radio' ? false : '',
                        });
                    }
                }
                this.fields = found;
                this.statusText = found.length
                    ? `Found ${found.length} field(s).`
                    : 'No AcroForm fields found.';
            },

            fieldsOnPage() {
                return this.fields.filter(f => f.page === this.page);
            },

            fieldBoxStyle(field) {
                if (!this.pageViewport) return 'display:none';
                const canvas = this.$refs.canvas;
                const rect = canvas.getBoundingClientRect();
                const scale = this.pageViewport.scale;
                const cssScaleX = rect.width / canvas.width;
                const cssScaleY = rect.height / canvas.height;
                const left = (field.x * PT_PER_MM * scale) * cssScaleX;
                const top = (field.y * PT_PER_MM * scale) * cssScaleY;
                const width = (field.width * PT_PER_MM * scale) * cssScaleX;
                const height = (field.height * PT_PER_MM * scale) * cssScaleY;
                return `left:${left}px;top:${top}px;width:${width}px;height:${height}px;`;
            },

            async render() {
                if (!pdfDoc) return;
                if (renderTask) { try { renderTask.cancel(); } catch (e) {} }
                const page = await pdfDoc.getPage(this.page);
                const unscaled = page.getViewport({ scale: 1 });
                const container = this.$refs.stage?.parentElement;
                const maxW = Math.max(320, (container?.clientWidth || 800) - 32);
                const scale = Math.min(2.2, maxW / unscaled.width);
                const viewport = page.getViewport({ scale });
                this.pageViewport = viewport;
                const canvas = this.$refs.canvas;
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                renderTask = page.render({ canvasContext: canvas.getContext('2d'), viewport });
                await renderTask.promise;
            },

            async prev() { if (this.page > 1) { this.page--; await this.render(); } },
            async next() { if (this.page < this.totalPages) { this.page++; await this.render(); } },

            canSubmit() {
                return this.fields.some(f => {
                    if (f.type === 'checkbox' || f.type === 'radio') return !!f.value;
                    return String(f.value || '').trim() !== '';
                });
            },

            async submit() {
                this.submitting = true;
                this.errorMessage = '';
                try {
                    const fields = this.fields
                        .filter(f => (f.type === 'checkbox' || f.type === 'radio') ? !!f.value : String(f.value || '').trim() !== '')
                        .map(({ id, ...rest }) => ({
                            ...rest,
                            value: (rest.type === 'checkbox' || rest.type === 'radio') ? !!rest.value : String(rest.value),
                        }));
                    const res = await fetch(this.submitUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ fields }),
                        credentials: 'same-origin',
                    });
                    if (res.redirected) {
                        window.location = res.url;
                        return;
                    }
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.errorMessage = data.message || Object.values(data.errors || {})[0]?.[0] || 'Could not save form.';
                        return;
                    }
                    window.location.reload();
                } catch (e) {
                    this.errorMessage = 'Could not save form.';
                } finally {
                    this.submitting = false;
                }
            },
        };
    }
</script>
@endpush
