<div x-data="{
        sidebarOpen: false,
        isLg: typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches,
        syncBreakpoint() {
            this.isLg = window.matchMedia('(min-width: 1024px)').matches;
            if (this.isLg) {
                this.sidebarOpen = false;
            }
        },
    }" x-init="window.addEventListener('resize', () => { syncBreakpoint() }); syncBreakpoint()"
    x-effect="if (typeof document !== 'undefined') { document.body.style.overflow = (sidebarOpen && !isLg) ? 'hidden' : '' }"
    @keydown.escape.window="sidebarOpen = false">
    {{-- Mobile top bar --}}
    <header
        class="fixed left-0 right-0 top-0 z-50 border-b border-brand-950/50 bg-gradient-to-r from-brand-950 via-brand-900 to-canvas-950 pt-[env(safe-area-inset-top,0px)] shadow-lg shadow-brand-950/40 backdrop-blur-md lg:hidden">
        <div class="flex h-14 items-center gap-2 px-3">
            <button type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-accent-100/90 hover:bg-white/10 active:bg-white/15"
                @click="sidebarOpen = !sidebarOpen" :aria-expanded="sidebarOpen" aria-controls="app-sidebar"
                aria-label="Toggle navigation menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
            <a href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center justify-center gap-2.5">
                <x-boa-theme::mark size="sm" />
                <span
                    class="truncate font-display text-base font-semibold tracking-wide text-accent-50">{{ config('app.name') }}</span>
            </a>
            <span class="h-11 w-11 shrink-0" aria-hidden="true"></span>
        </div>
    </header>

    {{-- Dim overlay when drawer is open --}}
    <div x-show="sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed bottom-0 left-0 right-0 z-30 bg-canvas-950/55 max-lg:top-[calc(3.5rem+env(safe-area-inset-top,0px))] lg:hidden"
        @click="sidebarOpen = false" x-cloak></div>

    <aside id="app-sidebar"
        class="fixed left-0 z-40 flex w-[min(20rem,calc(100vw-0.75rem))] flex-col border-r border-accent-500/15 bg-gradient-to-b from-brand-950 via-brand-900 to-canvas-950 shadow-2xl shadow-black/40 transition-transform duration-300 ease-out max-lg:top-[calc(3.5rem+env(safe-area-inset-top,0px))] max-lg:h-[calc(100dvh-3.5rem-env(safe-area-inset-top,0px)-env(safe-area-inset-bottom,0px))] lg:top-0 lg:h-screen lg:w-64 lg:translate-x-0 lg:border-0 lg:shadow-xl"
        :class="!isLg && (sidebarOpen ? 'translate-x-0' : '-translate-x-full')" role="navigation"
        aria-label="Main navigation">
        <div class="flex flex-1 flex-col overflow-y-auto overscroll-contain p-5 pt-6 lg:p-6">
            <a href="{{ route('dashboard') }}"
                class="mb-8 -m-2 hidden rounded-xl p-2 transition hover:bg-white/5 lg:flex"
                @click="sidebarOpen = false">
                <x-boa-theme::brand size="md" :show-tagline="true" class="text-accent-100" />
            </a>

            <div class="mb-6 border-b border-brand-800/80 pb-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-accent-300 to-accent-600 text-sm font-bold text-brand-950 shadow-md ring-2 ring-accent-200/40">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-canvas-50">{{ Auth::user()->name }}</p>
                        <p class="truncate text-sm text-brand-200/80">{{ Auth::user()->email }}</p>
                    </div>
                </div>
            </div>

            @php
                $navItem = function (string $route, string $label, string $iconPath) {
                    $active = request()->routeIs($route) || request()->routeIs($route . '.*');
                    $base = 'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 transition';
                    $classes = $active
                        ? 'bg-accent-400/15 font-semibold text-accent-50 ring-1 ring-accent-400/25'
                        : 'text-brand-100/90 hover:bg-white/5 hover:text-accent-100 active:bg-white/10';

                    return ['classes' => $base . ' ' . $classes, 'icon' => $iconPath, 'label' => $label];
                };
            @endphp

            <nav class="space-y-1">
                @php($d = $navItem('dashboard', 'Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'))
                <a href="{{ route('dashboard') }}" class="{{ $d['classes'] }}" @click="sidebarOpen = false">
                    <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $d['icon'] }}">
                        </path>
                    </svg>
                    <span>{{ $d['label'] }}</span>
                </a>

                @php($i = $navItem('pdf.index', 'My PDFs', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'))
                <a href="{{ route('pdf.index') }}" class="{{ $i['classes'] }}" @click="sidebarOpen = false">
                    <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $i['icon'] }}">
                        </path>
                    </svg>
                    <span>{{ $i['label'] }}</span>
                </a>

                @php($m = $navItem('pdf.merge.create', 'Merge', 'M8 7l4-4m0 0l4 4m-4-4v18'))
                <a href="{{ route('pdf.merge.create') }}" class="{{ $m['classes'] }}" @click="sidebarOpen = false">
                    <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $m['icon'] }}">
                        </path>
                    </svg>
                    <span>{{ $m['label'] }}</span>
                </a>

                @php($c = $navItem('pdf.compress.create', 'Compress', 'M19 14l-7 7m0 0l-7-7m7 7V3'))
                <a href="{{ route('pdf.compress.create') }}" class="{{ $c['classes'] }}" @click="sidebarOpen = false">
                    <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $c['icon'] }}">
                        </path>
                    </svg>
                    <span>{{ $c['label'] }}</span>
                </a>

                {{-- @php($cv = $navItem('pdf.convert.create', 'Convert', 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582
                9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'))
                <a href="{{ route('pdf.convert.create') }}" class="{{ $cv['classes'] }}" @click="sidebarOpen = false">
                    <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cv['icon'] }}">
                        </path>
                    </svg>
                    <span>{{ $cv['label'] }}</span>
                </a> --}}

                @if (Auth::user()->isAdmin())
                    @php($aActive = request()->routeIs('admin.*'))
                    @php($aClasses = 'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 transition '.($aActive ? 'bg-accent-400/15 font-semibold text-accent-50 ring-1 ring-accent-400/25' : 'text-brand-100/90 hover:bg-white/5 hover:text-accent-100 active:bg-white/10'))
                    <a href="{{ route('admin.dashboard') }}" class="{{ $aClasses }}" @click="sidebarOpen = false">
                        <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                            </path>
                        </svg>
                        <span>Admin</span>
                    </a>
                @endif
            </nav>
        </div>

        <div class="border-t border-brand-800/80 p-4 lg:p-6"
            style="padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px));">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-danger-400/25 bg-danger-950/35 px-4 py-2.5 font-medium text-danger-200 transition hover:bg-danger-900/45 active:bg-danger-900/60">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                        </path>
                    </svg>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>
</div>