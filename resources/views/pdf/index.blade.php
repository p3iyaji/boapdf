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
        Photograph one or more pages with your device camera. Each capture becomes a PDF page (works best on phones over HTTPS or localhost).
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
    </div>
    <p x-show="error" x-cloak class="mt-3 text-sm text-red-700" x-text="error"></p>

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

            <div class="mt-3 overflow-hidden rounded-xl bg-black">
                <video x-ref="video" playsinline muted autoplay class="mx-auto max-h-64 w-full object-contain sm:max-h-80"></video>
            </div>
            <canvas x-ref="canvas" class="hidden" width="2" height="2"></canvas>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" @click="capturePage()" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-amber-500">Capture page</button>
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

            <p class="mt-2 text-xs text-teal-800/70"><span x-text="frames.length"></span> page(s) queued</p>

            <div class="mt-3 grid max-h-32 grid-cols-4 gap-2 overflow-y-auto sm:max-h-40" x-show="frames.length > 0">
                <template x-for="(f, i) in frames" :key="i">
                    <div class="aspect-square overflow-hidden rounded-lg border border-teal-900/10 bg-white">
                        <img :src="f.preview" alt="" class="h-full w-full object-cover" />
                    </div>
                </template>
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-teal-900/10 pt-4">
                <button type="button" @click="closeModal()" class="rounded-lg px-4 py-2 text-sm font-medium text-teal-900 hover:bg-teal-900/10">Cancel</button>
                <button
                    type="button"
                    @click="submitCapture()"
                    :disabled="frames.length === 0"
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
    return {
        open: false,
        stream: null,
        frames: [],
        error: null,
        title: '',
        async startCamera() {
            this.error = null;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.error = 'Your browser does not support camera capture.';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 } },
                    audio: false,
                });
                this.open = true;
                await this.$nextTick();
                const v = this.$refs.video;
                if (v) {
                    v.srcObject = this.stream;
                    await v.play().catch(() => {});
                }
            } catch (e) {
                this.error = 'Camera blocked or unavailable. Allow camera access and use HTTPS (or localhost).';
            }
        },
        stopStream() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
            const v = this.$refs.video;
            if (v) {
                v.srcObject = null;
            }
        },
        closeModal() {
            this.stopStream();
            this.frames.forEach((f) => {
                if (f.preview) {
                    URL.revokeObjectURL(f.preview);
                }
            });
            this.frames = [];
            this.error = null;
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
            }, 'image/jpeg', 0.9);
        },
        removeLast() {
            const f = this.frames.pop();
            if (f && f.preview) {
                URL.revokeObjectURL(f.preview);
            }
        },
        clearAll() {
            this.frames.forEach((f) => {
                if (f.preview) {
                    URL.revokeObjectURL(f.preview);
                }
            });
            this.frames = [];
        },
        submitCapture() {
            if (this.frames.length === 0) {
                this.error = 'Add at least one page (Capture page).';
                return;
            }
            const form = this.$refs.captureForm;
            const input = this.$refs.cameraFiles;
            const dt = new DataTransfer();
            this.frames.forEach((f) => dt.items.add(f.file));
            input.files = dt.files;
            form.requestSubmit();
        },
    };
}
</script>
@endpush
@endsection
