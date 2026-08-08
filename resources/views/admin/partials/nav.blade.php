@php
    $adminLinks = [
        ['route' => 'admin.dashboard', 'label' => 'Overview', 'match' => 'admin.dashboard'],
        ['route' => 'admin.users.index', 'label' => 'Users', 'match' => 'admin.users.*'],
        ['route' => 'admin.documents.index', 'label' => 'Documents', 'match' => 'admin.documents.*'],
        ['route' => 'admin.signature-requests.index', 'label' => 'Signatures', 'match' => 'admin.signature-requests.*'],
        ['route' => 'admin.conversion-logs.index', 'label' => 'Conversion logs', 'match' => 'admin.conversion-logs.*'],
        ['route' => 'admin.audit-logs.index', 'label' => 'Audit logs', 'match' => 'admin.audit-logs.*'],
        ['route' => 'admin.password.edit', 'label' => 'Change password', 'match' => 'admin.password.*'],
    ];
@endphp

<nav class="mb-6 flex flex-wrap items-center gap-2 border-b border-brand-900/10 pb-4" aria-label="Admin sections">
    @foreach ($adminLinks as $link)
        @php($active = request()->routeIs($link['match']))
        <a href="{{ route($link['route']) }}"
            class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $active ? 'bg-brand-900 text-accent-50' : 'bg-white/80 text-brand-900 hover:bg-brand-50' }}">
            {{ $link['label'] }}
        </a>
    @endforeach

    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
        @csrf
        <button type="submit"
            class="rounded-lg border border-danger-400/30 bg-danger-50 px-3 py-2 text-sm font-medium text-danger-800 transition hover:bg-danger-100">
            Logout
        </button>
    </form>
</nav>
