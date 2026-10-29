@extends('layouts.app')

@section('title', 'Converting PDF - '.config('app.name'))

@section('content')
<div class="mx-auto max-w-xl"
     x-data="convertProgress({
         statusUrl: @js($statusUrl),
         downloadUrl: @js($downloadUrl),
         initial: @js($initialStatus),
         targetLabel: @js($targetLabel),
     })"
     x-init="start()">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Convert PDF</h1>
        <p class="mt-1 text-gray-600">
            <span x-show="!ready && !failed">Preparing your <span class="font-medium text-gray-800" x-text="targetLabel"></span> file&hellip;</span>
            <span x-show="ready" x-cloak>Your file is ready to download.</span>
            <span x-show="failed" x-cloak>Something went wrong during conversion.</span>
        </p>
    </div>

    <div class="rounded-xl bg-white p-6 shadow sm:p-8">
        <div class="mb-6 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full"
                 :class="failed ? 'bg-red-100 text-red-600' : (ready ? 'bg-emerald-100 text-emerald-600' : 'bg-blue-100 text-blue-600')">
                <svg x-show="!ready && !failed" class="h-6 w-6 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg x-show="ready" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <svg x-show="failed" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-gray-800" x-text="name"></p>
                <p class="mt-0.5 text-sm text-gray-500">
                    <span x-text="targetLabel"></span>
                    <template x-if="ready && size">
                        <span> · <span x-text="size"></span></span>
                    </template>
                </p>
            </div>
        </div>

        <div x-show="!failed" class="mb-2">
            <div class="mb-1.5 flex items-center justify-between text-xs font-medium text-gray-600">
                <span x-text="statusLabel"></span>
                <span class="tabular-nums" x-text="Math.round(progress) + '%'"></span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-gray-100" role="progressbar"
                 :aria-valuenow="Math.round(progress)" aria-valuemin="0" aria-valuemax="100"
                 :aria-label="statusLabel">
                <div class="h-full rounded-full transition-all duration-500 ease-out"
                     :class="ready ? 'bg-emerald-500' : 'bg-blue-600'"
                     :style="'width: ' + progress + '%'"></div>
            </div>
        </div>

        <div x-show="failed" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p x-text="error || 'Could not convert this PDF. Please try again.'"></p>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <a x-show="ready" x-cloak
               :href="downloadUrl"
               class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700">
                Download file
            </a>
            <a x-show="failed" x-cloak
               href="{{ route('pdf.convert.create') }}"
               class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700">
                Try again
            </a>
            <a href="{{ route('pdf.convert.create') }}"
               class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50"
               :class="ready || failed ? 'sm:flex-none' : 'flex-1'">
                Convert another
            </a>
            <a href="{{ route('pdf.index') }}"
               class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Library
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function convertProgress({ statusUrl, downloadUrl, initial, targetLabel }) {
        return {
            statusUrl,
            downloadUrl: initial.download_url || downloadUrl,
            targetLabel,
            name: initial.name,
            size: initial.size,
            status: initial.status,
            ready: initial.ready,
            failed: initial.failed,
            error: initial.error,
            progress: initial.ready ? 100 : (initial.failed ? 0 : 12),
            pollTimer: null,
            tickTimer: null,

            get statusLabel() {
                if (this.failed) {
                    return 'Failed';
                }
                if (this.ready) {
                    return 'Complete';
                }
                if (this.progress < 35) {
                    return 'Reading PDF…';
                }
                if (this.progress < 70) {
                    return 'Converting…';
                }
                return 'Finishing up…';
            },

            start() {
                if (this.ready || this.failed) {
                    return;
                }

                this.tickTimer = setInterval(() => {
                    if (this.ready || this.failed) {
                        return;
                    }
                    this.progress = Math.min(this.progress + (this.progress < 50 ? 4 : 1.5), 92);
                }, 800);

                this.pollTimer = setInterval(() => this.poll(), 1500);
                this.poll();
            },

            async poll() {
                try {
                    const response = await fetch(this.statusUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        return;
                    }
                    const data = await response.json();
                    this.apply(data);
                } catch (e) {
                    // Keep polling; transient network blips are fine.
                }
            },

            apply(data) {
                this.status = data.status;
                this.name = data.name;
                this.size = data.size;
                this.error = data.error;
                this.failed = data.failed;
                this.ready = data.ready;

                if (data.download_url) {
                    this.downloadUrl = data.download_url;
                }

                if (this.ready) {
                    this.progress = 100;
                    this.stop();
                } else if (this.failed) {
                    this.progress = 0;
                    this.stop();
                }
            },

            stop() {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
                if (this.tickTimer) {
                    clearInterval(this.tickTimer);
                    this.tickTimer = null;
                }
            },
        };
    }
</script>
@endpush
@endsection
