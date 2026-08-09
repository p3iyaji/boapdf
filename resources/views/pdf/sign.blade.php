@extends('layouts.app')

@section('title', ($guestMode ?? false) ? 'Sign document' : 'Sign - '.$document->original_name)

@section('content')
@php
    $guestMode = $guestMode ?? false;
    $signatureRequests = $signatureRequests ?? collect();
    $pendingCount = $signatureRequests->where('status', \App\Models\SignatureRequest::STATUS_PENDING)->count();
    $signedCount = $signatureRequests->where('status', \App\Models\SignatureRequest::STATUS_SIGNED)->count();
    $streamUrl = $guestMode
        ? route('sign.guest.stream', $token)
        : route('pdf.stream', $document);
    $submitUrl = $guestMode
        ? route('sign.guest.store', $token)
        : route('pdf.sign.store', $document);
@endphp

@if ($guestMode)
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
@endif

<div class="mb-6" x-data="{ mode: @js(($guestMode || $signatureRequests->isEmpty()) ? 'sign' : (request()->query('tab') === 'invite' ? 'invite' : 'sign')) }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            @if ($guestMode)
                <p class="text-sm font-medium text-emerald-700">Signature requested</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-800 md:text-3xl">Sign {{ $document->original_name }}</h1>
                <p class="mt-2 text-gray-600">
                    {{ $signatureRequest->requester_email }} asked
                    {{ $signatureRequest->signer_name ? $signatureRequest->signer_name : 'you' }}
                    to sign this PDF. Place your signature, then apply.
                </p>
            @else
                <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Sign PDF</h1>
                <p class="mt-1 truncate text-sm text-gray-500">{{ $document->original_name }}</p>
                <p class="mt-2 text-gray-600">
                    Sign it yourself, or invite others by email. Each person gets a secure link—no account required.
                </p>
            @endif
        </div>
        @unless ($guestMode)
            <a href="{{ route('pdf.show', $document) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Back to document</a>
        @endunless
    </div>

    @unless ($guestMode)
        <div class="mt-5 flex flex-wrap gap-2 border-b border-gray-200 pb-px">
            <button type="button" @click="mode = 'sign'"
                    class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition"
                    :class="mode === 'sign' ? 'border border-b-white border-gray-200 bg-white text-emerald-800' : 'border border-transparent text-gray-600 hover:text-gray-900'">
                Sign myself
            </button>
            <button type="button" @click="mode = 'invite'"
                    class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition"
                    :class="mode === 'invite' ? 'border border-b-white border-gray-200 bg-white text-emerald-800' : 'border border-transparent text-gray-600 hover:text-gray-900'">
                Request signatures
                @if ($pendingCount > 0)
                    <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ $pendingCount }} waiting</span>
                @endif
            </button>
        </div>
    @endunless

    @if (! $guestMode && $signatureRequests->isNotEmpty())
        <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-800">Signing progress</h2>
                <p class="text-xs text-gray-500">{{ $signedCount }} of {{ $signatureRequests->count() }} complete</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($signatureRequests as $req)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-800">
                                {{ $req->signer_name ?: $req->signer_email }}
                            </p>
                            @if ($req->signer_name)
                                <p class="truncate text-xs text-gray-500">{{ $req->signer_email }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($req->status === \App\Models\SignatureRequest::STATUS_SIGNED)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Signed</span>
                            @elseif ($req->isExpired())
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Expired</span>
                                <form method="POST" action="{{ route('pdf.sign.invite.destroy', [$document, $req]) }}"
                                      onsubmit="return confirm('Remove this expired invite?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-700 hover:text-red-900">Remove</button>
                                </form>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Waiting</span>
                                <form method="POST" action="{{ route('pdf.sign.invite.destroy', [$document, $req]) }}"
                                      onsubmit="return confirm(@js('Remove '.$req->signer_email.' from this signing request? Their link will stop working.'))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-700 hover:text-red-900">Remove</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless ($guestMode)
    <div x-show="mode === 'invite'" x-cloak class="mt-4" x-data="inviteSignersForm()">
        <div class="rounded-xl bg-white p-5 shadow sm:p-6">
            <h2 class="text-lg font-semibold text-gray-800">Invite people to sign</h2>
            <p class="mt-1 text-sm text-gray-600">They’ll receive an email with a private link. Signatures are applied one after another onto the same PDF.</p>

            <form method="POST" action="{{ route('pdf.sign.invite', $document) }}" class="mt-5 space-y-4">
                @csrf
                <template x-for="(signer, index) in signers" :key="index">
                    <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-100 bg-gray-50/80 p-3 sm:grid-cols-[1fr_1.4fr_auto]">
                        <label class="block text-sm">
                            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Name</span>
                            <input type="text" :name="'signers['+index+'][name]'" x-model="signer.name" maxlength="120"
                                   placeholder="Optional"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Email</span>
                            <input type="email" :name="'signers['+index+'][email]'" x-model="signer.email" required maxlength="255"
                                   placeholder="name@example.com"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </label>
                        <div class="flex items-end">
                            <button type="button" @click="removeSigner(index)" :disabled="signers.length <= 1"
                                    class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto">
                                Remove
                            </button>
                        </div>
                    </div>
                </template>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="addSigner()"
                            class="inline-flex min-h-10 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:border-emerald-400 hover:text-emerald-800">
                        + Add another signer
                    </button>
                    <button type="submit"
                            class="inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-600 px-5 text-sm font-semibold text-white hover:bg-emerald-700">
                        Send signature requests
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endunless

<div x-show="mode === 'sign' || {{ $guestMode ? 'true' : 'false' }}"
     class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3"
     x-data="signer({
        streamUrl: @js($streamUrl),
        submitUrl: @js($submitUrl),
        csrf: @js(csrf_token()),
        totalPages: {{ (int) $document->pages }},
        guestMode: @js($guestMode),
     })"
     x-init="init()">

    <div class="rounded-xl bg-white p-4 shadow lg:col-span-2">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 p-3">
            <div class="flex items-center space-x-2">
                <button type="button" @click="prev()" :disabled="page <= 1"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm disabled:opacity-50">&larr; Prev</button>
                <span class="text-sm text-gray-700">
                    Page <input type="number" min="1" :max="totalPages" x-model.number="page" @change="render()"
                                class="w-16 rounded border border-gray-300 px-2 py-1 text-center"> / <span x-text="totalPages"></span>
                </span>
                <button type="button" @click="next()" :disabled="page >= totalPages"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm disabled:opacity-50">Next &rarr;</button>
            </div>
            <div class="flex flex-col items-end gap-2 text-xs text-gray-500">
                <div x-show="hasDrawingInk() || canPlaceTyped() || logoData" class="flex flex-wrap items-center justify-end gap-1.5">
                    <span class="text-gray-500">Place on PDF:</span>
                    <button type="button" @click="placeMode = 'drawing'" :disabled="!hasDrawingInk()"
                            class="rounded border px-2 py-1 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-40"
                            :class="placeMode === 'drawing' ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'">Drawing</button>
                    <button type="button" @click="placeMode = 'typed'" :disabled="!canPlaceTyped()"
                            class="rounded border px-2 py-1 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-40"
                            :class="placeMode === 'typed' ? 'border-violet-600 bg-violet-50 text-violet-900' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'">Typed text</button>
                    <button type="button" @click="placeMode = 'logo'" :disabled="!logoData"
                            class="rounded border px-2 py-1 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-40"
                            :class="placeMode === 'logo' ? 'border-sky-600 bg-sky-50 text-sky-800' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'">Logo</button>
                </div>
                <p class="max-w-md text-right">
                    <span x-show="!hasDrawingInk() && !canPlaceTyped() && !logoData" class="text-amber-700">Use the right panel to draw, type, or upload a logo, then pick what to place here.</span>
                    <span x-show="placeMode === 'drawing' && !drawPlacement && hasDrawingInk()">Click the PDF to place your drawing.</span>
                    <span x-show="placeMode === 'typed' && hasTypedDraft() && !dragMode">Click the PDF to place this text — click again to add more.</span>
                    <span x-show="placeMode === 'typed' && !hasTypedDraft() && typedTexts.length && !dragMode" class="font-medium text-violet-800">Select a placed text to move or resize, or type new text to place another.</span>
                    <span x-show="placeMode === 'logo' && logoData && !logoPlacement">Click the PDF to place your logo.</span>
                    <span x-show="placeMode === 'drawing' && drawPlacement && !dragMode" class="font-medium text-emerald-700">Drawing on page <span x-text="drawPlacement?.page"></span> — drag, resize, or × to remove from page.</span>
                    <span x-show="placeMode === 'logo' && logoPlacement && !dragMode" class="font-medium text-sky-700">Logo on page <span x-text="logoPlacement?.page"></span> — drag, resize, or × to remove from page.</span>
                    <span x-show="dragMode && dragTarget === 'drawing'" class="font-medium text-emerald-700" x-text="dragMode === 'resize' ? 'Resizing drawing…' : 'Moving drawing…'"></span>
                    <span x-show="dragMode && dragTarget === 'typed'" class="font-medium text-violet-800" x-text="dragMode === 'resize' ? 'Resizing text…' : 'Moving text…'"></span>
                    <span x-show="dragMode && dragTarget === 'logo'" class="font-medium text-sky-700" x-text="dragMode === 'resize' ? 'Resizing logo…' : 'Moving logo…'"></span>
                </p>
            </div>
        </div>

        <div class="relative flex max-h-[calc(100vh-10rem)] min-h-[min(70vh,900px)] justify-center overflow-auto rounded-lg bg-gray-100 p-4">
            <div class="relative touch-manipulation" x-ref="stage">
                <canvas x-ref="canvas" @click="onPdfClick($event)"
                        :class="pdfCanvasCursorClass()"></canvas>
                <div x-show="drawPlacement && drawPlacement.page === page"
                     x-cloak
                     class="absolute z-10 touch-none select-none rounded border-2 border-emerald-500/80 bg-white/40 shadow-sm"
                     :class="dragMode === 'move' && dragTarget === 'drawing' ? 'cursor-grabbing' : 'cursor-grab'"
                     :style="drawPlacementBoxStyle()"
                     @mousedown.prevent.stop="startMoveDrawing($event)"
                     @touchstart.prevent.stop="startMoveDrawing($event)">
                    <button type="button"
                            class="absolute -left-1.5 -top-1.5 z-20 flex h-6 w-6 items-center justify-center rounded-full border border-white bg-gray-700 text-xs font-bold text-white shadow hover:bg-gray-900"
                            title="Remove drawing from page (keeps ink on pad)"
                            aria-label="Remove drawing from page"
                            @click.stop.prevent="clearDrawingFromPage()">&times;</button>
                    <img :src="drawingData" alt=""
                         class="pointer-events-none block h-full w-full object-contain opacity-95">
                    <button type="button"
                            class="absolute -bottom-1.5 -right-1.5 flex h-6 w-6 cursor-nwse-resize items-center justify-center rounded-full border-2 border-white bg-emerald-600 text-white shadow-md hover:bg-emerald-700"
                            aria-label="Resize drawing"
                            title="Drag to resize"
                            @mousedown.prevent.stop="startResizeDrawing($event)"
                            @touchstart.prevent.stop="startResizeDrawing($event)">
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                            <path d="M12 12H9v-3h3V12zM12 7H9V4h3v3zM7 12H4V9h3v3z"/>
                        </svg>
                    </button>
                </div>
                <template x-for="item in typedTextsOnPage()" :key="item.id">
                    <div class="absolute z-20 touch-none select-none rounded border-2 bg-white/40 shadow-sm"
                         :class="typedBoxClass(item)"
                         :style="typedPlacementBoxStyle(item)"
                         @mousedown.prevent.stop="startMoveTyped($event, item.id)"
                         @touchstart.prevent.stop="startMoveTyped($event, item.id)">
                        <button type="button"
                                class="absolute -left-1.5 -top-1.5 z-20 flex h-6 w-6 items-center justify-center rounded-full border border-white bg-gray-700 text-xs font-bold text-white shadow hover:bg-gray-900"
                                title="Remove this text"
                                aria-label="Remove typed text"
                                @click.stop.prevent="removeTypedText(item.id)">&times;</button>
                        <img :src="item.dataUrl" alt=""
                             class="pointer-events-none block h-full w-full object-contain opacity-95">
                        <button type="button"
                                class="absolute -bottom-1.5 -right-1.5 flex h-6 w-6 cursor-nwse-resize items-center justify-center rounded-full border-2 border-white bg-violet-600 text-white shadow-md hover:bg-violet-700"
                                aria-label="Resize typed text"
                                title="Drag to resize"
                                @mousedown.prevent.stop="startResizeTyped($event, item.id)"
                                @touchstart.prevent.stop="startResizeTyped($event, item.id)">
                            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                                <path d="M12 12H9v-3h3V12zM12 7H9V4h3v3zM7 12H4V9h3v3z"/>
                            </svg>
                        </button>
                    </div>
                </template>
                <div x-show="logoPlacement && logoPlacement.page === page"
                     x-cloak
                     class="absolute z-10 touch-none select-none rounded border-2 border-sky-500/80 bg-white/40 shadow-sm"
                     :class="dragMode === 'move' && dragTarget === 'logo' ? 'cursor-grabbing' : 'cursor-grab'"
                     :style="logoPlacementBoxStyle()"
                     @mousedown.prevent.stop="startMoveLogo($event)"
                     @touchstart.prevent.stop="startMoveLogo($event)">
                    <button type="button"
                            class="absolute -left-1.5 -top-1.5 z-20 flex h-6 w-6 items-center justify-center rounded-full border border-white bg-gray-700 text-xs font-bold text-white shadow hover:bg-gray-900"
                            title="Remove from page (keeps your image file)"
                            aria-label="Remove logo from page"
                            @click.stop.prevent="clearLogoFromPage()">&times;</button>
                    <img :src="logoData" alt="" class="pointer-events-none block h-full w-full object-contain opacity-95">
                    <button type="button"
                            class="absolute -bottom-1.5 -right-1.5 flex h-6 w-6 cursor-nwse-resize items-center justify-center rounded-full border-2 border-white bg-sky-600 text-white shadow-md hover:bg-sky-700"
                            aria-label="Resize logo"
                            title="Drag to resize"
                            @mousedown.prevent.stop="startResizeLogo($event)"
                            @touchstart.prevent.stop="startResizeLogo($event)">
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                            <path d="M12 12H9v-3h3V12zM12 7H9V4h3v3zM7 12H4V9h3v3z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <aside class="space-y-4 rounded-xl bg-white p-5 shadow">
        <ol class="mb-1 space-y-1 text-xs text-gray-500">
            <li><span class="font-semibold text-gray-700">Step 1</span> — Create a signature or text</li>
            <li><span class="font-semibold text-gray-700">Step 2</span> — Click the PDF to place (add multiple texts anytime)</li>
            <li><span class="font-semibold text-gray-700">Step 3</span> — Apply when ready</li>
        </ol>

        <div class="rounded-lg border border-gray-100 p-3">
            <h3 class="mb-2 font-semibold text-gray-800">1. Draw <span class="font-normal text-gray-500">(optional)</span></h3>
            <div class="rounded-lg border border-gray-300 bg-white">
                <canvas x-ref="pad" width="400" height="160" class="w-full cursor-crosshair touch-none rounded-lg bg-white"></canvas>
            </div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="clearDrawing()" class="text-sm text-gray-600 hover:text-gray-800">Clear pad</button>
                    <button type="button" x-show="drawPlacement" x-cloak @click="clearDrawingFromPage()" class="text-sm text-emerald-700 hover:text-emerald-900">Remove drawing from page</button>
                </div>
                <span class="text-xs text-gray-400" x-show="!hasDrawingInk()">No ink yet</span>
                <span class="text-xs font-medium text-emerald-600" x-show="hasDrawingInk()">Ready to place</span>
            </div>
        </div>

        <div class="rounded-lg border border-gray-100 p-3">
            <h3 class="mb-2 font-semibold text-gray-800">2. Typed text <span class="font-normal text-gray-500">(optional)</span></h3>
            <p class="mb-2 text-xs text-gray-500">Add as many text labels as you need — place each one, then type the next.</p>
            <label for="sig-text" class="mb-1 block text-sm font-medium text-gray-700">Compose text</label>
            <textarea id="sig-text" x-model="typedText" rows="2" maxlength="500" placeholder="e.g. Approved, date, title"
                      class="mb-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                      @input="renderTypedSignature()"></textarea>
            <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block text-sm text-gray-700">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Style</span>
                    <select x-model="textFontPreset" @change="renderTypedSignature()"
                            class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm">
                        <option value="script">Script</option>
                        <option value="serif">Serif</option>
                        <option value="sans">Sans</option>
                        <option value="mono">Mono</option>
                    </select>
                </label>
                <label class="block text-sm text-gray-700">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Text size</span>
                    <input type="number" min="14" max="72" step="1" x-model.number="textFontSize" @input="renderTypedSignature()"
                           class="w-full rounded-lg border border-gray-300 px-2 py-2 text-right text-sm tabular-nums">
                </label>
            </div>
            <div class="rounded-lg border border-gray-300 bg-white">
                <canvas x-ref="textCanvas" width="400" height="160" class="w-full touch-none rounded-lg bg-white"></canvas>
            </div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="clearTypedDraft()" class="text-sm text-gray-600 hover:text-gray-800">Clear draft</button>
                    <button type="button" x-show="hasTypedDraft()" x-cloak @click="armTypedPlacement()"
                            class="rounded border border-violet-300 bg-violet-50 px-2 py-0.5 text-sm font-medium text-violet-900 hover:bg-violet-100">
                        Place on PDF
                    </button>
                </div>
                <span class="text-xs text-gray-400" x-show="!hasTypedDraft()">No draft yet</span>
                <span class="text-xs font-medium text-violet-700" x-show="hasTypedDraft()">Ready — click the PDF</span>
            </div>

            <div x-show="typedTexts.length" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h4 class="text-sm font-medium text-gray-800">
                        Placed texts
                        <span class="tabular-nums text-gray-500" x-text="'(' + typedTexts.length + ')'"></span>
                    </h4>
                    <button type="button" @click="clearAllTypedTexts()" class="text-xs text-violet-800 hover:text-violet-950">Remove all</button>
                </div>
                <ul class="max-h-40 space-y-1.5 overflow-y-auto">
                    <template x-for="(item, index) in typedTexts" :key="item.id">
                        <li>
                            <button type="button"
                                    class="flex w-full items-start gap-2 rounded-lg border px-2.5 py-2 text-left transition"
                                    :class="selectedTypedId === item.id
                                        ? 'border-violet-500 bg-violet-50 ring-1 ring-violet-300'
                                        : 'border-gray-200 bg-white hover:border-violet-300 hover:bg-violet-50/50'"
                                    @click="selectTypedText(item.id)">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[10px] font-semibold text-violet-800 tabular-nums"
                                      x-text="index + 1"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-gray-900" x-text="item.label"></span>
                                    <span class="text-xs text-gray-500">Page <span x-text="item.page"></span></span>
                                </span>
                                <span role="button" tabindex="0"
                                      class="shrink-0 rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-white hover:text-red-600"
                                      title="Remove"
                                      @click.stop.prevent="removeTypedText(item.id)"
                                      @keydown.enter.stop.prevent="removeTypedText(item.id)">&times;</span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div>
            <h3 class="mb-2 font-semibold text-gray-800">3. Size on PDF</h3>
            <div x-show="placeMode === 'drawing'" class="space-y-1">
                <label class="flex items-center justify-between text-sm text-gray-700">
                    <span>Drawing width (mm)</span>
                    <input type="number" min="10" max="300" step="1" x-model.number="drawWidthMm" @input="repositionDrawPixel()"
                           class="w-24 rounded border border-gray-300 px-2 py-1 text-right">
                </label>
            </div>
            <div x-show="placeMode === 'typed'" x-cloak class="space-y-1">
                <label class="flex items-center justify-between text-sm text-gray-700">
                    <span>Selected text width (mm)</span>
                    <input type="number" min="10" max="300" step="1" x-model.number="textWidthMm" @input="repositionSelectedTypedPixel()"
                           class="w-24 rounded border border-gray-300 px-2 py-1 text-right"
                           :disabled="!selectedTypedPlacement()">
                </label>
                <p class="text-xs text-gray-400" x-show="!selectedTypedPlacement()">Select a placed text to resize, or set width before placing.</p>
            </div>
            <div x-show="placeMode === 'logo'" x-cloak class="space-y-1">
                <label class="flex items-center justify-between text-sm text-gray-700">
                    <span>Logo width (mm)</span>
                    <input type="number" min="5" max="300" step="1" x-model.number="logoWidthMm" @input="repositionLogoPixel()"
                           class="w-24 rounded border border-gray-300 px-2 py-1 text-right">
                </label>
                <p class="text-xs text-gray-400" x-show="!logoData">Upload a logo in step 4 to adjust width.</p>
            </div>
        </div>

        <div class="rounded-lg border border-gray-100 bg-gray-50/80 p-3">
            <h3 class="mb-2 font-semibold text-gray-800">4. Logo <span class="font-normal text-gray-500">(optional)</span></h3>
            <p class="mb-2 text-xs text-gray-500">PNG, JPEG, WebP, or GIF. Choose &ldquo;Logo&rdquo; above and click the PDF to position it.</p>
            <input type="file" x-ref="logoFile" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif"
                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-sky-600 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-sky-700"
                   @change="loadLogo($event)">
            <div x-show="logoData" class="mt-2 flex flex-wrap items-center gap-2">
                <img :src="logoData" alt="" class="h-12 max-w-[8rem] rounded border border-gray-200 bg-white object-contain p-1">
                <button type="button" x-show="logoPlacement" x-cloak @click="clearLogoFromPage()" class="text-xs text-sky-800 hover:text-sky-950">Remove logo from page</button>
                <button type="button" @click="clearLogo()" class="text-xs text-gray-600 hover:text-gray-900">Remove logo file</button>
            </div>
        </div>

        <form @submit.prevent="submit()" class="border-t border-gray-100 pt-2">
            <button type="submit"
                    :disabled="!canSubmit() || submitting"
                    class="w-full rounded-lg bg-emerald-600 py-3 font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span x-show="!submitting" x-text="guestMode ? 'Apply my signature' : 'Apply to PDF'"></span>
                <span x-show="submitting">Applying&hellip;</span>
            </button>
            <p class="mt-2 text-xs text-red-600" x-show="errorMessage" x-text="errorMessage"></p>
            <p class="mt-2 text-xs text-gray-500" x-show="!canSubmit() && !errorMessage">Place at least one drawing, typed text, or logo on the PDF to continue.</p>
        </form>

        @unless ($guestMode)
            <a href="{{ route('pdf.show', $document) }}" class="block text-center text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        @endunless
    </aside>
</div>
</div>

@if ($guestMode)
</div>
@endif
@endsection

@push('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
@endpush

@push('scripts')
<script>
    function clientFromEvent(event) {
        if (event.touches && event.touches.length > 0) {
            return { clientX: event.touches[0].clientX, clientY: event.touches[0].clientY };
        }
        if (event.changedTouches && event.changedTouches.length > 0) {
            return { clientX: event.changedTouches[0].clientX, clientY: event.changedTouches[0].clientY };
        }

        return { clientX: event.clientX, clientY: event.clientY };
    }

    function canvasDeviceCoords(canvas, clientX, clientY) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;

        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY,
        };
    }

    function inviteSignersForm() {
        return {
            signers: [{ name: '', email: '' }],
            addSigner() {
                this.signers.push({ name: '', email: '' });
            },
            removeSigner(index) {
                if (this.signers.length <= 1) {
                    return;
                }
                this.signers.splice(index, 1);
            },
        };
    }

    function signer({ streamUrl, submitUrl, csrf, totalPages, guestMode = false }) {
        const PT_PER_MM = 72 / 25.4;
        const RASTER_ASPECT = 0.4;

        const FONT_PRESETS = {
            script: '"Segoe Script", "Brush Script MT", "Apple Chancery", cursive',
            serif: 'Georgia, "Times New Roman", serif',
            sans: 'system-ui, -apple-system, "Segoe UI", sans-serif',
            mono: 'ui-monospace, SFMono-Regular, Menlo, Monaco, monospace',
        };

        let pdfDoc = null;
        let renderTask = null;
        let dragMoveHandler = null;
        let dragEndHandler = null;

        return {
            streamUrl, submitUrl, csrf, guestMode,
            totalPages,
            page: 1,
            pageViewport: null,

            hasInk: false,
            drawingData: '',

            typedText: '',
            textFontPreset: 'script',
            textFontSize: 36,
            typedSignatureData: '',
            typedTexts: [],
            selectedTypedId: null,
            nextTypedId: 1,

            drawPlacement: null,
            drawWidthMm: 60,

            textWidthMm: 60,

            placeMode: 'drawing',
            dragTarget: null,

            logoData: '',
            logoNaturalW: 1,
            logoNaturalH: 1,
            logoPlacement: null,
            logoWidthMm: 35,

            dragMode: null,
            dragOffsetX: 0,
            dragOffsetY: 0,

            submitting: false,
            errorMessage: '',

            preferPlaceMode() {
                if (this.hasDrawingInk()) {
                    return 'drawing';
                }
                if (this.canPlaceTyped()) {
                    return 'typed';
                }
                if (this.logoData) {
                    return 'logo';
                }

                return 'drawing';
            },

            hasDrawingInk() {
                return this.hasInk;
            },

            hasTypedDraft() {
                return this.typedText.trim().length > 0 && Boolean(this.typedSignatureData);
            },

            canPlaceTyped() {
                return this.hasTypedDraft() || this.typedTexts.length > 0;
            },

            typedTextsOnPage() {
                return this.typedTexts.filter((item) => item.page === this.page);
            },

            selectedTypedPlacement() {
                if (this.selectedTypedId === null) {
                    return null;
                }

                return this.typedTexts.find((item) => item.id === this.selectedTypedId) || null;
            },

            typedBoxClass(item) {
                const selected = this.selectedTypedId === item.id;
                const grabbing = this.dragMode === 'move' && this.dragTarget === 'typed' && this.selectedTypedId === item.id;
                const border = selected ? 'border-violet-600 ring-2 ring-violet-300' : 'border-violet-500/80';
                const cursor = grabbing ? 'cursor-grabbing' : 'cursor-grab';

                return `${border} ${cursor}`;
            },

            armTypedPlacement() {
                if (!this.hasTypedDraft()) {
                    return;
                }
                this.placeMode = 'typed';
            },

            selectTypedText(id) {
                this.selectedTypedId = id;
                this.placeMode = 'typed';
                const item = this.typedTexts.find((t) => t.id === id);
                if (item) {
                    this.textWidthMm = item.widthMm;
                    if (item.page !== this.page) {
                        this.page = item.page;
                        this.render();
                    }
                }
            },

            removeTypedText(id) {
                this.typedTexts = this.typedTexts.filter((item) => item.id !== id);
                if (this.selectedTypedId === id) {
                    this.selectedTypedId = this.typedTexts.length
                        ? this.typedTexts[this.typedTexts.length - 1].id
                        : null;
                    const selected = this.selectedTypedPlacement();
                    if (selected) {
                        this.textWidthMm = selected.widthMm;
                    }
                }
                this.handleDragEnd();
                if (!this.canPlaceTyped() && this.placeMode === 'typed') {
                    this.placeMode = this.preferPlaceMode();
                }
            },

            clearAllTypedTexts() {
                this.typedTexts = [];
                this.selectedTypedId = null;
                this.handleDragEnd();
                if (!this.hasTypedDraft() && this.placeMode === 'typed') {
                    this.placeMode = this.preferPlaceMode();
                }
            },

            clearTypedDraft() {
                this.typedText = '';
                if (this.$refs.textCanvas) {
                    const tc = this.$refs.textCanvas.getContext('2d');
                    tc.clearRect(0, 0, this.$refs.textCanvas.width, this.$refs.textCanvas.height);
                }
                this.typedSignatureData = '';
                if (!this.typedTexts.length && this.placeMode === 'typed') {
                    this.placeMode = this.preferPlaceMode();
                }
            },

            placeTypedAt(pixelX, pixelY) {
                if (!this.hasTypedDraft() || !this.pageViewport) {
                    return;
                }
                if (this.typedTexts.length >= 20) {
                    this.errorMessage = 'You can place up to 20 text labels.';

                    return;
                }
                const scale = this.pageViewport.scale;
                const widthPx = this.textWidthMm * PT_PER_MM * scale;
                const id = this.nextTypedId++;
                const item = {
                    id,
                    label: this.typedText.trim().slice(0, 48),
                    dataUrl: this.typedSignatureData,
                    page: this.page,
                    pixelX,
                    pixelY,
                    pixelWidth: widthPx,
                    pixelHeight: widthPx * RASTER_ASPECT,
                    widthMm: Math.min(300, Math.max(10, this.textWidthMm)),
                };
                this.typedTexts.push(item);
                this.selectedTypedId = id;
                this.clampTypedPlacement(item);
                this.errorMessage = '';
            },

            async init() {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                const response = await fetch(this.streamUrl, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error('Could not load PDF');
                }
                const buffer = await response.arrayBuffer();
                pdfDoc = await window.pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                this.totalPages = pdfDoc.numPages;
                await this.render();
                this.setupPad();
                this.$nextTick(() => {
                    this.renderTypedSignature();
                });
            },

            async render() {
                if (!pdfDoc) return;
                if (this.page < 1) this.page = 1;
                if (this.page > this.totalPages) this.page = this.totalPages;

                const page = await pdfDoc.getPage(this.page);
                const canvas = this.$refs.canvas;
                const ctx = canvas.getContext('2d');
                const viewport = page.getViewport({ scale: 1.5 });
                this.pageViewport = {
                    scale: viewport.scale,
                    viewBox: Array.from(viewport.viewBox),
                };
                canvas.width = viewport.width;
                canvas.height = viewport.height;

                if (renderTask) { renderTask.cancel(); }
                renderTask = page.render({ canvasContext: ctx, viewport });
                try { await renderTask.promise; } catch (e) { /* cancelled */ }

                this.repositionDrawPixel();
                this.repositionAllTypedPixels();
                this.repositionLogoPixel();
            },

            prev() { if (this.page > 1) { this.page--; this.render(); } },
            next() { if (this.page < this.totalPages) { this.page++; this.render(); } },

            setupPad() {
                const pad = this.$refs.pad;
                const inst = this;
                const ctx = pad.getContext('2d');
                ctx.lineWidth = 2.2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#0f172a';

                let drawing = false;
                let last = null;

                const pointerPos = (e) => {
                    const clientX = (e.touches && e.touches[0]) ? e.touches[0].clientX : e.clientX;
                    const clientY = (e.touches && e.touches[0]) ? e.touches[0].clientY : e.clientY;
                    const rect = pad.getBoundingClientRect();
                    const scaleX = pad.width / rect.width;
                    const scaleY = pad.height / rect.height;

                    return {
                        x: (clientX - rect.left) * scaleX,
                        y: (clientY - rect.top) * scaleY,
                    };
                };

                const start = (e) => {
                    drawing = true;
                    last = pointerPos(e);
                    e.preventDefault();
                };

                const move = (e) => {
                    if (!drawing) return;
                    const p = pointerPos(e);
                    ctx.beginPath();
                    ctx.moveTo(last.x, last.y);
                    ctx.lineTo(p.x, p.y);
                    ctx.stroke();
                    last = p;
                    this.hasInk = true;
                    this.drawingData = pad.toDataURL('image/png');
                    e.preventDefault();
                };

                const end = () => {
                    drawing = false;
                    last = null;
                };

                pad.addEventListener('mousedown', start);
                pad.addEventListener('mousemove', move);
                window.addEventListener('mouseup', end);
                pad.addEventListener('touchstart', start, { passive: false });
                pad.addEventListener('touchmove', (e) => move(e.touches[0]), { passive: false });
                pad.addEventListener('touchend', end);
            },

            textFontFamily() {
                return FONT_PRESETS[this.textFontPreset] || FONT_PRESETS.script;
            },

            renderTypedSignature() {
                const c = this.$refs.textCanvas;
                if (!c) {
                    return;
                }
                const ctx = c.getContext('2d');
                const w = c.width;
                const h = c.height;
                ctx.clearRect(0, 0, w, h);
                const text = this.typedText.trim();
                if (!text) {
                    this.typedSignatureData = '';

                    return;
                }
                const family = this.textFontFamily();
                let size = Math.min(72, Math.max(14, Number(this.textFontSize) || 36));
                ctx.textBaseline = 'middle';
                ctx.textAlign = 'center';
                ctx.fillStyle = '#0f172a';
                for (let i = 0; i < 20; i++) {
                    ctx.font = `${size}px ${family}`;
                    const tw = ctx.measureText(text).width;
                    if (tw <= w - 24) {
                        break;
                    }
                    size = Math.max(12, size - 2);
                }
                ctx.font = `${size}px ${family}`;
                ctx.fillText(text, w / 2, h / 2);
                this.typedSignatureData = c.toDataURL('image/png');
            },

            pdfCanvasCursorClass() {
                const canDraw = this.hasDrawingInk() && this.drawingData && this.placeMode === 'drawing';
                const canTyped = this.hasTypedDraft() && this.placeMode === 'typed';
                const canLogo = this.logoData && this.placeMode === 'logo';
                if (canDraw || canTyped || canLogo) {
                    return 'cursor-crosshair shadow';
                }

                return 'cursor-default shadow';
            },

            canSubmit() {
                const drawReady = Boolean(this.drawPlacement && this.hasDrawingInk() && this.drawingData);
                const typedReady = this.typedTexts.length > 0;
                const logoReady = Boolean(this.logoPlacement && this.logoData);

                return drawReady || typedReady || logoReady;
            },

            clearDrawingFromPage() {
                this.drawPlacement = null;
                this.handleDragEnd();
            },

            clearLogoFromPage() {
                this.logoPlacement = null;
                this.handleDragEnd();
            },

            clearDrawing() {
                const pad = this.$refs.pad;
                if (pad) {
                    pad.getContext('2d').clearRect(0, 0, pad.width, pad.height);
                }
                this.hasInk = false;
                this.drawingData = '';
                this.drawPlacement = null;
                this.placeMode = this.preferPlaceMode();
                this.handleDragEnd();
            },

            logoAspectRatio() {
                if (this.logoNaturalW > 0 && this.logoNaturalH > 0) {
                    return this.logoNaturalH / this.logoNaturalW;
                }

                return 0.35;
            },

            loadLogo(event) {
                const file = event.target.files && event.target.files[0];
                if (!file) {
                    this.logoData = '';
                    this.logoPlacement = null;
                    this.logoNaturalW = 1;
                    this.logoNaturalH = 1;

                    return;
                }
                if (!file.type || !file.type.startsWith('image/')) {
                    this.errorMessage = 'Please choose an image file.';

                    return;
                }
                this.errorMessage = '';
                const reader = new FileReader();
                reader.onload = (e) => {
                    const url = e.target.result;
                    this.logoData = url;
                    const img = new Image();
                    img.onload = () => {
                        this.logoNaturalW = img.naturalWidth || 1;
                        this.logoNaturalH = img.naturalHeight || 1;
                        if (!this.hasDrawingInk() && !this.canPlaceTyped()) {
                            this.placeMode = 'logo';
                        }
                        this.$nextTick(() => this.repositionLogoPixel());
                    };
                    img.src = url;
                };
                reader.readAsDataURL(file);
            },

            clearLogo() {
                this.logoData = '';
                this.logoPlacement = null;
                this.logoNaturalW = 1;
                this.logoNaturalH = 1;
                if (this.$refs.logoFile) {
                    this.$refs.logoFile.value = '';
                }
                this.placeMode = this.preferPlaceMode();
                this.handleDragEnd();
            },

            onPdfClick(event) {
                if (!this.pageViewport || event.target !== this.$refs.canvas) {
                    return;
                }
                const canvas = this.$refs.canvas;
                const { clientX, clientY } = clientFromEvent(event);
                const { x, y } = canvasDeviceCoords(canvas, clientX, clientY);
                const scale = this.pageViewport.scale;

                if (this.placeMode === 'logo') {
                    if (!this.logoData) {
                        return;
                    }
                    const widthPx = this.logoWidthMm * PT_PER_MM * scale;
                    this.logoPlacement = {
                        page: this.page,
                        pixelX: x,
                        pixelY: y,
                        pixelWidth: widthPx,
                        pixelHeight: widthPx * this.logoAspectRatio(),
                    };
                    this.clampLogoPlacement();

                    return;
                }

                if (this.placeMode === 'typed') {
                    if (!this.hasTypedDraft()) {
                        return;
                    }
                    this.placeTypedAt(x, y);

                    return;
                }

                if (!this.hasDrawingInk() || !this.drawingData) {
                    return;
                }
                const widthPx = this.drawWidthMm * PT_PER_MM * scale;
                this.drawPlacement = {
                    page: this.page,
                    pixelX: x,
                    pixelY: y,
                    pixelWidth: widthPx,
                    pixelHeight: widthPx * RASTER_ASPECT,
                };
                this.clampDrawPlacement();
            },

            drawPlacementBoxStyle() {
                if (!this.drawPlacement) {
                    return '';
                }
                const p = this.drawPlacement;

                return `left:${p.pixelX}px;top:${p.pixelY}px;width:${p.pixelWidth}px;height:${p.pixelHeight}px`;
            },

            typedPlacementBoxStyle(item) {
                if (!item) {
                    return '';
                }

                return `left:${item.pixelX}px;top:${item.pixelY}px;width:${item.pixelWidth}px;height:${item.pixelHeight}px`;
            },

            logoPlacementBoxStyle() {
                if (!this.logoPlacement) {
                    return '';
                }
                const p = this.logoPlacement;

                return `left:${p.pixelX}px;top:${p.pixelY}px;width:${p.pixelWidth}px;height:${p.pixelHeight}px`;
            },

            clampDrawPlacement() {
                if (!this.drawPlacement) return;
                const canvas = this.$refs.canvas;
                if (!canvas || !canvas.width) return;
                this.drawPlacement.pixelX = Math.max(0, Math.min(canvas.width - this.drawPlacement.pixelWidth, this.drawPlacement.pixelX));
                this.drawPlacement.pixelY = Math.max(0, Math.min(canvas.height - this.drawPlacement.pixelHeight, this.drawPlacement.pixelY));
            },

            clampTypedPlacement(item = null) {
                const target = item || this.selectedTypedPlacement();
                if (!target) return;
                const canvas = this.$refs.canvas;
                if (!canvas || !canvas.width) return;
                target.pixelX = Math.max(0, Math.min(canvas.width - target.pixelWidth, target.pixelX));
                target.pixelY = Math.max(0, Math.min(canvas.height - target.pixelHeight, target.pixelY));
            },

            clampLogoPlacement() {
                if (!this.logoPlacement) return;
                const canvas = this.$refs.canvas;
                if (!canvas || !canvas.width) return;
                this.logoPlacement.pixelX = Math.max(0, Math.min(canvas.width - this.logoPlacement.pixelWidth, this.logoPlacement.pixelX));
                this.logoPlacement.pixelY = Math.max(0, Math.min(canvas.height - this.logoPlacement.pixelHeight, this.logoPlacement.pixelY));
            },

            syncDrawWidthMmFromPlacement() {
                if (!this.drawPlacement || !this.pageViewport) return;
                const scale = this.pageViewport.scale;
                const wMm = this.drawPlacement.pixelWidth / (PT_PER_MM * scale);
                this.drawWidthMm = Math.round(Math.min(300, Math.max(10, wMm)) * 100) / 100;
            },

            syncTextWidthMmFromPlacement() {
                const target = this.selectedTypedPlacement();
                if (!target || !this.pageViewport) return;
                const scale = this.pageViewport.scale;
                const wMm = target.pixelWidth / (PT_PER_MM * scale);
                target.widthMm = Math.round(Math.min(300, Math.max(10, wMm)) * 100) / 100;
                this.textWidthMm = target.widthMm;
            },

            syncLogoWidthMmFromPlacement() {
                if (!this.logoPlacement || !this.pageViewport) return;
                const scale = this.pageViewport.scale;
                const wMm = this.logoPlacement.pixelWidth / (PT_PER_MM * scale);
                this.logoWidthMm = Math.round(Math.min(300, Math.max(5, wMm)) * 100) / 100;
            },

            repositionDrawPixel() {
                if (!this.drawPlacement || !this.pageViewport) return;
                const widthPx = this.drawWidthMm * PT_PER_MM * this.pageViewport.scale;
                this.drawPlacement.pixelWidth = widthPx;
                this.drawPlacement.pixelHeight = widthPx * RASTER_ASPECT;
                this.clampDrawPlacement();
            },

            repositionSelectedTypedPixel() {
                const target = this.selectedTypedPlacement();
                if (!target || !this.pageViewport) return;
                const widthPx = this.textWidthMm * PT_PER_MM * this.pageViewport.scale;
                target.widthMm = Math.min(300, Math.max(10, this.textWidthMm));
                target.pixelWidth = widthPx;
                target.pixelHeight = widthPx * RASTER_ASPECT;
                this.clampTypedPlacement(target);
            },

            repositionAllTypedPixels() {
                if (!this.pageViewport) return;
                const scale = this.pageViewport.scale;
                this.typedTexts.forEach((item) => {
                    if (item.page !== this.page) {
                        return;
                    }
                    const widthPx = item.widthMm * PT_PER_MM * scale;
                    item.pixelWidth = widthPx;
                    item.pixelHeight = widthPx * RASTER_ASPECT;
                    this.clampTypedPlacement(item);
                });
            },

            repositionLogoPixel() {
                if (!this.logoPlacement || !this.pageViewport) return;
                const widthPx = this.logoWidthMm * PT_PER_MM * this.pageViewport.scale;
                this.logoPlacement.pixelWidth = widthPx;
                this.logoPlacement.pixelHeight = widthPx * this.logoAspectRatio();
                this.clampLogoPlacement();
            },

            attachDragListeners() {
                if (dragMoveHandler !== null) return;
                const inst = this;
                dragMoveHandler = (e) => { inst.handleDragMove(e); };
                dragEndHandler = () => { inst.handleDragEnd(); };
                window.addEventListener('mousemove', dragMoveHandler);
                window.addEventListener('mouseup', dragEndHandler);
                window.addEventListener('touchmove', dragMoveHandler, { passive: false });
                window.addEventListener('touchend', dragEndHandler);
                window.addEventListener('touchcancel', dragEndHandler);
            },

            handleDragEnd() {
                if (dragMoveHandler === null) return;
                window.removeEventListener('mousemove', dragMoveHandler);
                window.removeEventListener('mouseup', dragEndHandler);
                window.removeEventListener('touchmove', dragMoveHandler);
                window.removeEventListener('touchend', dragEndHandler);
                window.removeEventListener('touchcancel', dragEndHandler);
                dragMoveHandler = null;
                dragEndHandler = null;
                this.dragMode = null;
                this.dragTarget = null;
            },

            startMoveDrawing(event) {
                if (!this.drawPlacement || !this.pageViewport) return;
                const canvas = this.$refs.canvas;
                const { clientX, clientY } = clientFromEvent(event);
                const { x, y } = canvasDeviceCoords(canvas, clientX, clientY);
                this.dragMode = 'move';
                this.dragTarget = 'drawing';
                this.dragOffsetX = x - this.drawPlacement.pixelX;
                this.dragOffsetY = y - this.drawPlacement.pixelY;
                this.attachDragListeners();
            },

            startResizeDrawing(event) {
                if (!this.drawPlacement) return;
                this.dragMode = 'resize';
                this.dragTarget = 'drawing';
                this.attachDragListeners();
            },

            startMoveTyped(event, id) {
                const item = this.typedTexts.find((t) => t.id === id);
                if (!item || !this.pageViewport) return;
                this.selectTypedText(id);
                const canvas = this.$refs.canvas;
                const { clientX, clientY } = clientFromEvent(event);
                const { x, y } = canvasDeviceCoords(canvas, clientX, clientY);
                this.dragMode = 'move';
                this.dragTarget = 'typed';
                this.dragOffsetX = x - item.pixelX;
                this.dragOffsetY = y - item.pixelY;
                this.attachDragListeners();
            },

            startResizeTyped(event, id) {
                const item = this.typedTexts.find((t) => t.id === id);
                if (!item) return;
                this.selectTypedText(id);
                this.dragMode = 'resize';
                this.dragTarget = 'typed';
                this.attachDragListeners();
            },

            startMoveLogo(event) {
                if (!this.logoPlacement || !this.pageViewport) return;
                const canvas = this.$refs.canvas;
                const { clientX, clientY } = clientFromEvent(event);
                const { x, y } = canvasDeviceCoords(canvas, clientX, clientY);
                this.dragMode = 'move';
                this.dragTarget = 'logo';
                this.dragOffsetX = x - this.logoPlacement.pixelX;
                this.dragOffsetY = y - this.logoPlacement.pixelY;
                this.attachDragListeners();
            },

            startResizeLogo(event) {
                if (!this.logoPlacement) return;
                this.dragMode = 'resize';
                this.dragTarget = 'logo';
                this.attachDragListeners();
            },

            handleDragMove(event) {
                if (!this.dragMode || !this.pageViewport) return;
                if (event.cancelable && event.type === 'touchmove') event.preventDefault();
                const canvas = this.$refs.canvas;
                const { clientX, clientY } = clientFromEvent(event);
                const { x, y } = canvasDeviceCoords(canvas, clientX, clientY);
                const scale = this.pageViewport.scale;

                if (this.dragTarget === 'logo' && this.logoPlacement) {
                    const minWpx = 5 * PT_PER_MM * scale;
                    const maxWpx = 300 * PT_PER_MM * scale;
                    const aspect = this.logoAspectRatio();
                    if (this.dragMode === 'move') {
                        let nx = x - this.dragOffsetX;
                        let ny = y - this.dragOffsetY;
                        nx = Math.max(0, Math.min(canvas.width - this.logoPlacement.pixelWidth, nx));
                        ny = Math.max(0, Math.min(canvas.height - this.logoPlacement.pixelHeight, ny));
                        this.logoPlacement.pixelX = nx;
                        this.logoPlacement.pixelY = ny;
                    } else if (this.dragMode === 'resize') {
                        let newW = x - this.logoPlacement.pixelX;
                        newW = Math.min(maxWpx, Math.max(minWpx, newW));
                        this.logoPlacement.pixelWidth = newW;
                        this.logoPlacement.pixelHeight = newW * aspect;
                        this.syncLogoWidthMmFromPlacement();
                        this.clampLogoPlacement();
                    }
                    return;
                }

                const rasterTarget = this.dragTarget === 'drawing'
                    ? this.drawPlacement
                    : (this.dragTarget === 'typed' ? this.selectedTypedPlacement() : null);
                if (!rasterTarget) return;

                const minWpx = 10 * PT_PER_MM * scale;
                const maxWpx = 300 * PT_PER_MM * scale;

                if (this.dragMode === 'move') {
                    let nx = x - this.dragOffsetX;
                    let ny = y - this.dragOffsetY;
                    nx = Math.max(0, Math.min(canvas.width - rasterTarget.pixelWidth, nx));
                    ny = Math.max(0, Math.min(canvas.height - rasterTarget.pixelHeight, ny));
                    rasterTarget.pixelX = nx;
                    rasterTarget.pixelY = ny;
                } else if (this.dragMode === 'resize') {
                    let newW = x - rasterTarget.pixelX;
                    newW = Math.min(maxWpx, Math.max(minWpx, newW));
                    rasterTarget.pixelWidth = newW;
                    rasterTarget.pixelHeight = newW * RASTER_ASPECT;
                    if (this.dragTarget === 'drawing') {
                        this.syncDrawWidthMmFromPlacement();
                        this.clampDrawPlacement();
                    } else {
                        this.syncTextWidthMmFromPlacement();
                        this.clampTypedPlacement(rasterTarget);
                    }
                }
            },

            async submit() {
                if (!this.canSubmit()) return;
                this.submitting = true;
                this.errorMessage = '';

                const scale = this.pageViewport.scale;
                const fd = new FormData();
                fd.append('_token', this.csrf);

                if (this.drawPlacement && this.hasDrawingInk() && this.drawingData) {
                    const xPt = this.drawPlacement.pixelX / scale;
                    const yPt = this.drawPlacement.pixelY / scale;
                    fd.append('signature', this.drawingData);
                    fd.append('page', this.drawPlacement.page);
                    fd.append('x', (xPt / PT_PER_MM).toFixed(2));
                    fd.append('y', (yPt / PT_PER_MM).toFixed(2));
                    fd.append('width', String(Math.min(300, Math.max(10, this.drawWidthMm))));
                }

                if (this.typedTexts.length > 0) {
                    this.typedTexts.forEach((item, index) => {
                        const xPt = item.pixelX / scale;
                        const yPt = item.pixelY / scale;
                        fd.append(`typed_texts[${index}][image]`, item.dataUrl);
                        fd.append(`typed_texts[${index}][page]`, String(item.page));
                        fd.append(`typed_texts[${index}][x]`, (xPt / PT_PER_MM).toFixed(2));
                        fd.append(`typed_texts[${index}][y]`, (yPt / PT_PER_MM).toFixed(2));
                        fd.append(`typed_texts[${index}][width]`, String(Math.min(300, Math.max(10, item.widthMm))));
                    });
                }

                if (this.logoPlacement && this.logoData) {
                    const lxPt = this.logoPlacement.pixelX / scale;
                    const lyPt = this.logoPlacement.pixelY / scale;
                    fd.append('logo', this.logoData);
                    fd.append('logo_page', String(this.logoPlacement.page));
                    fd.append('logo_x', (lxPt / PT_PER_MM).toFixed(2));
                    fd.append('logo_y', (lyPt / PT_PER_MM).toFixed(2));
                    fd.append('logo_width', String(Math.min(300, Math.max(5, this.logoWidthMm))));
                }

                try {
                    const res = await fetch(this.submitUrl, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        redirect: 'follow',
                    });

                    if (res.redirected) {
                        window.location.href = res.url;
                        return;
                    }

                    if (res.status === 422) {
                        let message = 'Please check the form and try again.';
                        try {
                            const json = await res.json();
                            const errs = json.errors || {};
                            message = errs.sign?.[0]
                                || errs.signature?.[0]
                                || errs.typed_texts?.[0]
                                || errs['typed_texts.0.image']?.[0]
                                || errs.typed_signature?.[0]
                                || errs.logo?.[0]
                                || Object.values(errs).flat()[0]
                                || json.message
                                || message;
                        } catch {
                            // ignore JSON parse errors
                        }
                        this.errorMessage = message;
                        return;
                    }

                    if (!res.ok) {
                        await res.text();
                        this.errorMessage = 'Apply failed (' + res.status + ').';
                    }
                } catch (e) {
                    this.errorMessage = e.message;
                } finally {
                    this.submitting = false;
                }
            },
        };
    }
</script>
@endpush
