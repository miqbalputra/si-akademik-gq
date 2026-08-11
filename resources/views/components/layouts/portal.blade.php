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

                    {{-- Notification Bell --}}
                    <div id="notif-bell-wrap" class="relative ml-2">
                        <button id="notif-bell-btn" type="button" aria-label="Notifikasi" aria-haspopup="true" aria-expanded="false" class="relative flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                            <span id="notif-badge" style="display:none;position:absolute;top:-4px;right:-4px;min-width:16px;height:16px;padding:0 4px;background:#dc2626;color:#fff;font-size:10px;font-weight:800;border-radius:999px;align-items:center;justify-content:center;line-height:1;">0</span>
                        </button>
                        <div id="notif-dropdown" style="display:none;position:absolute;right:0;top:44px;z-index:60;width:360px;max-width:calc(100vw - 24px);background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 12px 40px -8px rgba(0,0,0,.15);overflow:hidden;">
                            <div style="padding:14px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
                                <strong style="font-size:13px;color:#0f172a;">Notifikasi</strong>
                                <button type="button" id="notif-mark-all" style="font-size:11px;font-weight:700;color:#6b21a8;background:none;border:none;cursor:pointer;padding:0;">Tandai semua dibaca</button>
                            </div>
                            <div id="notif-list" style="max-height:380px;overflow-y:auto;">
                                <div style="padding:24px;text-align:center;color:#94a3b8;font-size:12px;font-weight:600;">Memuat...</div>
                            </div>
                            <div style="padding:10px 16px;border-top:1px solid #f1f5f9;text-align:center;">
                                <a href="{{ route('notifications.index') }}" style="font-size:12px;font-weight:700;color:#6b21a8;text-decoration:none;">Lihat semua notifikasi →</a>
                            </div>
                        </div>
                    </div>

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

                    {{-- Notification Bell (mobile) — reuse same dropdown --}}
                    <button type="button" onclick="document.getElementById('notif-bell-btn').click()" class="relative flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition-colors hover:bg-slate-50" aria-label="Notifikasi">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                        <span class="notif-badge-mirror" style="display:none;position:absolute;top:-4px;right:-4px;min-width:16px;height:16px;padding:0 4px;background:#dc2626;color:#fff;font-size:10px;font-weight:800;border-radius:999px;align-items:center;justify-content:center;line-height:1;">0</span>
                    </button>

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

    {{-- ===== NOTIFICATION POLLING (30 detik) ===== --}}
    <script>
    (function(){
        const feedUrl = "{{ route('notifications.feed') }}";
        const readUrl = (id) => "{{ route('notifications.read', '__ID__') }}".replace('__ID__', id);
        const markAllUrl = "{{ route('notifications.read-all') }}";
        const csrf = "{{ csrf_token() }}";
        let pollTimer = null;

        function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }

        function severityIcon(sev){
            const m = { success:'✓', warning:'!', danger:'!', info:'i' };
            return m[sev] || 'i';
        }
        function severityClass(sev){
            const m = { success:'bg-emerald-50 text-emerald-700', warning:'bg-amber-50 text-amber-700', danger:'bg-rose-50 text-rose-700', info:'bg-blue-50 text-blue-700' };
            return m[sev] || 'bg-slate-50 text-slate-600';
        }

        function renderList(notifs){
            const list = document.getElementById('notif-list');
            if(!list) return;
            if(!notifs.length){
                list.innerHTML = '<div style="padding:32px 24px;text-align:center;color:#94a3b8;font-size:12px;font-weight:600;">Tidak ada notifikasi baru.</div>';
                return;
            }
            list.innerHTML = notifs.map(n => `
                <a href="${n.link_url || '#'}" data-id="${n.id}" class="notif-link" style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border-bottom:1px solid #f1f5f9;text-decoration:none;${n.status==='unread'?'background:#fdfbff;':''}">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold ${severityClass(n.severity)}">${severityIcon(n.severity)}</span>
                    <span style="flex:1;min-width:0;">
                        <span style="display:block;font-size:12px;font-weight:700;color:#0f172a;line-height:1.3;">${escapeHtml(n.title)}${n.batch_count>1?` <span style="font-size:10px;color:#64748b;">×${n.batch_count}</span>`:''}</span>
                        <span style="display:block;font-size:11px;color:#64748b;margin-top:2px;line-height:1.4;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${escapeHtml(n.body)}</span>
                        <span style="display:block;font-size:10px;color:#cbd5e1;margin-top:4px;">${escapeHtml(n.created_at||'')}</span>
                    </span>
                    ${n.status==='unread'?'<span style="width:6px;height:6px;border-radius:999px;background:#dc2626;flex-shrink:0;margin-top:6px;"></span>':''}
                </a>
            `).join('');

            // Auto mark as read on click + redirect.
            list.querySelectorAll('.notif-link').forEach(a => {
                a.addEventListener('click', function(e){
                    const id = this.dataset.id;
                    if(id){
                        fetch(readUrl(id), { method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'} }).catch(()=>{});
                    }
                    // biarkan navigasi terjadi (link href)
                });
            });
        }

        function updateBadge(count){
            const badge = document.getElementById('notif-badge');
            const mirror = document.querySelector('.notif-badge-mirror');
            if(count > 0){
                if(badge){ badge.textContent = count > 99 ? '99+' : count; badge.style.display = 'flex'; }
                if(mirror){ mirror.textContent = count > 99 ? '99+' : count; mirror.style.display = 'flex'; }
            } else {
                if(badge){ badge.style.display = 'none'; }
                if(mirror){ mirror.style.display = 'none'; }
            }
        }

        async function poll(){
            try{
                const res = await fetch(feedUrl, { headers:{'Accept':'application/json'} });
                if(!res.ok) return;
                const data = await res.json();
                updateBadge(data.unread_count || 0);
                renderList(data.notifications || []);
            }catch(e){ /* silent */ }
        }

        // Toggle dropdown.
        const btn = document.getElementById('notif-bell-btn');
        const dd = document.getElementById('notif-dropdown');
        if(btn && dd){
            btn.addEventListener('click', function(e){
                e.stopPropagation();
                const open = dd.style.display === 'block';
                dd.style.display = open ? 'none' : 'block';
                btn.setAttribute('aria-expanded', open ? 'false' : 'true');
                if(!open){ poll(); }
            });
            document.addEventListener('click', function(e){
                if(!dd.contains(e.target) && !btn.contains(e.target)){ dd.style.display = 'none'; btn.setAttribute('aria-expanded','false'); }
            });
        }

        // Mark all read.
        const markAll = document.getElementById('notif-mark-all');
        if(markAll){
            markAll.addEventListener('click', async function(e){
                e.preventDefault();
                try{
                    await fetch(markAllUrl, { method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'} });
                    poll();
                }catch(err){}
            });
        }

        // Initial + polling 30 detik.
        poll();
        pollTimer = setInterval(poll, 30000);
    })();
    </script>
</body>
</html>
