@php
    $isGuruPortal = ($portalLabel ?? null) === 'Portal Guru';
    $isWaliPortal = ($portalLabel ?? null) === 'Portal Wali Santri';
    $isManagementPortal = ($portalLabel ?? null) === 'Portal Manajemen';
    $portalHomeUrl = $isGuruPortal
        ? route('guru.dashboard')
        : ($isWaliPortal ? route('wali.dashboard') : ($isManagementPortal ? url('/admin') : url('/')));
    $canAccessAttendance = $isGuruPortal && (auth()->user()?->canAccessAttendance() ?? false);
    $isTasmiExaminer = $isGuruPortal && (auth()->user()?->isTasmiExaminer() ?? false);
    $isHomeroomTeacher = $isGuruPortal && (auth()->user()?->canAccessAttendance() ?? false);
    $guruNavItems = [
        ['label' => 'Beranda', 'href' => route('guru.dashboard'), 'match' => ['guru.dashboard']],
        ['label' => 'Input Nilai', 'href' => route('guru.diniyyah-scores.index'), 'match' => ['guru.diniyyah-scores.*']],
        ['label' => 'Jurnal', 'href' => route('guru.diniyyah-journals.index'), 'match' => ['guru.diniyyah-journals.*', 'guru.diniyyah-tafsir-journals.*', 'guru.diniyyah-substitute-journals.*', 'guru.diniyyah-substitute-tafsir-journals.*']],
        ['label' => 'Tahfidz', 'href' => route('guru.tahfidz.index'), 'match' => ['guru.tahfidz.*']],
        ['label' => 'Kalender', 'href' => route('guru.calendar'), 'match' => ['guru.calendar']],
    ];
    if ($isTasmiExaminer) {
        $guruNavItems[] = ['label' => 'Tasmi\'', 'href' => route('guru.tasmi.index'), 'match' => ['guru.tasmi.index', 'guru.tasmi.create', 'guru.tasmi.store', 'guru.tasmi.records', 'guru.tasmi.edit', 'guru.tasmi.update', 'guru.tasmi.destroy']];
    }
    if ($isHomeroomTeacher) {
        $guruNavItems[] = ['label' => 'Tasmi\' Kelas Saya', 'href' => route('guru.tasmi-wali.index'), 'match' => ['guru.tasmi-wali.*']];
        $guruNavItems[] = ['label' => 'Monitoring Jurnal Kelas', 'href' => route('wali.diniyyah-journals.index'), 'match' => ['wali.diniyyah-journals.*']];
    }
    if ($canAccessAttendance) {
        array_splice($guruNavItems, 1, 0, [[
            'label' => 'Presensi', 'href' => route('attendance.index'), 'match' => ['attendance.*'],
        ]]);
    }
    $waliNavItems = [
        ['label' => 'Beranda', 'href' => route('wali.dashboard'), 'match' => ['wali.dashboard']],
        ['label' => 'Rapor', 'href' => route('wali.dashboard').'#rapor', 'match' => ['report-cards.*']],
        ['label' => 'Tahfidz', 'href' => route('wali.tahfidz'), 'match' => ['wali.tahfidz']],
        ['label' => 'Kalender', 'href' => route('wali.calendar'), 'match' => ['wali.calendar']],
    ];
    $managementNavItems = [
        ['label' => 'Dashboard Admin', 'href' => url('/admin'), 'match' => ['filament.admin.pages.dashboard']],
        ['label' => 'Monitoring Diniyyah', 'href' => route('diniyyah.monitoring.index'), 'match' => ['diniyyah.monitoring.*']],
        isset($snapshot)
            ? ['label' => 'Leger / Rapor', 'href' => route('diniyyah.ledger.show', $snapshot), 'match' => ['diniyyah.ledger.*']]
            : ['label' => 'Leger / Rapor', 'href' => route('filament.admin.resources.diniyyah-ledger-snapshots.index'), 'match' => ['filament.admin.resources.diniyyah-ledger-snapshots.*']],
    ];
    $portalNavItems = $isGuruPortal ? $guruNavItems : ($isWaliPortal ? $waliNavItems : ($isManagementPortal ? $managementNavItems : []));
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal' }} - SIAKAD Griya Qur'an</title>
    <meta name="description" content="Sistem Informasi Akademik Griya Qur'an Tunas Ilmu">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
    @stack('head')
</head>
<body class="app-shell overflow-x-hidden text-slate-800 antialiased">
    <div class="pointer-events-none fixed inset-0 -z-10 bg-grid opacity-60"></div>

    <aside class="portal-sidebar" aria-label="Menu {{ $portalLabel ?? 'portal' }}">
        <a href="{{ $portalHomeUrl }}" class="portal-sidebar-brand">
            <span class="portal-sidebar-brand-mark">GQ</span>
            <span>
                <strong class="block text-sm leading-tight">Griya Qur'an</strong>
                <span class="mt-1 block font-mono text-[9px] font-bold tracking-[.12em] text-slate-500">{{ $portalLabel ?? 'SIAKAD' }}</span>
            </span>
        </a>

        <p class="portal-sidebar-section">Product line</p>
        <nav aria-label="Navigasi {{ $portalLabel ?? 'portal' }}">
            @foreach($portalNavItems as $item)
                @php($isActive = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                <a href="{{ $item['href'] }}" class="portal-sidebar-link {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>
                    <span class="flex h-7 w-7 items-center justify-center rounded-md border border-line bg-white" aria-hidden="true"><span class="h-2 w-2 rounded-full {{ $isActive ? 'bg-neon' : 'bg-slate-300' }}"></span></span>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        @if($isGuruPortal && isset($navLinks))
            <p class="portal-sidebar-section">Lainnya</p>
            <div class="space-y-1">{{ $navLinks }}</div>
        @endif
        <div class="mt-auto pt-8">
            <div class="rounded-xl border border-slate-800 bg-ink p-4 text-white">
                <p class="m-0 font-mono text-[10px] font-bold tracking-[.12em] text-neon">SIAKAD GQ</p>
                <p class="mb-0 mt-2 text-xs leading-5 text-slate-300">Informasi akademik yang dekat dengan pekerjaan Anda.</p>
            </div>
        </div>
    </aside>

    <div class="portal-main">
        <nav class="portal-nav" aria-label="Navigasi utama">
            <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-3 px-4 py-2 sm:px-6">
                <a href="{{ $portalHomeUrl }}" class="flex shrink-0 items-center gap-2 lg:hidden">
                    <span class="portal-sidebar-brand-mark !h-8 !w-8 !text-[10px]">GQ</span>
                    <span class="hidden sm:block"><strong class="block text-sm leading-none text-ink">Griya Qur'an</strong><span class="mt-1 block font-mono text-[9px] font-bold tracking-[.1em] text-slate-500">{{ $portalLabel ?? 'SIAKAD' }}</span></span>
                </a>
                <div class="hidden min-w-0 flex-1 items-center gap-3 lg:flex">
                    <span class="eyebrow">{{ $portalLabel ?? 'SIAKAD' }}</span>
                    @isset($breadcrumb)<span class="truncate border-l border-line pl-3 text-xs font-bold text-slate-600">{{ $breadcrumb }}</span>@endisset
                </div>

                <div class="ml-auto flex items-center gap-2" data-notification-root data-feed-url="{{ route('notifications.feed') }}" data-read-url-template="{{ route('notifications.read', '__ID__') }}" data-mark-all-url="{{ route('notifications.read-all') }}">
                    @isset($navLinks)
                        <div class="hidden items-center gap-1 lg:flex">{{ $navLinks }}</div>
                    @endisset
                    <div class="relative">
                        <button type="button" class="relative flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-white text-ink transition hover:border-ink" aria-label="Notifikasi" aria-haspopup="dialog" aria-expanded="false" data-notification-toggle>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                            <span class="absolute -right-1 -top-1 hidden min-w-4 rounded-full bg-ink px-1 py-0.5 font-mono text-[9px] font-black text-neon" data-notification-badge>0</span>
                        </button>
                        <section class="notification-dropdown absolute right-0 top-12 z-60" role="dialog" aria-label="Notifikasi" hidden data-notification-panel>
                            <header class="flex items-center justify-between border-b border-line px-4 py-3"><strong class="text-xs text-ink">Notifikasi</strong><button type="button" class="font-mono text-[10px] font-bold text-slate-500 underline decoration-neon decoration-2 underline-offset-4" data-notification-mark-all>Tandai semua dibaca</button></header>
                            <div class="max-h-96 overflow-y-auto" data-notification-list><p class="px-5 py-8 text-center text-xs font-bold text-slate-400">Memuat...</p></div>
                            <footer class="border-t border-line px-4 py-3 text-center"><a href="{{ route('notifications.index') }}" class="font-mono text-[10px] font-bold text-ink underline decoration-neon decoration-2 underline-offset-4">Lihat semua notifikasi →</a></footer>
                        </section>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">@csrf<button type="submit" class="btn btn-outline btn-sm">Keluar</button></form>
                    <details class="portal-mobile-menu relative lg:hidden" data-portal-menu>
                        <summary class="portal-menu-summary flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-line bg-white px-3 text-xs font-extrabold text-ink">Menu <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></summary>
                        <div class="absolute right-0 top-12 z-50 w-72 rounded-xl border border-line bg-white p-2 shadow-2xl shadow-slate-950/10" role="dialog" aria-label="Menu portal">
                            <p class="px-3 pb-2 pt-1 font-mono text-[10px] font-bold tracking-[.1em] text-slate-500">{{ $portalLabel ?? 'SIAKAD' }}</p>
                            @foreach($portalNavItems as $item)
                                @php($isActive = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                                <a href="{{ $item['href'] }}" class="flex items-center justify-between rounded-lg px-3 py-3 text-sm font-bold {{ $isActive ? 'bg-brand-100 text-ink' : 'text-slate-600 hover:bg-slate-100' }}" @if($isActive) aria-current="page" @endif>{{ $item['label'] }} @if($isActive)<span class="h-2 w-2 rounded-full bg-neon"></span>@endif</a>
                            @endforeach
                            @isset($navLinks)<div class="mt-2 border-t border-line pt-2">{{ $navLinks }}</div>@endisset
                            <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-line pt-2 sm:hidden">@csrf<button type="submit" class="flex w-full items-center justify-between rounded-lg px-3 py-3 text-sm font-bold text-slate-600 hover:bg-slate-100">Keluar <span aria-hidden="true">→</span></button></form>
                        </div>
                    </details>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:py-10">{{ $slot }}</main>
        <footer class="mx-auto max-w-7xl border-t border-line px-4 py-6 text-center font-mono text-[10px] font-bold tracking-[.09em] text-slate-500 sm:px-6">© {{ date('Y') }} GRIYA QUR'AN TUNAS ILMU</footer>
    </div>
    @stack('scripts')
</body>
</html>
