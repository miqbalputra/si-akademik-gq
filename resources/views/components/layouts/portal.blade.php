@php
    $isGuruPortal = ($portalLabel ?? null) === 'Portal Guru';
    $isWaliPortal = ($portalLabel ?? null) === 'Portal Wali Santri';
    $isManagementPortal = ($portalLabel ?? null) === 'Portal Manajemen';
    $isTahfidzPortal = ($portalLabel ?? null) === 'Portal Kabag Tahfidz';
    $isDiniyyahPortal = ($portalLabel ?? null) === 'Portal Kabag Diniyyah';
    $portalUser = auth()->user();
    $usesAdminGuard = auth()->getDefaultDriver() === 'admin';
    $notificationPrefix = $usesAdminGuard ? 'admin.notifications.' : 'notifications.';
    $portalLogoutUrl = $usesAdminGuard ? route('filament.admin.auth.logout') : route('logout');
    $workspaceService = app(\App\Services\WorkspaceRedirectService::class);
    $workspaceItems = $portalUser ? $workspaceService->availableFor($portalUser) : [];
    $currentWorkspace = $isGuruPortal
        ? \App\Services\WorkspaceRedirectService::GURU
        : ($isTahfidzPortal
            ? \App\Services\WorkspaceRedirectService::KABAG_TAHFIDZ
            : ($isDiniyyahPortal
                ? \App\Services\WorkspaceRedirectService::KABAG_DINIYYAH
                : ($isManagementPortal ? \App\Services\WorkspaceRedirectService::MANAGEMENT : null)));
    $portalHomeUrl = $isGuruPortal
        ? route('guru.dashboard')
        : ($isTahfidzPortal
            ? route('kabag-tahfidz.dashboard')
            : ($isDiniyyahPortal
                ? route('kabag-diniyyah.dashboard')
                : ($isWaliPortal ? route('wali.dashboard') : ($isManagementPortal ? url('/admin') : url('/')))));
    $isHomeActive = request()->routeIs($isGuruPortal ? 'guru.dashboard' : ($isTahfidzPortal ? 'kabag-tahfidz.dashboard' : ($isDiniyyahPortal ? 'kabag-diniyyah.dashboard' : ($isWaliPortal ? 'wali.dashboard' : 'filament.admin.pages.dashboard'))));
    if ($usesAdminGuard) $portalHomeUrl = url('/admin');
    $guruNavigation = app(\App\Support\GuruNavigation::class);
    $guruGroups = $isGuruPortal && $portalUser
        ? $guruNavigation->groups($portalUser, $hasSimultaneousTafsirSchedule ?? false)
        : [];

    $waliTodayItems = [
        ['label' => 'Tahfidz', 'href' => route('wali.tahfidz'), 'match' => ['wali.tahfidz']],
        ['label' => 'Kalender', 'href' => route('wali.calendar'), 'match' => ['wali.calendar']],
    ];
    $waliArchiveItems = [
        ['label' => 'Rapor', 'href' => route('wali.dashboard').'#rapor', 'match' => ['report-cards.*']],
    ];

    $tahfidzMonitoringItems = [
        ['label' => 'Dashboard Tahfidz', 'href' => route('kabag-tahfidz.dashboard'), 'match' => ['kabag-tahfidz.dashboard']],
        ['label' => 'Nilai Tahfidz Rapor', 'href' => route('kabag-tahfidz.report.index'), 'match' => ['kabag-tahfidz.report.*']],
        ['label' => 'Laporan Tasmi\'', 'href' => route('admin.tasmi-report.index'), 'match' => ['admin.tasmi-report.*']],
        ['label' => 'Penugasan PJ Tasmi\'', 'href' => \App\Filament\Resources\TasmiExaminerAssignments\TasmiExaminerAssignmentResource::getUrl(), 'match' => ['filament.admin.resources.tasmi-examiner-assignments.*']],
    ];
    $tahfidzCoordinationItems = [
        ['label' => 'Penempatan Halaqah', 'href' => \App\Filament\Pages\HalaqahPlacementBoard::getUrl(), 'match' => ['filament.admin.pages.halaqah-placement-board']],
        ['label' => 'Halaqah', 'href' => \App\Filament\Resources\TahfidzHalaqahs\TahfidzHalaqahResource::getUrl(), 'match' => ['filament.admin.resources.tahfidz-halaqahs.*']],
        ['label' => 'Pekan Tahfidz', 'href' => \App\Filament\Resources\TahfidzWeeks\TahfidzWeekResource::getUrl(), 'match' => ['filament.admin.resources.tahfidz-weeks.*']],
        ['label' => 'Jadwal UAS', 'href' => \App\Filament\Resources\TahfidzUasDays\TahfidzUasDayResource::getUrl(), 'match' => ['filament.admin.resources.tahfidz-uas-days.*']],
        ['label' => 'Aspek UAS', 'href' => \App\Filament\Resources\TahfidzUasCategories\TahfidzUasCategoryResource::getUrl(), 'match' => ['filament.admin.resources.tahfidz-uas-categories.*']],
    ];
    $diniyyahMonitoringItems = [
        ['label' => 'Penetapan KKM', 'href' => route('kabag.kkm.index'), 'match' => ['kabag.kkm.*']],
        ['label' => 'Dashboard Diniyyah', 'href' => route('kabag-diniyyah.dashboard'), 'match' => ['kabag-diniyyah.dashboard']],
        ['label' => 'Monitoring Nilai', 'href' => route('diniyyah.monitoring.index'), 'match' => ['diniyyah.monitoring.*']],
        ['label' => 'Jurnal KBM', 'href' => \App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-class-journals.*']],
        ['label' => 'Jadwal Mengajar', 'href' => \App\Filament\Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-teaching-schedules.*']],
    ];
    $diniyyahManagementItems = [
        ['label' => 'Penugasan Guru', 'href' => \App\Filament\Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-teacher-assignments.*']],
        ['label' => 'Mapel & Kelas', 'href' => \App\Filament\Resources\DiniyyahClassSubjects\DiniyyahClassSubjectResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-class-subjects.*']],
        ['label' => 'Set & Validasi Nilai', 'href' => \App\Filament\Resources\DiniyyahAssessmentSets\DiniyyahAssessmentSetResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-assessment-sets.*', 'filament.admin.resources.diniyyah-score-validations.*']],
        ['label' => 'Rapor', 'href' => \App\Filament\Resources\ReportCards\ReportCardResource::getUrl(), 'match' => ['filament.admin.resources.report-cards.*']],
        ['label' => 'Monitoring RPP', 'href' => \App\Filament\Resources\Rpps\RppResource::getUrl(), 'match' => ['filament.admin.resources.rpps.*', 'filament.admin.resources.rpp-promes.*']],
    ];
    $managementTodayItems = [
        ['label' => 'Panel Admin', 'href' => url('/admin'), 'match' => ['filament.admin.pages.dashboard']],
    ];
    $managementArchiveItems = [
        isset($snapshot)
            ? ['label' => 'Leger / Rapor', 'href' => route('diniyyah.ledger.show', $snapshot), 'match' => ['diniyyah.ledger.*']]
            : ['label' => 'Rapor Diniyyah', 'href' => route('filament.admin.resources.report-cards.index'), 'match' => ['filament.admin.resources.report-cards.*']],
    ];
    if ($isManagementPortal && $portalUser?->hasRole('admin')) {
        array_unshift($managementArchiveItems, ['label' => 'Peringkat Semua Kelas', 'href' => route('admin.class-rankings.index'), 'match' => ['admin.class-rankings.*']]);
    }

    $portalNavGroups = $isGuruPortal
        ? $guruGroups
        : ($isTahfidzPortal
            ? [
                ['label' => 'Monitoring', 'items' => $tahfidzMonitoringItems],
                ['label' => 'Koordinasi Tahfidz', 'items' => $tahfidzCoordinationItems],
            ]
            : ($isDiniyyahPortal
                ? [
                    ['label' => 'Monitoring', 'items' => $diniyyahMonitoringItems],
                    ['label' => 'Koordinasi Diniyyah', 'items' => $diniyyahManagementItems],
                ]
                : ($isWaliPortal
            ? [
                ['label' => 'Kegiatan Hari Ini', 'items' => $waliTodayItems],
                ['label' => 'Rekap & Arsip', 'items' => $waliArchiveItems],
            ]
            : ($isManagementPortal
                ? [
                    ['label' => 'Kegiatan Hari Ini', 'items' => $managementTodayItems],
                    ['label' => 'Rekap & Arsip', 'items' => $managementArchiveItems],
                ]
                : []))));
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal' }} - Ruang GQ</title>
    <meta name="description" content="Aktivitas Akademik Griya Qur'an Tunas Ilmu">
    @include('partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-palette')
    @include('partials.pwa-head')
    @stack('head')
    @stack('styles')
</head>
<body class="app-shell min-h-screen overflow-x-clip bg-surface-subtle text-body antialiased dark:bg-canvas dark:text-body">
    @if(in_array($portalLabel ?? null, ['Portal Guru', 'Portal Wali Santri'], true))
        @include('partials.pwa-install-prompt')
    @endif

    <div x-data="{ mobileSidebarOpen: false, desktopSidebar: window.innerWidth >= 1280 }"
        @resize.window="desktopSidebar = window.innerWidth >= 1280"
        @keydown.escape.window="if (mobileSidebarOpen) { mobileSidebarOpen = false; $refs.portalMenuButton.focus(); }"
        class="min-h-screen xl:flex">
        <div
            x-cloak
            x-show="mobileSidebarOpen"
            x-transition.opacity
            @click="mobileSidebarOpen = false"
            class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm xl:hidden"
            aria-hidden="true"
        ></div>

        <aside
            id="portal-sidebar"
            :inert="!mobileSidebarOpen && !desktopSidebar"
            :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="school-sidebar max-w-full fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-line bg-surface transition-transform duration-300 xl:sticky xl:top-0 xl:h-screen xl:translate-x-0 dark:border-gray-800 dark:bg-surface"
            aria-label="Navigasi {{ $portalLabel ?? 'portal' }}"
        >
            <div class="flex h-20 shrink-0 items-center justify-between border-b border-line px-6 dark:border-gray-800">
                <a href="{{ $portalHomeUrl }}" class="school-brand">
                    <span class="school-mark">GQ</span>
                    <span>
                        <strong>Ruang GQ</strong>
                        <small>Griya Qur'an Tunas Ilmu</small>
                    </span>
                </a>
                <button type="button" @click="mobileSidebarOpen = false" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-soft hover:bg-surface-muted hover:text-body xl:hidden dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Tutup menu">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </button>
            </div>

            <nav class="school-sidebar-nav flex-1 overflow-y-auto px-4 py-6" aria-label="Menu portal"
                @if($isGuruPortal) data-guru-nav data-storage-key="{{ $guruNavigation->storageKey($portalUser) }}" @endif
            >
                @if($isGuruPortal)
                    <div class="mb-5">
                        <label for="guru-menu-search" class="mb-2 block px-1 text-theme-sm font-medium text-heading">Cari menu</label>
                        <div class="flex items-center gap-1 rounded-lg border border-line bg-surface px-2">
                            <input id="guru-menu-search" data-guru-menu-search type="search" placeholder="Contoh: rapor, presensi" autocomplete="off" aria-controls="guru-menu-groups" class="min-h-11 w-full min-w-0 bg-transparent px-1 text-theme-sm text-heading">
                            <button type="button" data-guru-menu-clear class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-surface-subtle" aria-label="Hapus pencarian menu" hidden>
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <p data-guru-menu-status role="status" aria-live="polite" class="mt-2 px-1 text-theme-xs text-muted"></p>
                        <p data-guru-menu-empty class="mt-2 px-1 text-theme-sm text-muted" hidden>Menu tidak ditemukan. Coba kata lain.</p>
                    </div>
                @endif
                <p class="mb-2 px-3 ui-menu-group text-soft">{{ $portalLabel ?? 'Ruang GQ' }}</p>
                <a
                    href="{{ $portalHomeUrl }}"
                    @if($isGuruPortal) data-guru-home @endif
                    class="school-sidebar-link mb-5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-theme-sm font-medium transition @if($isHomeActive) bg-brand-soft text-brand-ink dark:bg-surface/5 dark:text-brand-300 @else text-body hover:bg-surface-subtle hover:text-heading dark:text-body dark:hover:bg-surface/5 dark:hover:text-white @endif"
                    @if($isHomeActive) aria-current="page" @endif
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 10.5 8.25-6.75 8.25 6.75v9a.75.75 0 0 1-.75.75h-5.25v-6.75h-4.5v6.75H4.5a.75.75 0 0 1-.75-.75v-9Z"/></svg>
                    <span>Beranda</span>
                </a>

                <div @if($isGuruPortal) id="guru-menu-groups" @endif>
                @foreach($portalNavGroups as $group)
                    @php($isGroupActive = $group['active'] ?? collect($group['items'])->contains(fn ($item) => collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern))))
                    <details class="school-sidebar-group school-nav-dropdown mb-4"
                        @if($isGuruPortal) data-guru-group="{{ $group['key'] }}" data-active="{{ $isGroupActive ? 'true' : 'false' }}" data-default-open="{{ $group['default_open'] ? 'true' : 'false' }}" @endif
                        @if($isGroupActive || ($group['default_open'] ?? false)) open @endif
                    >
                        <summary class="school-nav-dropdown-toggle mb-2 flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2 ui-menu-group text-soft hover:bg-surface-subtle dark:hover:bg-surface/5">
                            <span>{{ $group['label'] }}</span>
                            <svg class="school-nav-chevron h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </summary>
                        <div class="space-y-1">
                            @foreach($group['items'] as $item)
                                @php($isActive = $item['active'] ?? collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                                <div @if($isGuruPortal) data-guru-item data-search-label="{{ $item['label'].' '.($item['aliases'] ?? '') }}" @endif>
                                <x-workspace-link
                                    :href="$item['href']"
                                    :class="'school-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-theme-sm font-medium transition '.($isActive ? 'bg-brand-soft text-brand-ink dark:bg-surface/5 dark:text-brand-300' : 'text-body hover:bg-surface-subtle hover:text-heading dark:text-body dark:hover:bg-surface/5 dark:hover:text-white')"
                                    :aria-current="$isActive ? 'page' : null"
                                >
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full @if($isActive) bg-brand-500 @else bg-line-strong dark:bg-gray-600 @endif" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
                                </x-workspace-link>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach
                </div>
            </nav>

            <div class="shrink-0 border-t border-line p-4 dark:border-gray-800">
                <div class="flex items-center gap-3 rounded-lg bg-surface-subtle p-3 dark:bg-surface-muted/60">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft-strong text-theme-sm font-medium text-brand-ink dark:bg-brand-900/50 dark:text-brand-300">
                        {{ \Illuminate\Support\Str::of(auth()->user()?->name ?? 'GQ')->substr(0, 1)->upper() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-theme-sm font-medium text-heading dark:text-white/90">{{ auth()->user()?->name }}</span>
                        <span class="block truncate text-theme-xs text-muted dark:text-muted font-normal">{{ $portalLabel ?? 'Ruang GQ' }}</span>
                    </span>
                    <form method="POST" action="{{ $portalLogoutUrl }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-md text-soft transition hover:bg-surface hover:text-danger-ink dark:hover:bg-gray-700" aria-label="Keluar dari akun" title="Keluar dari akun">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18 15l3-3m0 0-3-3m3 3H9.75"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="school-header sticky top-0 z-30 border-b border-line bg-surface/90 backdrop-blur-xl dark:border-gray-800 dark:bg-surface/90">
                <nav class="school-header-inner flex min-h-[4.5rem] flex-wrap items-center gap-x-3 gap-y-2 px-4 sm:flex-nowrap sm:gap-4 sm:px-6 xl:px-8" aria-label="Konteks {{ $portalLabel ?? 'portal' }}">
                    <button type="button" x-ref="portalMenuButton" @click="mobileSidebarOpen = true" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-line bg-surface text-muted transition hover:bg-surface-subtle xl:hidden dark:border-gray-700 dark:bg-surface dark:text-body dark:hover:bg-gray-800" aria-label="Buka menu" aria-controls="portal-sidebar" :aria-expanded="mobileSidebarOpen">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    <div class="school-context order-last w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                        <p class="m-0 truncate text-theme-sm font-normal text-muted dark:text-muted">{{ $portalLabel ?? 'Ruang GQ' }}@if(filled($breadcrumb ?? null)) <span class="mx-1 text-soft dark:text-soft">/</span>{{ $breadcrumb }}@endif</p>
                        <h1 class="m-0 truncate text-heading dark:text-white ui-page-title">{{ $title ?? 'Beranda' }}</h1>
                    </div>

                    <div class="school-header-actions flex shrink-0 items-center gap-2 sm:gap-3" data-header-actions>
                        @isset($navLinks)
                            <div class="hidden items-center gap-1 xl:flex">{{ $navLinks }}</div>
                        @endisset

                        @if(count($workspaceItems) > 1)
                            <details class="school-nav-dropdown relative" data-portal-menu>
                                <summary class="school-nav-dropdown-toggle inline-flex h-10 w-10 cursor-pointer list-none items-center justify-center gap-2 rounded-lg border border-line bg-surface text-theme-sm font-medium text-body hover:bg-surface-subtle sm:w-auto sm:px-3 dark:border-gray-700 dark:bg-surface dark:text-body dark:hover:bg-gray-800" aria-label="Ganti ruang kerja" aria-expanded="false">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6.75h16M4 12h16M4 17.25h16"/></svg>
                                    <span class="hidden sm:inline">Ganti Ruang</span>
                                    <svg class="school-nav-chevron hidden h-4 w-4 sm:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="school-nav-dropdown-panel absolute end-0 top-12 z-50 grid min-w-56 gap-1 rounded-xl border border-line bg-surface p-2 shadow-theme-lg dark:border-gray-700 dark:bg-surface" role="menu" aria-label="Ganti ruang kerja">
                                    @foreach($workspaceItems as $key => $workspace)
                                        <x-workspace-link :href="$workspace['destination']" class="school-nav-dropdown-link flex items-center justify-between rounded-lg px-3 py-2 text-theme-sm font-medium text-body hover:bg-surface-subtle dark:text-body dark:hover:bg-gray-800" role="menuitem" :aria-current="$currentWorkspace === $key ? 'page' : null">
                                            <span>{{ $workspace['label'] }}</span>
                                            @if($currentWorkspace === $key)<span class="school-nav-active-dot h-1.5 w-1.5 rounded-full bg-brand-500" aria-hidden="true"></span>@endif
                                        </x-workspace-link>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        <x-common.theme-toggle />

                        <div class="relative" data-notification-root data-feed-url="{{ route($notificationPrefix.'feed') }}" data-read-url-template="{{ route($notificationPrefix.'read', '__ID__') }}" data-mark-all-url="{{ route($notificationPrefix.'read-all') }}">
                            <button type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-surface text-muted transition hover:bg-surface-subtle dark:border-gray-700 dark:bg-surface dark:text-body dark:hover:bg-gray-800" aria-label="Notifikasi" aria-haspopup="dialog" aria-expanded="false" data-notification-toggle>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                                <span class="absolute -end-1 -top-1 hidden min-w-4 rounded-full bg-brand-600 px-1 py-0.5 text-white text-theme-xs font-medium" data-notification-badge>0</span>
                            </button>
                            <section class="notification-dropdown absolute end-0 top-12 z-60" role="dialog" aria-label="Notifikasi" hidden data-notification-panel>
                                <header class="flex items-center justify-between border-b border-line px-4 py-3 dark:border-gray-800">
                                    <strong class="text-theme-sm font-medium text-heading dark:text-white/90">Notifikasi</strong>
                                    <button type="button" class="text-brand-ink hover:text-brand-ink dark:text-brand-300 text-theme-sm font-medium" data-notification-mark-all>Tandai semua dibaca</button>
                                </header>
                                <div class="max-h-96 overflow-y-auto" data-notification-list><p class="px-5 py-8 text-center text-theme-xs font-normal text-soft">Memuat...</p></div>
                                <footer class="border-t border-line px-4 py-3 text-center dark:border-gray-800"><a href="{{ route($notificationPrefix.'index') }}" class="text-theme-sm font-medium text-brand-ink hover:text-brand-ink dark:text-brand-300">Lihat semua notifikasi <span aria-hidden="true">&rarr;</span></a></footer>
                            </section>
                        </div>

                        <form method="POST" action="{{ $portalLogoutUrl }}" class="shrink-0" data-header-logout>
                            @csrf
                            <button type="submit" class="inline-flex h-10 w-10 items-center justify-center gap-2 rounded-lg border border-line bg-surface text-theme-sm font-medium text-muted transition hover:border-danger-line hover:bg-danger-soft hover:text-danger-ink sm:w-auto sm:px-3 dark:border-gray-700 dark:bg-surface dark:text-body dark:hover:border-danger-line dark:hover:bg-danger-soft dark:hover:text-danger-ink" aria-label="Keluar dari akun" title="Keluar dari akun">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18 15l3-3m0 0-3-3m3 3H9.75"/></svg>
                                <span class="hidden sm:inline">Keluar</span>
                            </button>
                        </form>
                    </div>
                </nav>
                @isset($navLinks)
                    <div class="border-t border-line px-4 py-2 xl:hidden dark:border-gray-800 sm:px-6">
                        <div class="flex flex-wrap items-center gap-2" data-mobile-page-actions>{{ $navLinks }}</div>
                    </div>
                @endisset
            </header>

            <main class="school-main mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 sm:py-8 xl:px-8">
                @if($isGuruPortal)
                    <x-tasmi-correction-reminder :reminder="$tasmiCorrectionReminder ?? null" />
                @endif
                {{ $slot }}
                <footer class="school-footer mt-10 border-t border-line pt-5 text-center text-theme-xs text-soft dark:border-gray-800 dark:text-muted font-normal">
                    &copy; {{ date('Y') }} Ruang GQ · Griya Qur'an Tunas Ilmu · SchoolVia.id
                </footer>
            </main>
        </div>
    </div>

    @if($isGuruPortal)
        <x-journal-overdue-reminder :journal-overdue-reminder="$journalOverdueReminder ?? null" />
        <x-tasmi-wali-reminder :tasmi-wali-reminder="$tasmiWaliReminder ?? null" />
    @endif
    @stack('scripts')
</body>
</html>
