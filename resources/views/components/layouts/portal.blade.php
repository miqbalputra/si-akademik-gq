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
        ['label' => 'Buat RPP', 'href' => route('guru.rpp.create'), 'match' => ['guru.rpp.create']],
        ['label' => 'Referensi RPP', 'href' => route('guru.rpp.references'), 'match' => ['guru.rpp.references']],
        ['label' => 'Promes Saya', 'href' => route('guru.rpp.promes'), 'match' => ['guru.rpp.promes']],
        ['label' => 'Sampah RPP', 'href' => route('guru.rpp.trash'), 'match' => ['guru.rpp.trash']],
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
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
    @stack('head')
</head>
<body class="app-shell overflow-x-hidden text-slate-800 antialiased">
    @if(in_array($portalLabel ?? null, ['Portal Guru', 'Portal Wali Santri'], true))
        @include('partials.pwa-install-prompt')
    @endif
    <header class="school-header">
        <nav class="school-header-inner" aria-label="Navigasi {{ $portalLabel ?? 'portal' }}">
            <a href="{{ $portalHomeUrl }}" class="school-brand">
                <span class="school-mark">GQ</span>
                <span>
                    <strong>Ruang GQ</strong>
                    <small>Griya Qur'an Tunas Ilmu</small>
                </span>
            </a>

            <div class="school-context">
                <strong>{{ $portalLabel ?? 'Ruang GQ' }}</strong>
                <span>{{ $breadcrumb ?? 'Kegiatan akademik' }}</span>
            </div>

            <div class="school-nav" aria-label="Menu kegiatan">
                <a href="{{ $portalHomeUrl }}" class="school-nav-link school-nav-home" @if($isHomeActive) aria-current="page" @endif>Beranda</a>
                @if($isGuruPortal || $isTahfidzPortal || $isDiniyyahPortal)
                    @foreach($portalNavGroups as $group)
                        @php($isGroupActive = collect($group['items'])->contains(fn ($item) => collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern))))
                        <details class="school-nav-dropdown" data-portal-menu>
                            <summary class="school-nav-dropdown-toggle @if($isGroupActive) is-active @endif" aria-expanded="false">
                                <span>{{ $group['label'] }}</span>
                                <svg class="school-nav-chevron" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="school-nav-dropdown-panel" role="menu" aria-label="{{ $group['label'] }}">
                                @foreach($group['items'] as $item)
                                    @php($isActive = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                                    <a href="{{ $item['href'] }}" class="school-nav-dropdown-link" role="menuitem" @if($isActive) aria-current="page" @endif>
                                        <span>{{ $item['label'] }}</span>
                                        @if($isActive)<span class="school-nav-active-dot" aria-hidden="true"></span>@endif
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                @else
                    @foreach($portalNavGroups as $group)
                        <div class="school-nav-group" aria-label="{{ $group['label'] }}">
                            @foreach($group['items'] as $item)
                                @php($isActive = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                                <a href="{{ $item['href'] }}" class="school-nav-link" @if($isActive) aria-current="page" @endif>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="school-header-actions" data-notification-root data-feed-url="{{ route('notifications.feed') }}" data-read-url-template="{{ route('notifications.read', '__ID__') }}" data-mark-all-url="{{ route('notifications.read-all') }}">
                @isset($navLinks)
                    <div class="hidden items-center gap-1 xl:flex">{{ $navLinks }}</div>
                @endisset
                @if(count($workspaceItems) > 1)
                    <details class="school-nav-dropdown hidden sm:block" data-portal-menu>
                        <summary class="school-nav-dropdown-toggle" aria-expanded="false"><span>Ganti Ruang</span><svg class="school-nav-chevron" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></summary>
                        <div class="school-nav-dropdown-panel" role="menu" aria-label="Ganti ruang kerja">
                            @foreach($workspaceItems as $key => $workspace)
                                <a href="{{ $workspace['destination'] }}" class="school-nav-dropdown-link" role="menuitem" @if($currentWorkspace === $key) aria-current="page" @endif><span>{{ $workspace['label'] }}</span>@if($currentWorkspace === $key)<span class="school-nav-active-dot" aria-hidden="true"></span>@endif</a>
                            @endforeach
                        </div>
                    </details>
                @endif
                <div class="relative">
                    <button type="button" class="relative flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-white text-ink transition hover:border-ink" aria-label="Notifikasi" aria-haspopup="dialog" aria-expanded="false" data-notification-toggle>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                        <span class="absolute -right-1 -top-1 hidden min-w-4 rounded-full bg-ink px-1 py-0.5 font-mono text-[9px] font-black text-neon" data-notification-badge>0</span>
                    </button>
                    <section class="notification-dropdown absolute right-0 top-12 z-60" role="dialog" aria-label="Notifikasi" hidden data-notification-panel>
                        <header class="flex items-center justify-between border-b border-line px-4 py-3"><strong class="text-xs text-ink">Notifikasi</strong><button type="button" class="font-mono text-[10px] font-bold text-slate-500 underline decoration-neon decoration-2 underline-offset-4" data-notification-mark-all>Tandai semua dibaca</button></header>
                        <div class="max-h-96 overflow-y-auto" data-notification-list><p class="px-5 py-8 text-center text-xs font-bold text-slate-400">Memuat...</p></div>
                        <footer class="border-t border-line px-4 py-3 text-center"><a href="{{ route('notifications.index') }}" class="font-mono text-[10px] font-bold text-ink underline decoration-neon decoration-2 underline-offset-4">Lihat semua notifikasi <span aria-hidden="true">&rarr;</span></a></footer>
                    </section>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">@csrf<button type="submit" class="btn btn-outline btn-sm">Keluar</button></form>
                <details class="school-mobile-menu relative" data-portal-menu>
                    <summary class="school-menu-summary flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-line bg-white px-3 text-xs font-extrabold text-ink" aria-expanded="false">Menu <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></summary>
                    <div class="absolute right-0 top-12 z-50 w-72 rounded-xl border border-line bg-white p-2 shadow-2xl shadow-slate-950/10" role="dialog" aria-label="Menu portal">
                        <p class="px-3 pb-2 pt-1 font-mono text-[10px] font-bold tracking-[.1em] text-slate-500">{{ $portalLabel ?? 'Ruang GQ' }}</p>
                        <a href="{{ $portalHomeUrl }}" class="school-mobile-link" @if($isHomeActive) aria-current="page" @endif>Beranda @if($isHomeActive)<span class="h-2 w-2 rounded-full bg-neon"></span>@endif</a>
                        @if(count($workspaceItems) > 1)
                            <section class="school-mobile-group" aria-label="Ganti ruang kerja"><p>Ganti Ruang Kerja</p>@foreach($workspaceItems as $key => $workspace)<a href="{{ $workspace['destination'] }}" class="school-mobile-link" @if($currentWorkspace === $key) aria-current="page" @endif>{{ $workspace['label'] }} @if($currentWorkspace === $key)<span class="h-2 w-2 rounded-full bg-neon"></span>@endif</a>@endforeach</section>
                        @endif
                        @foreach($portalNavGroups as $group)
                            <section class="school-mobile-group" aria-label="{{ $group['label'] }}">
                                <p>{{ $group['label'] }}</p>
                                @foreach($group['items'] as $item)
                                    @php($isActive = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                                    <a href="{{ $item['href'] }}" class="school-mobile-link" @if($isActive) aria-current="page" @endif>{{ $item['label'] }} @if($isActive)<span class="h-2 w-2 rounded-full bg-neon"></span>@endif</a>
                                @endforeach
                            </section>
                        @endforeach
                        @isset($navLinks)<div class="mt-2 border-t border-line pt-2">{{ $navLinks }}</div>@endisset
                        <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-line pt-2 sm:hidden">@csrf<button type="submit" class="school-mobile-link w-full">Keluar <span aria-hidden="true">&rarr;</span></button></form>
                    </div>
                </details>
            </div>
        </nav>
    </header>

    <main class="school-main">{{ $slot }}</main>
    <footer class="school-footer">&copy; {{ date('Y') }} RUANG GQ · GRIYA QUR'AN TUNAS ILMU · Dikembangkan oleh Muhammad Iqbal Putra — SchoolVia.id</footer>
    @if($isGuruPortal)
        <x-journal-overdue-reminder :journal-overdue-reminder="$journalOverdueReminder ?? null" />
        <x-tasmi-wali-reminder :tasmi-wali-reminder="$tasmiWaliReminder ?? null" />
    @endif
    @stack('scripts')
</body>
</html>
