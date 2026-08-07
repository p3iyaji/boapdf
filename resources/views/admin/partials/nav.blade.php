@php
    $adminLinks = [
        ['route' => 'admin.dashboard', 'label' => 'Overview', 'match' => 'admin.dashboard'],
        ['route' => 'admin.users.index', 'label' => 'Users', 'match' => 'admin.users.*'],
        ['route' => 'admin.documents.index', 'label' => 'Documents', 'match' => 'admin.documents.*'],
        ['route' => 'admin.signature-requests.index', 'label' => 'Signatures', 'match' => 'admin.signature-requests.*'],
        ['route' => 'admin.conversion-logs.index', 'label' => 'Conversion logs', 'match' => 'admin.conversion-logs.*'],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-2 border-b border-brand-900/10 pb-4" aria-label="Admin sections">
    @foreach ($adminLinks as $link)
        @php($active = request()->routeIs($link['match']))
        <a href="{{ route($link['route']) }}"
            class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $active ? 'bg-brand-900 text-accent-50' : 'bg-white/80 text-brand-900 hover:bg-brand-50' }}">
            {{ $link['label'] }}
        </a>
    @endforeach
</nav>
