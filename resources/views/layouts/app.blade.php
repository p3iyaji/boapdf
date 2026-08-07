<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <x-boa-theme::styles />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen bg-gradient-to-br from-accent-50 via-canvas-100 to-brand-100/90 font-sans text-canvas-900 antialiased">
    @auth
        @if (request()->routeIs('verification.*'))
            @if (session('success'))
                <div class="mx-auto mt-4 max-w-md rounded-boa-lg border border-success-200/80 bg-success-50/95 px-4 py-3 text-success-950 shadow-sm">
                    {{ session('success') }}
                </div>
            @endif
            @yield('content')
        @else
            @include('partials.sidebar')
            <main class="min-h-screen min-w-0 max-w-full overflow-x-hidden px-4 pb-8 max-lg:pt-[calc(env(safe-area-inset-top,0px)+3.5rem+1rem)] lg:ml-64 lg:px-8 lg:pb-10 lg:pt-8 md:px-6">
                @if (session('success'))
                    <div class="mb-4 rounded-boa-lg border border-success-200/80 bg-success-50/95 px-4 py-3 text-success-950 shadow-sm shadow-brand-950/5 backdrop-blur-sm">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('status'))
                    <div class="mb-4 rounded-boa-lg border border-success-200/80 bg-success-50/95 px-4 py-3 text-success-950 shadow-sm shadow-brand-950/5 backdrop-blur-sm">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-boa-lg border border-danger-200/90 bg-danger-50/95 px-4 py-3 text-danger-900 shadow-sm backdrop-blur-sm">
                        <ul class="list-disc space-y-1 pl-5 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </main>
        @endif
    @else
        @yield('content')
    @endauth
    {{-- Page scripts (Alpine helpers) must load before deferred Alpine CDN runs. --}}
    @stack('scripts')
</body>
</html>
