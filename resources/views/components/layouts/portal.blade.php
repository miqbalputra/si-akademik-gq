@php
    $isGuruPortal = ($portalLabel ?? null) === 'Portal Guru';
    $canAccessAttendance = $isGuruPortal && (auth()->user()?->canAccessAttendance() ?? false);
    $isTasmiExaminer = $isGuruPortal && (auth()->user()?->isTasmiExaminer() ?? false);
    $isHomeroomTeacher = $isGuruPortal && (auth()->user()?->canAccessAttendance() ?? false);
    $guruNavItems = [
        ['label' => 'Beranda', 'href' => route('guru.dashboard'), 'match' => ['guru.dashboard']],
        ['label' => 'Input Nilai', 'href' => route('guru.diniyyah-scores.index'), 'match' => ['guru.diniyyah-scores.*']],
        [
            'label' => 'Jurnal',
            'href' => route('guru.diniyyah-journals.index'),
            'match' => [
                'guru.diniyyah-journals.*',
                'guru.diniyyah-tafsir-journals.*',
                'guru.diniyyah-substitute-journals.*',
                'guru.diniyyah-substitute-tafsir-journals.*',
            ],
        ],
        ['label' => 'Tahfidz', 'href' => route('guru.tahfidz.index'), 'match' => ['guru.tahfidz.*']],
        ['label' => 'Kalender', 'href' => route('guru.calendar'), 'match' => ['guru.calendar']],
    ];
    // Menu Tasmi' (PJ Tasmi') — muncul bila guru ditugaskan sebagai PJ Tasmi'.
    if ($isTasmiExaminer) {
        $guruNavItems[] = ['label' => 'Tasmi\'', 'href' => route('guru.tasmi.index'), 'match' => ['guru.tasmi.index', 'guru.tasmi.create', 'guru.tasmi.store', 'guru.tasmi.records', 'guru.tasmi.edit', 'guru.tasmi.update', 'guru.tasmi.destroy']];
    }
    // Menu "Tasmi' Kelas Saya" (read-only) — muncul bila guru adalah wali kelas.
    if ($isHomeroomTeacher) {
        $guruNavItems[] = ['label' => 'Tasmi\' Kelas Saya', 'href' => route('guru.tasmi-wali.index'), 'match' => ['guru.tasmi-wali.*']];
    }
    if ($canAccessAttendance) {
        array_splice($guruNavItems, 1, 0, [[
            'label' => 'Presensi',
            'href' => route('attendance.index'),
            'match' => ['attendance.*'],
        ]]);
    }
@endphp

{{--
    Layout: portal.blade.php
    Shared portal layout for Guru and Wali Santri pages.
    
    Slots:
    - $title     : Page title (string)
    - $navLinks  : Optional extra nav links
    - slot:default : Main page content

    Props passed via component or directly:
    - $portalLabel : e.g. "Portal Wali Santri"
    - $accentColor : 'amber' | 'indigo' | 'emerald' (optional, default amber)
--}}
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal' }} — SIAKAD Griya Qur'an</title>
    <meta name="description" content="Sistem Informasi Akademik Griya Qur'an Tunas Ilmu">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Tailwind CSS --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: { sans: ['Outfit', 'sans-serif'] },
                        colors: {
                            brand: {
                                50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a',
                                300: '#fcd34d', 400: '#fbbf24', 500: '#f59e0b',
                                600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f'
                            }
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        .bg-grid {
            background-size: 40px 40px;
            background-image: linear-gradient(to right, rgba(0,0,0,.025) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(0,0,0,.025) 1px, transparent 1px);
        }
        .glass-card {
            background: rgba(255,255,255,.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,.6);
            box-shadow: 0 4px 20px -4px rgba(0,0,0,.06);
            border-radius: 20px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .hover-card { transition: all .25s cubic-bezier(.16,1,.3,1); }
        .hover-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px -8px rgba(0,0,0,.1); }
        .portal-nav {
            position: sticky; top: 0; z-index: 50;
            background: rgba(255,255,255,.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid #f1f5f9;
            box-shadow: 0 1px 0 rgba(0,0,0,.04);
        }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue  { background: #dbeafe; color: #1e40af; }
        .badge-indigo { background: #e0e7ff; color: #3730a3; }
        .badge-red   { background: #fee2e2; color: #991b1b; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .badge-purple { background: #f3e8ff; color: #6b21a8; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-weight: 700; font-size: 13px; border-radius: 10px; padding: 8px 18px; transition: all .2s; cursor: pointer; border: none; text-decoration: none; white-space: nowrap; }
        .btn-primary { background: #d97706; color: #fff; box-shadow: 0 2px 8px rgba(217,119,6,.3); }
        .btn-primary:hover { background: #b45309; transform: translateY(-1px); }
        .btn-secondary { background: #0f172a; color: #fff; }
        .btn-secondary:hover { background: #1e293b; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1.5px solid #e2e8f0; color: #475569; }
        .btn-outline:hover { background: #f8fafc; }
        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 8px; }
        .btn-lg { padding: 11px 24px; font-size: 15px; border-radius: 12px; }
        .stat-card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; padding: 20px; text-align: center; transition: all .2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px -8px rgba(0,0,0,.1); }
        .section-title { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .section-title h2 { font-size: 16px; font-weight: 800; color: #0f172a; white-space: nowrap; }
        .section-divider { flex: 1; height: 1px; background: #f1f5f9; }
        .empty-state { border: 2px dashed #e2e8f0; border-radius: 16px; padding: 40px 20px; text-align: center; }
        .empty-state p { color: #94a3b8; font-weight: 600; font-size: 13px; margin-top: 8px; }
        .form-input { width: 100%; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 8px 13px; font-size: 14px; font-weight: 500; color: #1e293b; background: #f8fafc; outline: none; transition: border-color .2s, background .2s; font-family: 'Outfit', sans-serif; }
        .form-input:focus { border-color: #f59e0b; background: #fff; box-shadow: 0 0 0 3px rgba(245,158,11,.1); }
        .portal-menu-summary::-webkit-details-marker { display: none; }
        .portal-nav-link { border: 1px solid transparent; transition: color .2s, background-color .2s, border-color .2s; }
        .portal-nav-link:hover { background: #f8fafc; color: #0f172a; }
        .portal-nav-link:focus-visible, .portal-menu-summary:focus-visible { outline: 3px solid rgba(245,158,11,.3); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) {
            .animate-fade-in-up { animation: none; opacity: 1; }
            .hover-card, .btn { transition: none; }
        }
        @keyframes fadeInUp { 0% { opacity: 0; transform: translateY(16px); } 100% { opacity: 1; transform: translateY(0); } }
        .animate-fade-in-up { animation: fadeInUp .6s cubic-bezier(.16,1,.3,1) forwards; opacity: 0; }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        .delay-300 { animation-delay: 300ms; }
    </style>

    @include('partials.pwa-head')

    @stack('head')
</head>
<body class="min-h-screen text-slate-800 antialiased overflow-x-hidden">

    {{-- Subtle background --}}
    <div class="fixed inset-0 z-[-1] pointer-events-none bg-grid opacity-60"></div>

    {{-- ===== TOP NAVIGATION ===== --}}
    <nav class="portal-nav" aria-label="Navigasi utama">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="flex min-h-16 items-center justify-between gap-4 py-3">

                {{-- Logo + School Name --}}
                <div class="flex items-center gap-3">
                    <a href="{{ $isGuruPortal ? route('guru.dashboard') : url('/') }}" class="group flex shrink-0 items-center gap-3 rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 font-black text-white text-sm shadow-md group-hover:shadow-amber-300/40 transition-shadow">
                            GQ
                        </span>
                        <div class="hidden sm:block">
                            <span class="block text-sm font-extrabold text-slate-800 leading-none">Griya Qur'an</span>
                            <span class="block text-[9px] font-bold uppercase tracking-widest text-amber-600 mt-0.5">{{ $portalLabel ?? 'SIAKAD' }}</span>
                        </div>
                    </a>
                    
                    @isset($breadcrumb)
                    <div class="ml-1 hidden items-center gap-2 border-l-2 border-slate-200 pl-3 sm:flex">
                        <span class="max-w-48 truncate text-xs font-bold text-slate-500">{{ $breadcrumb }}</span>
                    </div>
                    @endisset
                </div>

                {{-- Desktop navigation --}}
                <div class="hidden min-w-0 flex-1 items-center justify-end gap-1 lg:flex">
                    @if($isGuruPortal)
                        <div class="flex items-center gap-1 rounded-2xl bg-slate-50 p-1" aria-label="Menu guru">
                            @foreach($guruNavItems as $item)
                                @php($isActive = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                                <a href="{{ $item['href'] }}" class="portal-nav-link rounded-xl px-3 py-2 text-xs font-bold {{ $isActive ? 'border-slate-200 bg-white text-slate-900 shadow-sm' : 'text-slate-500' }}" @if($isActive) aria-current="page" @endif>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @else
                        @isset($navLinks)
                            {{ $navLinks }}
                        @endisset
                    @endif

                    @if($isGuruPortal)
                        @isset($navLinks)
                            {{ $navLinks }}
                        @endisset
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="ml-2">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                            Keluar
                        </button>
                    </form>
                </div>

                {{-- Mobile navigation --}}
                <div class="flex items-center gap-2 lg:hidden">
                    @if($isGuruPortal)
                        <details class="relative">
                            <summary class="portal-menu-summary flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm">
                                Menu
                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </summary>
                            <div class="absolute right-0 top-12 z-50 w-64 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/10">
                                <p class="px-3 pb-2 pt-1 text-[10px] font-black uppercase tracking-[.16em] text-slate-400">Menu utama</p>
                                @foreach($guruNavItems as $item)
                                    @php($isActive = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern)))
                                    <a href="{{ $item['href'] }}" class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-bold {{ $isActive ? 'bg-amber-50 text-amber-800' : 'text-slate-600 hover:bg-slate-50' }}" @if($isActive) aria-current="page" @endif>
                                        {{ $item['label'] }}
                                        @if($isActive)
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                        @endif
                                    </a>
                                @endforeach
                                @isset($navLinks)
                                    <div class="mt-2 border-t border-slate-100 pt-2">
                                        {{ $navLinks }}
                                    </div>
                                @endisset
                            </div>
                        </details>
                    @else
                        @isset($navLinks)
                            {{ $navLinks }}
                        @endisset
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500" aria-label="Keluar">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    {{-- ===== MAIN CONTENT ===== --}}
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-10">
        {{ $slot }}
    </main>

    {{-- ===== FOOTER ===== --}}
    <footer class="mt-12 border-t border-slate-100 bg-white/60 py-6 text-center text-xs font-medium text-slate-400">
        &copy; {{ date('Y') }} Griya Qur'an Tunas Ilmu
    </footer>

    @stack('scripts')
</body>
</html>
