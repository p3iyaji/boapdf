@extends('layouts.app')

@section('title', 'My PDFs - '.config('app.name'))

@section('content')
<div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800 sm:text-3xl">My PDFs</h1>
        <p class="mt-1 text-sm text-gray-600 sm:text-base">Upload, view, and manage your PDF library.</p>
    </div>
</div>

<div class="mb-6 rounded-xl bg-white p-4 shadow sm:p-6">
    <form method="POST" action="{{ route('pdf.upload') }}" enctype="multipart/form-data"
          x-data="{ fileName: null, dragging: false }"
          @dragover.prevent="dragging = true"
          @dragleave.prevent="dragging = false"
          @drop.prevent="dragging = false; $refs.file.files = $event.dataTransfer.files; fileName = $refs.file.files[0]?.name; $refs.form.requestSubmit();"
          x-ref="form">
        @csrf
        <label class="block cursor-pointer rounded-xl border-2 border-dashed p-8 text-center transition sm:p-10"
               :class="dragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'">
            <input type="file" name="file" accept="application/pdf" class="hidden" x-ref="file"
                   @change="fileName = $event.target.files[0]?.name; $refs.form.requestSubmit();">
            <div class="flex flex-col items-center space-y-2">
                <svg class="h-10 w-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                <p class="font-medium text-gray-700">Drop a PDF here, or click to select</p>
                <p class="text-xs text-gray-500">Max {{ config('pdf.max_file_size') / 1024 }} MB</p>
                <p x-show="fileName" class="max-w-xs truncate text-sm text-blue-600" x-text="fileName"></p>
            </div>
        </label>
    </form>
</div>

<div
    x-data="cameraCapture()"
    class="mb-6 rounded-xl border border-teal-900/10 bg-white/95 p-4 shadow-md shadow-teal-950/5 sm:p-6"
>
    <h2 class="text-lg font-semibold text-teal-950">Scan from camera</h2>
    <p class="mt-1 text-sm text-teal-900/70">
        Photograph one or more pages with your device camera or photo library. Each image becomes a full-page PDF sheet — tap a preview to crop and zoom so it fills the signing window.
    </p>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button
            type="button"
            x-show="!open"
            @click="startCamera()"
            class="inline-flex items-center gap-2 rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-amber-50 shadow hover:bg-teal-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Open camera
        </button>
        <button
            type="button"
            x-show="!open"
            @click="pickPhotos()"
            class="inline-flex items-center gap-2 rounded-lg border border-teal-900/20 bg-white px-4 py-2.5 text-sm font-semibold text-teal-900 shadow-sm hover:bg-teal-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
        >
            Choose photos
        </button>
    </div>
    <p x-show="error" x-cloak class="mt-3 text-sm text-red-700" x-text="error"></p>

    {{-- Native camera / gallery: works on every mobile browser without getUserMedia --}}
    <input
        type="file"
        accept="image/*"
        capture="environment"
        class="sr-only"
        x-ref="nativeCamera"
        @change="onNativePhotos($event)"
    />
    <input
        type="file"
        accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
        multiple
        class="sr-only"
        x-ref="photoPicker"
        @change="onNativePhotos($event)"
    />

    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-end justify-center bg-teal-950/60 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:items-center"
        @keydown.escape.window="closeModal()"
        @click.self="closeModal()"
    >
        <div
            class="max-h-[min(92vh,40rem)] w-full max-w-lg overflow-y-auto rounded-2xl border border-teal-900/15 bg-stone-50 p-4 shadow-2xl sm:p-6"
        >
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-display text-lg font-semibold text-teal-950">Camera capture</h3>
                <button type="button" class="rounded-lg p-1 text-teal-800 hover:bg-teal-900/10" @click="closeModal()" aria-label="Close">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div x-show="mode === 'live'" class="mt-3 overflow-hidden rounded-xl bg-black">
                <video x-ref="video" playsinline muted autoplay class="mx-auto max-h-64 w-full object-contain sm:max-h-80"></video>
            </div>
            <canvas x-ref="canvas" class="hidden" width="2" height="2"></canvas>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    x-show="mode === 'live'"
                    @click="capturePage()"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-amber-500"
                >
                    Capture page
                </button>
                <button
                    type="button"
                    x-show="mode !== 'live'"
                    @click="useNativeCamera()"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-amber-500"
                >
                    Take photo
                </button>
                <button
                    type="button"
                    @click="pickPhotos()"
                    class="rounded-lg border border-teal-900/20 px-4 py-2 text-sm font-medium text-teal-900"
                >
                    Add photos
                </button>
                <button type="button" @click="removeLast()" :disabled="frames.length === 0" class="rounded-lg border border-teal-900/20 px-4 py-2 text-sm font-medium text-teal-900 disabled:opacity-40">Remove last</button>
                <button type="button" @click="clearAll()" :disabled="frames.length === 0" class="rounded-lg border border-teal-900/20 px-4 py-2 text-sm font-medium text-teal-900 disabled:opacity-40">Clear all</button>
            </div>

            <label class="mt-4 block">
                <span class="text-sm font-medium text-teal-950">Document name (optional)</span>
                <input
                    type="text"
                    x-model="title"
                    maxlength="200"
                    placeholder="e.g. Receipt May 2026"
                    class="mt-1 w-full rounded-lg border border-teal-900/15 bg-white px-3 py-2 text-sm text-stone-900 shadow-inner focus:outline-none focus:ring-2 focus:ring-amber-500/80"
                />
            </label>

            <p class="mt-2 text-sm font-medium text-teal-950" x-show="frames.length > 0 && !editing">
                <span x-text="frames.length"></span> page(s) queued — tap a page below to crop &amp; zoom for full-screen signing
            </p>
            <p class="mt-2 text-xs text-teal-800/70" x-show="frames.length === 0">Capture or add photos, then edit each page size before saving.</p>

            <div class="mt-3 grid max-h-48 grid-cols-2 gap-3 overflow-y-auto sm:max-h-56 sm:grid-cols-3" x-show="frames.length > 0 && !editing">
                <template x-for="(f, i) in frames" :key="i">
                    <button
                        type="button"
                        class="overflow-hidden rounded-lg border-2 border-amber-500/70 bg-white text-left shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500"
                        @click="openEditor(i)"
                        :title="'Edit page ' + (i + 1) + ' size'"
                    >
                        <div class="aspect-[3/4] overflow-hidden bg-stone-100">
                            <img :src="f.preview" alt="" class="h-full w-full object-cover" />
                        </div>
                        <span class="block bg-amber-600 px-2 py-1.5 text-center text-xs font-semibold text-white">
                            Edit size — page <span x-text="i + 1"></span>
                        </span>
                    </button>
                </template>
            </div>

            <div x-show="editing" x-cloak class="mt-4 space-y-3 rounded-xl border border-teal-900/15 bg-white p-3">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-teal-950">
                        Edit page size <span class="font-normal text-teal-800/70" x-text="editing ? '(page ' + (editIndex + 1) + ')' : ''"></span>
                    </h4>
                    <button type="button" class="text-xs font-medium text-teal-800 hover:text-teal-950" @click="cancelEditor()">Back</button>
                </div>
                <p class="text-xs text-teal-800/70">Drag to pan. Zoom so the photo fills the A4 frame — that frame is what you see full-screen when signing.</p>
                <div
                    class="relative mx-auto w-full max-w-[14rem] overflow-hidden rounded-lg border border-teal-900/20 bg-stone-200 shadow-inner"
                    style="aspect-ratio: 210 / 297;"
                    x-ref="cropViewport"
                    @pointerdown="startPan($event)"
                    @pointermove="onPan($event)"
                    @pointerup="endPan($event)"
                    @pointercancel="endPan($event)"
                    @pointerleave="endPan($event)"
                >
                    <img
                        x-ref="cropImage"
                        :src="editPreview"
                        alt=""
                        class="absolute left-0 top-0 max-w-none origin-top-left select-none touch-none"
                        draggable="false"
                        :style="cropImageStyle()"
                        @load="onCropImageLoad()"
                    />
                </div>
                <label class="block">
                    <span class="flex items-center justify-between text-xs font-medium text-teal-950">
                        <span>Zoom</span>
                        <span class="tabular-nums text-teal-800/70" x-text="Math.round(editZoom * 100) + '%'"></span>
                    </span>
                    <input
                        type="range"
                        min="1"
                        max="4"
                        step="0.01"
                        x-model.number="editZoom"
                        @input="clampPan()"
                        class="mt-1 w-full accent-amber-600"
                    />
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="fillPage()" class="rounded-lg border border-teal-900/20 px-3 py-1.5 text-xs font-semibold text-teal-900 hover:bg-teal-50">
                        Fill page
                    </button>
                    <button type="button" @click="fitInside()" class="rounded-lg border border-teal-900/20 px-3 py-1.5 text-xs font-medium text-teal-900 hover:bg-teal-50">
                        Fit inside
                    </button>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-teal-900/10 pt-3">
                    <button type="button" @click="cancelEditor()" class="rounded-lg px-3 py-1.5 text-sm font-medium text-teal-900 hover:bg-teal-900/10">Cancel</button>
                    <button type="button" @click="applyEditor()" class="rounded-lg bg-amber-600 px-4 py-1.5 text-sm font-semibold text-white shadow hover:bg-amber-500">
                        Apply size
                    </button>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-teal-900/10 pt-4">
                <button type="button" @click="closeModal()" class="rounded-lg px-4 py-2 text-sm font-medium text-teal-900 hover:bg-teal-900/10">Cancel</button>
                <button
                    type="button"
                    @click="submitCapture()"
                    :disabled="frames.length === 0 || editing"
                    class="rounded-lg bg-gradient-to-r from-teal-800 to-teal-950 px-5 py-2 text-sm font-semibold text-amber-50 shadow disabled:opacity-40"
                >
                    Save as PDF
                </button>
            </div>
        </div>
    </div>

    <form
        x-ref="captureForm"
        method="POST"
        action="{{ route('pdf.upload.camera') }}"
        enctype="multipart/form-data"
        class="sr-only"
    >
        @csrf
        <input type="hidden" name="title" x-bind:value="title" />
        <input type="file" name="images[]" multiple accept="image/jpeg,image/png" x-ref="cameraFiles" />
    </form>
</div>

@if ($documents->isEmpty())
    <div class="rounded-xl bg-white p-12 text-center shadow">
        <p class="text-gray-500">You haven't uploaded any PDFs yet.</p>
    </div>
@else
    {{-- Phones & tablets: stacked cards with full action grid (table from lg) --}}
    <div class="space-y-3 lg:hidden">
        @foreach ($documents as $doc)
            <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <a href="{{ route('pdf.show', $doc) }}" class="block font-semibold text-blue-600 hover:text-blue-800">{{ $doc->original_name }}</a>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                    {{ ucfirst($doc->operation_type) }}
                    <span class="text-gray-300">&middot;</span> {{ $doc->pages }} pages
                    <span class="text-gray-300">&middot;</span> {{ $doc->human_file_size }}
                    <span class="text-gray-300">&middot;</span> {{ $doc->created_at->diffForHumans() }}
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @include('pdf._icon-actions', ['doc' => $doc, 'large' => true])
                    <a href="{{ route('pdf.sign.create', $doc) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-emerald-600 px-4 text-center text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 active:bg-emerald-800 sm:flex-initial">Sign</a>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Large screens: table with compact pill actions --}}
    <div class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow lg:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 lg:px-6">Name</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 lg:px-6">Type</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 lg:px-6">Pages</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 lg:px-6">Size</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 lg:px-6">Uploaded</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 lg:px-6">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($documents as $doc)
                        <tr class="hover:bg-gray-50/80">
                            <td class="max-w-[14rem] px-4 py-3 lg:max-w-xs lg:px-6 lg:py-4">
                                <a href="{{ route('pdf.show', $doc) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $doc->original_name }}</a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 lg:px-6 lg:py-4">{{ ucfirst($doc->operation_type) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 lg:px-6 lg:py-4">{{ $doc->pages }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 lg:px-6 lg:py-4">{{ $doc->human_file_size }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 lg:px-6 lg:py-4">{{ $doc->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 lg:px-6 lg:py-4">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    @include('pdf._icon-actions', ['doc' => $doc, 'large' => false])
                                    <a href="{{ route('pdf.sign.create', $doc) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 sm:text-sm">Sign</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $documents->links() }}</div>
@endif

@push('scripts')
<script>
function cameraCapture() {
    const A4_RATIO = 210 / 297;

    return {
        open: false,
        mode: 'photos',
        stream: null,
        frames: [],
        error: null,
        title: '',
        editing: false,
        editIndex: -1,
        editPreview: '',
        editZoom: 1,
        editBaseZoom: 1,
        editOffsetX: 0,
        editOffsetY: 0,
        editNaturalW: 0,
        editNaturalH: 0,
        panActive: false,
        panStartX: 0,
        panStartY: 0,
        panOriginX: 0,
        panOriginY: 0,
        getUserMediaFn() {
            if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
                return (constraints) => navigator.mediaDevices.getUserMedia(constraints);
            }

            const legacy = navigator.getUserMedia
                || navigator.webkitGetUserMedia
                || navigator.mozGetUserMedia
                || navigator.msGetUserMedia;

            if (!legacy) {
                return null;
            }

            return (constraints) => new Promise((resolve, reject) => {
                legacy.call(navigator, constraints, resolve, reject);
            });
        },
        canUseLiveCamera() {
            return window.isSecureContext !== false && typeof this.getUserMediaFn() === 'function';
        },
        async startCamera() {
            this.error = null;

            if (this.canUseLiveCamera()) {
                try {
                    await this.startLiveCamera();
                    return;
                } catch (e) {
                    // Fall through to the native camera / file picker path.
                }
            }

            this.useNativeCamera();
        },
        async startLiveCamera() {
            const getUserMedia = this.getUserMediaFn();
            if (!getUserMedia) {
                throw new Error('getUserMedia unavailable');
            }

            const attempts = [
                { video: { facingMode: { ideal: 'environment' } }, audio: false },
                { video: { facingMode: 'environment' }, audio: false },
                { video: true, audio: false },
            ];

            let lastError = null;
            for (const constraints of attempts) {
                try {
                    this.stream = await getUserMedia(constraints);
                    this.mode = 'live';
                    this.open = true;
                    await this.$nextTick();
                    const video = this.$refs.video;
                    if (video) {
                        if ('srcObject' in video) {
                            video.srcObject = this.stream;
                        } else {
                            video.src = URL.createObjectURL(this.stream);
                        }
                        video.setAttribute('playsinline', 'true');
                        video.muted = true;
                        await video.play().catch(() => {});
                    }
                    return;
                } catch (e) {
                    lastError = e;
                    this.stopStream();
                }
            }

            throw lastError || new Error('Camera unavailable');
        },
        useNativeCamera() {
            this.error = null;
            this.mode = 'photos';
            const input = this.$refs.nativeCamera;
            if (!input) {
                this.error = 'Camera input is unavailable in this browser.';
                return;
            }
            input.value = '';
            input.click();
        },
        pickPhotos() {
            this.error = null;
            const input = this.$refs.photoPicker;
            if (!input) {
                this.error = 'Photo picker is unavailable in this browser.';
                return;
            }
            input.value = '';
            input.click();
        },
        async onNativePhotos(event) {
            const files = Array.from(event.target.files || []);
            if (files.length === 0) {
                return;
            }

            this.mode = 'photos';
            this.stopStream();
            this.open = true;
            this.error = null;

            for (const file of files) {
                if (!file.type.startsWith('image/') && !/\.(jpe?g|png|webp|heic|heif)$/i.test(file.name || '')) {
                    continue;
                }

                try {
                    const normalized = await this.normalizeImageFile(file);
                    this.frames.push(normalized);
                } catch (e) {
                    this.error = 'Could not read one of the selected photos. Try a JPEG or PNG.';
                }
            }

            if (this.frames.length === 0 && !this.error) {
                this.error = 'No usable photos were selected. Try JPEG or PNG images.';
            } else if (this.frames.length > 0) {
                this.openEditor(this.frames.length - 1);
            }

            event.target.value = '';
        },
        normalizeImageFile(file) {
            return new Promise((resolve, reject) => {
                // Server PDF builder accepts JPEG/PNG only — pass those through.
                if (file.type === 'image/jpeg' || file.type === 'image/png') {
                    resolve({
                        preview: URL.createObjectURL(file),
                        file: file instanceof File
                            ? file
                            : new File([file], file.name || ('page-' + (this.frames.length + 1) + '.jpg'), { type: file.type }),
                    });
                    return;
                }

                // Convert webp/heic/etc. to JPEG when the browser can decode them.
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth || img.width;
                    canvas.height = img.naturalHeight || img.height;
                    const ctx = canvas.getContext('2d');
                    if (!ctx || !canvas.width || !canvas.height) {
                        URL.revokeObjectURL(url);
                        reject(new Error('Invalid image'));
                        return;
                    }
                    ctx.drawImage(img, 0, 0);
                    canvas.toBlob((blob) => {
                        URL.revokeObjectURL(url);
                        if (!blob) {
                            reject(new Error('Could not convert image'));
                            return;
                        }
                        const name = 'page-' + (this.frames.length + 1) + '.jpg';
                        resolve({
                            preview: URL.createObjectURL(blob),
                            file: new File([blob], name, { type: 'image/jpeg' }),
                        });
                    }, 'image/jpeg', 0.9);
                };
                img.onerror = () => {
                    URL.revokeObjectURL(url);
                    reject(new Error('Could not load image'));
                };
                img.src = url;
            });
        },
        stopStream() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
            const video = this.$refs.video;
            if (video) {
                video.srcObject = null;
                if (video.src && video.src.startsWith('blob:')) {
                    URL.revokeObjectURL(video.src);
                }
                video.removeAttribute('src');
            }
        },
        closeModal() {
            this.cancelEditor();
            this.stopStream();
            this.frames.forEach((f) => {
                if (f.preview) {
                    URL.revokeObjectURL(f.preview);
                }
            });
            this.frames = [];
            this.error = null;
            this.mode = 'photos';
            this.open = false;
        },
        capturePage() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            if (!video || !canvas || video.readyState < 2) {
                this.error = 'Wait for the camera preview to load.';
                return;
            }
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            canvas.toBlob((blob) => {
                if (!blob) {
                    this.error = 'Could not capture this frame.';
                    return;
                }
                const name = 'page-' + (this.frames.length + 1) + '.jpg';
                const file = new File([blob], name, { type: 'image/jpeg' });
                const preview = URL.createObjectURL(blob);
                this.frames.push({ preview, file });
                this.error = null;
                this.openEditor(this.frames.length - 1);
            }, 'image/jpeg', 0.9);
        },
        removeLast() {
            if (this.editing) {
                this.cancelEditor();
            }
            const f = this.frames.pop();
            if (f && f.preview) {
                URL.revokeObjectURL(f.preview);
            }
        },
        clearAll() {
            this.cancelEditor();
            this.frames.forEach((f) => {
                if (f.preview) {
                    URL.revokeObjectURL(f.preview);
                }
            });
            this.frames = [];
        },
        openEditor(index) {
            const frame = this.frames[index];
            if (!frame) {
                return;
            }
            this.editing = true;
            this.editIndex = index;
            this.editPreview = frame.preview;
            this.editZoom = 1;
            this.editBaseZoom = 1;
            this.editOffsetX = 0;
            this.editOffsetY = 0;
            this.editNaturalW = 0;
            this.editNaturalH = 0;
            this.$nextTick(() => {
                const img = this.$refs.cropImage;
                if (img && img.complete && img.naturalWidth) {
                    this.onCropImageLoad();
                }
            });
        },
        cancelEditor() {
            this.editing = false;
            this.editIndex = -1;
            this.editPreview = '';
            this.panActive = false;
        },
        viewportSize() {
            const vp = this.$refs.cropViewport;
            if (!vp) {
                return { w: 0, h: 0 };
            }
            return { w: vp.clientWidth, h: vp.clientHeight };
        },
        onCropImageLoad() {
            const img = this.$refs.cropImage;
            if (!img) {
                return;
            }
            this.editNaturalW = img.naturalWidth || img.width;
            this.editNaturalH = img.naturalHeight || img.height;
            this.fillPage();
        },
        coverZoom() {
            const { w, h } = this.viewportSize();
            if (!w || !h || !this.editNaturalW || !this.editNaturalH) {
                return 1;
            }
            return Math.max(w / this.editNaturalW, h / this.editNaturalH);
        },
        containZoom() {
            const { w, h } = this.viewportSize();
            if (!w || !h || !this.editNaturalW || !this.editNaturalH) {
                return 1;
            }
            return Math.min(w / this.editNaturalW, h / this.editNaturalH);
        },
        fillPage() {
            this.editBaseZoom = this.coverZoom();
            this.editZoom = 1;
            this.editOffsetX = 0;
            this.editOffsetY = 0;
            this.clampPan();
        },
        fitInside() {
            this.editBaseZoom = this.containZoom();
            this.editZoom = 1;
            this.editOffsetX = 0;
            this.editOffsetY = 0;
            this.clampPan();
        },
        displayScale() {
            return this.editBaseZoom * this.editZoom;
        },
        cropImageStyle() {
            const scale = this.displayScale();
            const w = this.editNaturalW * scale;
            const h = this.editNaturalH * scale;
            const { w: vw, h: vh } = this.viewportSize();
            const left = (vw - w) / 2 + this.editOffsetX;
            const top = (vh - h) / 2 + this.editOffsetY;
            return `width:${w}px;height:${h}px;transform:translate(${left}px, ${top}px);`;
        },
        clampPan() {
            const { w: vw, h: vh } = this.viewportSize();
            if (!vw || !vh) {
                return;
            }
            const scale = this.displayScale();
            const w = this.editNaturalW * scale;
            const h = this.editNaturalH * scale;
            const maxX = Math.max(0, (w - vw) / 2);
            const maxY = Math.max(0, (h - vh) / 2);
            this.editOffsetX = Math.min(maxX, Math.max(-maxX, this.editOffsetX));
            this.editOffsetY = Math.min(maxY, Math.max(-maxY, this.editOffsetY));
        },
        startPan(event) {
            if (!this.editing) {
                return;
            }
            this.panActive = true;
            this.panStartX = event.clientX;
            this.panStartY = event.clientY;
            this.panOriginX = this.editOffsetX;
            this.panOriginY = this.editOffsetY;
            if (event.currentTarget && event.currentTarget.setPointerCapture) {
                event.currentTarget.setPointerCapture(event.pointerId);
            }
        },
        onPan(event) {
            if (!this.panActive) {
                return;
            }
            this.editOffsetX = this.panOriginX + (event.clientX - this.panStartX);
            this.editOffsetY = this.panOriginY + (event.clientY - this.panStartY);
            this.clampPan();
        },
        endPan(event) {
            if (!this.panActive) {
                return;
            }
            this.panActive = false;
            if (event.currentTarget && event.currentTarget.releasePointerCapture) {
                try {
                    event.currentTarget.releasePointerCapture(event.pointerId);
                } catch (e) {
                    // ignore
                }
            }
        },
        applyEditor() {
            if (!this.editing || this.editIndex < 0) {
                return;
            }
            const { w: vw, h: vh } = this.viewportSize();
            if (!vw || !vh || !this.editNaturalW || !this.editNaturalH) {
                this.error = 'Wait for the image to load before applying.';
                return;
            }

            const scale = this.displayScale();
            const dispW = this.editNaturalW * scale;
            const dispH = this.editNaturalH * scale;
            const left = (vw - dispW) / 2 + this.editOffsetX;
            const top = (vh - dispH) / 2 + this.editOffsetY;

            // Map the A4 viewport back into source image pixels.
            const srcX = Math.max(0, (-left) / scale);
            const srcY = Math.max(0, (-top) / scale);
            const srcW = Math.min(this.editNaturalW - srcX, vw / scale);
            const srcH = Math.min(this.editNaturalH - srcY, vh / scale);

            if (srcW < 2 || srcH < 2) {
                this.error = 'Crop area is too small. Zoom out a little and try again.';
                return;
            }

            // Export at a resolution that looks sharp on an A4 signing canvas (~150–200 dpi).
            const targetW = Math.min(1654, Math.max(800, Math.round(srcW)));
            const targetH = Math.round(targetW / A4_RATIO);

            const canvas = document.createElement('canvas');
            canvas.width = targetW;
            canvas.height = targetH;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                this.error = 'Could not crop this image.';
                return;
            }
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, targetW, targetH);

            const img = this.$refs.cropImage;
            ctx.drawImage(img, srcX, srcY, srcW, srcH, 0, 0, targetW, targetH);

            canvas.toBlob((blob) => {
                if (!blob) {
                    this.error = 'Could not save the cropped page.';
                    return;
                }
                const old = this.frames[this.editIndex];
                if (old && old.preview) {
                    URL.revokeObjectURL(old.preview);
                }
                const name = 'page-' + (this.editIndex + 1) + '.jpg';
                const file = new File([blob], name, { type: 'image/jpeg' });
                const preview = URL.createObjectURL(blob);
                this.frames.splice(this.editIndex, 1, { preview, file });
                this.error = null;
                this.cancelEditor();
            }, 'image/jpeg', 0.92);
        },
        submitCapture() {
            if (this.editing) {
                this.error = 'Apply or cancel page size editing before saving.';
                return;
            }
            if (this.frames.length === 0) {
                this.error = 'Add at least one page before saving.';
                return;
            }
            const form = this.$refs.captureForm;
            const input = this.$refs.cameraFiles;
            if (!form || !input) {
                this.error = 'Upload form is unavailable. Refresh and try again.';
                return;
            }

            if (typeof DataTransfer === 'undefined') {
                this.error = 'This browser cannot attach captured photos. Use Choose photos, then try again.';
                return;
            }

            const dt = new DataTransfer();
            this.frames.forEach((f) => dt.items.add(f.file));
            input.files = dt.files;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        },
    };
}
</script>
@endpush
@endsection
