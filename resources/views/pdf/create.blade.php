@extends('layouts.app')

@section('title', 'Create PDF')

@section('content')
<div x-data="pdfCreator({
        submitUrl: @js(route('pdf.create.store')),
        csrf: @js(csrf_token()),
     })" x-init="init()">
    <input type="file" x-ref="sharedFile" class="hidden" accept="image/png,image/jpeg,image/webp,image/gif"
           @change="onSharedFile($event)">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Create PDF</h1>
            <p class="mt-2 text-gray-600">Build a new document with vector text and images. Saved to your library.</p>
        </div>
        <a href="{{ route('pdf.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Back to library</a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl bg-white p-4 shadow sm:p-5">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block min-w-[12rem] flex-1 text-sm">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Title</span>
                        <input type="text" x-model="title" maxlength="180" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Untitled.pdf">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Size</span>
                        <select x-model="pageSize" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="A4">A4</option>
                            <option value="LETTER">Letter</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Orientation</span>
                        <select x-model="orientation" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="P">Portrait</option>
                            <option value="L">Landscape</option>
                        </select>
                    </label>
                </div>
            </div>

            <template x-for="(page, pageIndex) in pages" :key="page.id">
                <div class="rounded-xl bg-white p-4 shadow sm:p-5">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold text-gray-800">Page <span x-text="pageIndex + 1"></span></h2>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="addText(pageIndex)" class="rounded-lg border border-violet-300 bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-900">+ Text</button>
                            <button type="button" @click="pickImage(pageIndex)" class="rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-sm font-medium text-sky-900">+ Image</button>
                            <button type="button" @click="removePage(pageIndex)" :disabled="pages.length <= 1"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 disabled:opacity-40">Remove page</button>
                        </div>
                    </div>

                    <div class="page-preview relative mx-auto overflow-hidden rounded-lg border border-gray-200 bg-[#f8fafc] shadow-inner"
                         :style="pagePreviewStyle()">
                        <template x-for="(el, elIndex) in page.elements" :key="el.id">
                            <div class="absolute rounded border border-dashed border-gray-400/70 bg-white/80 p-1"
                                 :style="elementStyle(el)"
                                 @mousedown="startDrag(pageIndex, elIndex, $event)">
                                <button type="button" class="absolute -right-2 -top-2 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-gray-800 text-xs text-white"
                                        @click.stop="removeElement(pageIndex, elIndex)">&times;</button>
                                <template x-if="el.type === 'text'">
                                    <textarea x-model="el.text" rows="2" class="w-full resize-none border-0 bg-transparent p-0 text-sm focus:ring-0"
                                              :style="`font-size:${Math.max(10, el.font_size)}px;color:${el.color}`"
                                              placeholder="Type here…"></textarea>
                                </template>
                                <template x-if="el.type === 'image'">
                                    <img :src="el.image" alt="" class="pointer-events-none h-full w-full object-contain">
                                </template>
                            </div>
                        </template>
                        <p x-show="page.elements.length === 0" class="absolute inset-0 flex items-center justify-center text-sm text-gray-400">Add text or an image to this page</p>
                    </div>
                </div>
            </template>

            <button type="button" @click="addPage()"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:border-blue-400 hover:text-blue-800">
                + Add page
            </button>
        </div>

        <aside class="h-fit space-y-4 rounded-xl bg-white p-5 shadow lg:sticky lg:top-8">
            <h2 class="font-semibold text-gray-800">Create</h2>
            <p class="text-sm text-gray-600">Text is written as vector Helvetica for crisp output. Images are embedded at high resolution.</p>
            <ul class="text-sm text-gray-600">
                <li><span x-text="pages.length"></span> page(s)</li>
                <li><span x-text="elementCount()"></span> element(s)</li>
            </ul>
            <button type="button" @click="submit()" :disabled="!canSubmit() || submitting"
                    class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                <span x-show="!submitting">Create PDF</span>
                <span x-show="submitting">Creating…</span>
            </button>
            <p class="text-xs text-red-600" x-show="errorMessage" x-text="errorMessage"></p>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function pdfCreator({ submitUrl, csrf }) {
        let nextPageId = 1;
        let nextElId = 1;
        let drag = null;

        return {
            submitUrl, csrf,
            title: 'Untitled.pdf',
            pageSize: 'A4',
            orientation: 'P',
            pages: [{ id: 1, elements: [] }],
            pendingImagePage: null,
            submitting: false,
            errorMessage: '',

            init() {
                nextPageId = 2;
                window.addEventListener('mousemove', (e) => this.onDrag(e));
                window.addEventListener('mouseup', () => { drag = null; });
            },

            pageDims() {
                const portrait = this.orientation === 'P';
                if (this.pageSize === 'LETTER') {
                    return portrait ? { w: 216, h: 279 } : { w: 279, h: 216 };
                }
                return portrait ? { w: 210, h: 297 } : { w: 297, h: 210 };
            },

            pagePreviewStyle() {
                const { w, h } = this.pageDims();
                const scale = 2.2;
                return `width:${w * scale}px;height:${h * scale}px;max-width:100%;aspect-ratio:${w}/${h};`;
            },

            mmToPct(mm, axis) {
                const { w, h } = this.pageDims();
                return (mm / (axis === 'x' ? w : h)) * 100;
            },

            elementStyle(el) {
                const heightMm = el.height || (el.type === 'text' ? el.font_size * 1.2 : el.width * 0.75);
                return `left:${this.mmToPct(el.x, 'x')}%;top:${this.mmToPct(el.y, 'y')}%;width:${this.mmToPct(el.width, 'x')}%;height:${this.mmToPct(heightMm, 'y')}%;`;
            },

            addPage() {
                this.pages.push({ id: nextPageId++, elements: [] });
            },

            removePage(index) {
                if (this.pages.length <= 1) return;
                this.pages.splice(index, 1);
            },

            addText(pageIndex) {
                this.pages[pageIndex].elements.push({
                    id: nextElId++,
                    type: 'text',
                    x: 20,
                    y: 25 + this.pages[pageIndex].elements.length * 12,
                    width: 160,
                    height: 20,
                    text: '',
                    font_size: 14,
                    color: '#111827',
                });
            },

            pickImage(pageIndex) {
                this.pendingImagePage = pageIndex;
                this.$refs.sharedFile.click();
            },

            onSharedFile(event) {
                const file = event.target.files?.[0];
                const pageIndex = this.pendingImagePage;
                this.pendingImagePage = null;
                event.target.value = '';
                if (!file || pageIndex === null) return;
                const reader = new FileReader();
                reader.onload = () => {
                    this.pages[pageIndex].elements.push({
                        id: nextElId++,
                        type: 'image',
                        x: 30,
                        y: 40,
                        width: 80,
                        height: 60,
                        image: reader.result,
                    });
                };
                reader.readAsDataURL(file);
            },

            removeElement(pageIndex, elIndex) {
                this.pages[pageIndex].elements.splice(elIndex, 1);
            },

            startDrag(pageIndex, elIndex, event) {
                if (event.target.tagName === 'TEXTAREA' || event.target.tagName === 'INPUT') return;
                const el = this.pages[pageIndex].elements[elIndex];
                drag = {
                    pageIndex,
                    elIndex,
                    startX: event.clientX,
                    startY: event.clientY,
                    origX: el.x,
                    origY: el.y,
                };
                event.preventDefault();
            },

            onDrag(event) {
                if (!drag) return;
                const preview = document.querySelectorAll('.page-preview')[drag.pageIndex];
                if (!preview) return;
                const rect = preview.getBoundingClientRect();
                const { w, h } = this.pageDims();
                const dx = ((event.clientX - drag.startX) / rect.width) * w;
                const dy = ((event.clientY - drag.startY) / rect.height) * h;
                const el = this.pages[drag.pageIndex].elements[drag.elIndex];
                el.x = Math.max(0, drag.origX + dx);
                el.y = Math.max(0, drag.origY + dy);
            },

            elementCount() {
                return this.pages.reduce((n, p) => n + p.elements.length, 0);
            },

            canSubmit() {
                return this.pages.some(p => p.elements.some(el =>
                    (el.type === 'text' && String(el.text || '').trim() !== '') ||
                    (el.type === 'image' && el.image)
                ));
            },

            async submit() {
                this.submitting = true;
                this.errorMessage = '';
                try {
                    const payload = {
                        title: this.title,
                        page_size: this.pageSize,
                        orientation: this.orientation,
                        pages: this.pages.map(p => ({
                            elements: p.elements
                                .filter(el => (el.type === 'text' && String(el.text || '').trim() !== '') || (el.type === 'image' && el.image))
                                .map(({ id, ...rest }) => rest),
                        })),
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
                        this.errorMessage = data.message || Object.values(data.errors || {})[0]?.[0] || 'Could not create PDF.';
                        return;
                    }
                    window.location.reload();
                } catch (e) {
                    this.errorMessage = 'Could not create PDF.';
                } finally {
                    this.submitting = false;
                }
            },
        };
    }
</script>
@endpush
