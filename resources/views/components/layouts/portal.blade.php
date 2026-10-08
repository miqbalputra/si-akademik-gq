@php
    $isGuruPortal = ($portalLabel ?? null) === 'Portal Guru';
    $isWaliPortal = ($portalLabel ?? null) === 'Portal Wali Santri';
    $isManagementPortal = ($portalLabel ?? null) === 'Portal Manajemen';
    $isTahfidzPortal = ($portalLabel ?? null) === 'Portal Kabag Tahfidz';
    $isDiniyyahPortal = ($portalLabel ?? null) === 'Portal Kabag Diniyyah';
    $portalUser = auth()->user();
    $workspaceService = app(\App\Services\WorkspaceRedirectService::class);
    $workspaceItems = $portalUser ? $workspaceService->availableFor($portalUser) : [];
    $currentWorkspace = $isGuruPortal
        ? \App\Services\WorkspaceRedirectService::GURU
        : ($isTahfidzPortal
            ? \App\Services\WorkspaceRedirectService::KABAG_TAHFIDZ
            : ($isDiniyyahPortal
                ? \App\Services\WorkspaceRedirectService::KABAG_DINIYYAH
                : ($isManagementPortal ? \App\Services\WorkspaceRedirectService::MANAGEMENT : null)));
    $hasLinkedTeacher = $isGuruPortal && $portalUser?->teacher !== null;
    $portalHomeUrl = $isGuruPortal
        ? route('guru.dashboard')
        : ($isTahfidzPortal
            ? route('kabag-tahfidz.dashboard')
            : ($isDiniyyahPortal
                ? route('kabag-diniyyah.dashboard')
                : ($isWaliPortal ? route('wali.dashboard') : ($isManagementPortal ? url('/admin') : url('/')))));
    $isHomeActive = request()->routeIs($isGuruPortal ? 'guru.dashboard' : ($isTahfidzPortal ? 'kabag-tahfidz.dashboard' : ($isDiniyyahPortal ? 'kabag-diniyyah.dashboard' : ($isWaliPortal ? 'wali.dashboard' : 'filament.admin.pages.dashboard'))));
    $canAccessAttendance = $isGuruPortal && ($portalUser?->canAccessAttendance() ?? false);
    $isTasmiExaminer = $isGuruPortal && ($portalUser?->isTasmiExaminer() ?? false);
    $isHomeroomTeacher = $isGuruPortal && ($portalUser?->teacher?->homeroomAssignments()->exists() ?? false);

    $guruTodayItems = [
        ['label' => 'Jurnal', 'href' => route('guru.diniyyah-journals.index'), 'match' => ['guru.diniyyah-journals.*']],
        ['label' => 'Tahfidz', 'href' => route('guru.tahfidz.index'), 'match' => ['guru.tahfidz.*']],
    ];
    if ($hasLinkedTeacher) {
        array_splice($guruTodayItems, 1, 0, [[
            'label' => 'Jurnal Pengganti', 'href' => route('guru.diniyyah-substitute-journals.index'), 'match' => ['guru.diniyyah-substitute-journals.*', 'guru.diniyyah-substitute-tafsir-journals.*'],
        ], [
            'label' => 'Pengganti Tafsir', 'href' => route('guru.diniyyah-substitute-tafsir-journals.index'), 'match' => ['guru.diniyyah-substitute-tafsir-journals.*'],
        ]]);
    }
    if ($hasSimultaneousTafsirSchedule ?? false) {
        array_splice($guruTodayItems, 1, 0, [[
            'label' => 'Jurnal Tafsir', 'href' => route('guru.diniyyah-tafsir-journals.index'), 'match' => ['guru.diniyyah-tafsir-journals.*'],
        ]]);
    }
    if ($canAccessAttendance) {
        array_splice($guruTodayItems, 1, 0, [[
            'label' => 'Presensi', 'href' => route('attendance.index'), 'match' => ['attendance.*'],
        ]]);
    }
    if ($isTasmiExaminer) {
        $guruTodayItems[] = ['label' => 'Tasmi\'', 'href' => route('guru.tasmi.index'), 'match' => ['guru.tasmi.*']];
    }
    $guruClassItems = [
        ['label' => 'Input Nilai', 'href' => route('guru.diniyyah-scores.index'), 'match' => ['guru.diniyyah-scores.*']],
    ];
    if ($isHomeroomTeacher) {
        $guruClassItems[] = ['label' => 'Tasmi\' Kelas Saya', 'href' => route('guru.tasmi-wali.index'), 'match' => ['guru.tasmi-wali.*']];
        $guruClassItems[] = ['label' => 'Monitoring Jurnal Kelas', 'href' => route('wali.diniyyah-journals.index'), 'match' => ['wali.diniyyah-journals.*']];
        $guruClassItems[] = ['label' => 'Rekap JP Kelas', 'href' => route('wali.jp-recap.index'), 'match' => ['wali.jp-recap.*']];
    }
    $guruArchiveItems = [
        ['label' => 'Performa Jurnal Saya', 'href' => route('guru.performa'), 'match' => ['guru.performa']],
        ['label' => 'Riwayat Jurnal', 'href' => route('guru.diniyyah-journals.riwayat'), 'match' => ['guru.diniyyah-journals.riwayat']],
        ['label' => 'Kalender', 'href' => route('guru.calendar'), 'match' => ['guru.calendar']],
    ];
    if ($hasLinkedTeacher) {
        array_unshift($guruArchiveItems, [
            'label' => 'Presensi Saya', 'href' => route('guru.attendance-report.index'), 'match' => ['guru.attendance-report.*'],
        ]);
    }

    $guruLearningItems = $hasLinkedTeacher ? [
        ['label' => 'RPP Saya', 'href' => route('guru.rpp.index'), 'match' => ['guru.rpp.index', 'guru.rpp.show', 'guru.rpp.edit']],
        ...(! config('rpp_sync.enabled') ? [['label' => 'Buat RPP', 'href' => route('guru.rpp.create'), 'match' => ['guru.rpp.create']]] : []),
        ['label' => 'Referensi RPP', 'href' => route('guru.rpp.references'), 'match' => ['guru.rpp.references']],
        ['label' => 'Promes Saya', 'href' => route('guru.rpp.promes'), 'match' => ['guru.rpp.promes']],
        ...(! config('rpp_sync.enabled') ? [['label' => 'Sampah RPP', 'href' => route('guru.rpp.trash'), 'match' => ['guru.rpp.trash']]] : []),
    ] : [];
    $guruCoordinationItems = [];
    if ($isGuruPortal && $portalUser?->hasRole('kabag_tahfidz')) {
        $guruCoordinationItems[] = ['label' => "Monitoring Tasmi' Semua Kelas", 'href' => route('admin.tasmi-report.index'), 'match' => ['admin.tasmi-report.*']];
    }

    $waliTodayItems = [
        ['label' => 'Tahfidz', 'href' => route('wali.tahfidz'), 'match' => ['wali.tahfidz']],
        ['label' => 'Kalender', 'href' => route('wali.calendar'), 'match' => ['wali.calendar']],
    ];
    $waliArchiveItems = [
        ['label' => 'Rapor', 'href' => route('wali.dashboard').'#rapor', 'match' => ['report-cards.*']],
    ];

    $tahfidzMonitoringItems = [
        ['label' => 'Dashboard Tahfidz', 'href' => route('kabag-tahfidz.dashboard'), 'match' => ['kabag-tahfidz.dashboard']],
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
        ['label' => 'Dashboard Diniyyah', 'href' => route('kabag-diniyyah.dashboard'), 'match' => ['kabag-diniyyah.dashboard']],
        ['label' => 'Monitoring Nilai', 'href' => route('diniyyah.monitoring.index'), 'match' => ['diniyyah.monitoring.*']],
        ['label' => 'Jurnal KBM', 'href' => \App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-class-journals.*']],
        ['label' => 'Jadwal Mengajar', 'href' => \App\Filament\Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-teaching-schedules.*']],
    ];
    $diniyyahManagementItems = [
        ['label' => 'Penugasan Guru', 'href' => \App\Filament\Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-teacher-assignments.*']],
        ['label' => 'Mapel & Kelas', 'href' => \App\Filament\Resources\DiniyyahClassSubjects\DiniyyahClassSubjectResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-class-subjects.*']],
        ['label' => 'Set & Validasi Nilai', 'href' => \App\Filament\Resources\DiniyyahAssessmentSets\DiniyyahAssessmentSetResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-assessment-sets.*', 'filament.admin.resources.diniyyah-score-validations.*']],
        ['label' => 'Leger & Rapor', 'href' => \App\Filament\Resources\DiniyyahLedgerSnapshots\DiniyyahLedgerSnapshotResource::getUrl(), 'match' => ['filament.admin.resources.diniyyah-ledger-snapshots.*', 'filament.admin.resources.report-cards.*']],
        ['label' => 'Monitoring RPP', 'href' => \App\Filament\Resources\Rpps\RppResource::getUrl(), 'match' => ['filament.admin.resources.rpps.*', 'filament.admin.resources.rpp-promes.*']],
    ];
    $managementTodayItems = [
        ['label' => 'Panel Admin', 'href' => url('/admin'), 'match' => ['filament.admin.pages.dashboard']],
    ];
    $managementArchiveItems = [
        isset($snapshot)
            ? ['label' => 'Leger / Rapor', 'href' => route('diniyyah.ledger.show', $snapshot), 'match' => ['diniyyah.ledger.*']]
            : ['label' => 'Leger / Rapor', 'href' => route('filament.admin.resources.diniyyah-ledger-snapshots.index'), 'match' => ['filament.admin.resources.diniyyah-ledger-snapshots.*']],
    ];

    $portalNavGroups = $isGuruPortal
        ? array_values(array_filter([
            ['label' => 'Kegiatan', 'items' => $guruTodayItems],
            ['label' => 'Perangkat Pembelajaran', 'items' => $guruLearningItems],
            ['label' => 'Kelas & Santri', 'items' => $guruClassItems],
            ['label' => 'Koordinasi Tahfidz', 'items' => $guruCoordinationItems],
            ['label' => 'Laporan & Arsip', 'items' => $guruArchiveItems],
        ], fn (array $group): bool => $group['items'] !== []))
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

    <div x-data="{ mobileSidebarOpen: false }" @keydown.escape.window="mobileSidebarOpen = false" class="min-h-screen xl:flex">
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
            :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="school-sidebar fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-line bg-surface transition-transform duration-300 xl:sticky xl:top-0 xl:h-screen xl:translate-x-0 dark:border-gray-800 dark:bg-surface"
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
                <button type="button" @click="mobileSidebarOpen = false" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-soft hover:bg-surface-muted hover:text-body xl:hidden dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Tutup menu">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </button>
            </div>

            <nav class="school-sidebar-nav flex-1 overflow-y-auto px-4 py-6" aria-label="Menu portal">
                <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[.08em] text-soft">{{ $portalLabel ?? 'Ruang GQ' }}</p>
                <a
                    href="{{ $portalHomeUrl }}"
                    class="school-sidebar-link mb-5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition @if($isHomeActive) bg-brand-soft text-brand-ink dark:bg-surface/5 dark:text-brand-300 @else text-body hover:bg-surface-subtle hover:text-heading dark:text-body dark:hover:bg-surface/5 dark:hover:text-white @endif"
                    @if($isHomeActive) aria-current="page" @endif
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 10.5 8.25-6.75 8.25 6.75v9a.75.75 0 0 1-.75.75h-5.25v-6.75h-4.5v6.75H4.5a.75.75 0 0 1-.75-.75v-9Z"/></svg>
                    <span>Beranda</span>
                </a>

                @foreach($portalNavGroups as $group)
                    @php($isGroupActive = collect($group['items'])->contains(fn ($item) => collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern))))
                    <details class="school-sidebar-group school-nav-dropdown mb-6" @if($isGroupActive) open @endif>
                        <summary class="school-nav-dropdown-toggle mb-2 flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-[.08em] text-soft hover:bg-surface-subtle dark:hover:bg-surface/5">
                            <span>{{ $group['label'] }}</span>
                            <svg class="school-nav-chevron h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </summary>
                        <div class="space-y-1">
                            @foreach($group['items'] as $item)
                                @php($isActive = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                                <a
                                    href="{{ $item['href'] }}"
                                    class="school-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition @if($isActive) bg-brand-soft text-brand-ink dark:bg-surface/5 dark:text-brand-300 @else text-body hover:bg-surface-subtle hover:text-heading dark:text-body dark:hover:bg-surface/5 dark:hover:text-white @endif"
                                    @if($isActive) aria-current="page" @endif
                                >
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full @if($isActive) bg-brand-500 @else bg-line-strong dark:bg-gray-600 @endif" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </nav>

            <div class="border-t border-line p-4 dark:border-gray-800">
                <div class="flex items-center gap-3 rounded-lg bg-surface-subtle p-3 dark:bg-surface-muted/60">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft-strong text-sm font-semibold text-brand-ink dark:bg-brand-900/50 dark:text-brand-300">
                        {{ \Illuminate\Support\Str::of(auth()->user()?->name ?? 'GQ')->substr(0, 1)->upper() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-heading dark:text-white/90">{{ auth()->user()?->name }}</span>
                        <span class="block truncate text-xs text-muted dark:text-muted">{{ $portalLabel ?? 'Ruang GQ' }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md p-2 text-soft transition hover:bg-surface hover:text-danger-ink dark:hover:bg-gray-700" aria-label="Keluar">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18 15l3-3m0 0-3-3m3 3H9.75"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="school-header sticky top-0 z-30 border-b border-line bg-surface/90 backdrop-blur-xl dark:border-gray-800 dark:bg-surface/90">
                <nav class="school-header-inner flex min-h-[4.5rem] items-center gap-4 px-4 sm:px-6 xl:px-8" aria-label="Konteks {{ $portalLabel ?? 'portal' }}">
                    <button type="button" @click="mobileSidebarOpen = true" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-line bg-surface text-muted transition hover:bg-surface-subtle xl:hidden dark:border-gray-700 dark:bg-surface dark:text-body dark:hover:bg-gray-800" aria-label="Buka menu" aria-controls="portal-sidebar">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    <div class="school-context min-w-0 flex-1">
                        <p class="m-0 truncate text-xs font-medium text-muted dark:text-muted">{{ $portalLabel ?? 'Ruang GQ' }}@if(filled($breadcrumb ?? null)) <span class="mx-1 text-soft dark:text-soft">/</span>{{ $breadcrumb }}@endif</p>
                        <h1 class="m-0 truncate text-base font-semibold text-heading dark:text-white sm:text-lg">{{ $title ?? 'Beranda' }}</h1>
                    </div>

                    <div class="school-header-actions flex shrink-0 items-center gap-2 sm:gap-3" data-header-actions>
                        @isset($navLinks)
                            <div class="hidden items-center gap-1 xl:flex">{{ $navLinks }}</div>
                        @endisset

                        @if(count($workspaceItems) > 1)
                            <details class="school-nav-dropdown relative" data-portal-menu>
                                <summary class="school-nav-dropdown-toggle inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm font-medium text-body hover:bg-surface-subtle dark:border-gray-700 dark:bg-surface dark:text-body dark:hover:bg-gray-800" aria-expanded="false">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6.75h16M4 12h16M4 17.25h16"/></svg>
                                    <span class="hidden sm:inline">Ganti Ruang</span>
                                    <svg class="school-nav-chevron h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="school-nav-dropdown-panel absolute end-0 top-12 z-50 grid min-w-56 gap-1 rounded-xl border border-line bg-surface p-2 shadow-theme-lg dark:border-gray-700 dark:bg-surface" role="menu" aria-label="Ganti ruang kerja">
                                    @foreach($workspaceItems as $key => $workspace)
                                        <a href="{{ $workspace['destination'] }}" class="school-nav-dropdown-link flex items-center justify-between rounded-lg px-3 py-2 text-sm text-body hover:bg-surface-subtle dark:text-body dark:hover:bg-gray-800" role="menuitem" @if($currentWorkspace === $key) aria-current="page" @endif>
                                            <span>{{ $workspace['label'] }}</span>
                                            @if($currentWorkspace === $key)<span class="school-nav-active-dot h-1.5 w-1.5 rounded-full bg-brand-500" aria-hidden="true"></span>@endif
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        <x-common.theme-toggle />

                        <div class="relative" data-notification-root data-feed-url="{{ route('notifications.feed') }}" data-read-url-template="{{ route('notifications.read', '__ID__') }}" data-mark-all-url="{{ route('notifications.read-all') }}">
                            <button type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-surface text-muted transition hover:bg-surface-subtle dark:border-gray-700 dark:bg-surface dark:text-body dark:hover:bg-gray-800" aria-label="Notifikasi" aria-haspopup="dialog" aria-expanded="false" data-notification-toggle>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                                <span class="absolute -end-1 -top-1 hidden min-w-4 rounded-full bg-brand-600 px-1 py-0.5 text-[9px] font-bold text-white" data-notification-badge>0</span>
                            </button>
                            <section class="notification-dropdown absolute end-0 top-12 z-60" role="dialog" aria-label="Notifikasi" hidden data-notification-panel>
                                <header class="flex items-center justify-between border-b border-line px-4 py-3 dark:border-gray-800">
                                    <strong class="text-sm font-semibold text-heading dark:text-white/90">Notifikasi</strong>
                                    <button type="button" class="text-xs font-medium text-brand-ink hover:text-brand-ink dark:text-brand-300" data-notification-mark-all>Tandai semua dibaca</button>
                                </header>
                                <div class="max-h-96 overflow-y-auto" data-notification-list><p class="px-5 py-8 text-center text-xs font-medium text-soft">Memuat...</p></div>
                                <footer class="border-t border-line px-4 py-3 text-center dark:border-gray-800"><a href="{{ route('notifications.index') }}" class="text-xs font-medium text-brand-ink hover:text-brand-ink dark:text-brand-300">Lihat semua notifikasi <span aria-hidden="true">&rarr;</span></a></footer>
                            </section>
                        </div>
                    </div>
                </nav>
                @isset($navLinks)
                    <div class="border-t border-line px-4 py-2 xl:hidden dark:border-gray-800 sm:px-6">
                        <div class="flex flex-wrap items-center gap-2" data-mobile-page-actions>{{ $navLinks }}</div>
                    </div>
                @endisset
            </header>

            <main class="school-main mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 sm:py-8 xl:px-8">
                {{ $slot }}
                <footer class="school-footer mt-10 border-t border-line pt-5 text-center text-xs text-soft dark:border-gray-800 dark:text-muted">
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
