<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Presensi Kelas — SIAKAD Griya Qur'an</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']},colors:{brand:{50:'#fffbeb',100:'#fef3c7',500:'#f59e0b',600:'#d97706',700:'#b45309'}}}}}</script>
    @endif
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #1e293b; margin: 0; -webkit-font-smoothing: antialiased; }
        .bg-grid { background-size: 40px 40px; background-image: linear-gradient(to right, rgba(0,0,0,.025) 1px, transparent 1px), linear-gradient(to bottom, rgba(0,0,0,.025) 1px, transparent 1px); }
        .portal-nav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.95); backdrop-filter: blur(16px); border-bottom: 1px solid #f1f5f9; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .hover-card { transition: all .25s cubic-bezier(.16,1,.3,1); }
        .hover-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px -8px rgba(0,0,0,.1); }
        .btn-primary { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 13px; border-radius: 10px; padding: 8px 18px; background: #d97706; color: #fff; border: none; cursor: pointer; transition: all .2s; box-shadow: 0 2px 8px rgba(217,119,6,.3); text-decoration: none; }
        .btn-primary:hover { background: #b45309; transform: translateY(-1px); }
        .btn-ghost { background: transparent; border: 1.5px solid #e2e8f0; color: #475569; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all .2s; display: inline-flex; align-items: center; gap: 5px; font-family: 'Outfit', sans-serif; }
        .btn-ghost:hover { background: #f8fafc; border-color: #fde68a; color: #d97706; }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .form-input { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; font-size: 13px; font-weight: 500; color: #1e293b; background: #f8fafc; outline: none; transition: border-color .2s; font-family: 'Outfit', sans-serif; }
        .form-input:focus { border-color: #f59e0b; background: #fff; box-shadow: 0 0 0 3px rgba(245,158,11,.1); }
        .section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .section-header h2 { font-size: 16px; font-weight: 800; color: #0f172a; white-space: nowrap; margin: 0; }
        .section-divider { flex: 1; height: 1px; background: #f1f5f9; }
        @keyframes fadeInUp { 0%{opacity:0;transform:translateY(14px)} 100%{opacity:1;transform:translateY(0)} }
        .fade-up { animation: fadeInUp .5s cubic-bezier(.16,1,.3,1) forwards; opacity: 0; }
        .delay-1 { animation-delay: .1s; }
        .delay-2 { animation-delay: .2s; }
    </style>
    @include('partials.pwa-head')
</head>
<body>
    <div class="fixed inset-0 z-[-1] bg-grid opacity-50"></div>

    {{-- Nav --}}
    <nav class="portal-nav">
        <div style="max-width:1100px;margin:0 auto;padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;">
            <a href="{{ url('/') }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                <span style="width:36px;height:36px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;color:#fff;">GQ</span>
                <div>
                    <span style="display:block;font-size:14px;font-weight:800;color:#0f172a;line-height:1.2;">Griya Qur'an</span>
                    <span style="display:block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#d97706;">Presensi Kelas</span>
                </div>
            </a>
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="btn-ghost">
                    <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                    Dashboard Guru
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Keluar</button>
                </form>
            </div>
        </div>
    </nav>

    <main style="max-width:1100px;margin:0 auto;padding:28px 24px;">

        {{-- Header --}}
        <header class="fade-up" style="margin-bottom:24px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <div style="display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:999px;padding:3px 12px;margin-bottom:10px;">
                        <span style="font-size:11px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.05em;">Presensi Harian</span>
                    </div>
                    <h1 style="font-size:26px;font-weight:900;color:#0f172a;margin:0 0 6px;letter-spacing:-.02em;">Presensi Kelas</h1>
                    <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">Pilih kelas dan bulan untuk menginput kehadiran santri (H / S / I / A / L).</p>
                </div>
            </div>
        </header>

        {{-- Filter --}}
        <div class="card fade-up delay-1" style="padding:20px 24px;margin-bottom:24px;display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;">
            <form method="GET" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;flex:1;">
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;">Bulan Input</label>
                    <input type="month" name="month" value="{{ $selectedMonth }}" class="form-input">
                </div>
                <button type="submit" class="btn-primary">Terapkan</button>
            </form>
        </div>

        @include('partials.upcoming-school-events', [
            'schoolEvents'          => $schoolEvents ?? collect(),
            'heading'               => 'Agenda Sekolah untuk Guru',
            'subheading'            => 'Outdoor, ujian, atau event sekolah yang ditetapkan admin.',
        ])

        {{-- Classes Grid --}}
        <div class="fade-up delay-2">
            <div class="section-header" style="margin-top:24px;">
                <h2>Daftar Kelas</h2>
                <div class="section-divider"></div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                @forelse ($classroomTerms as $classroomTerm)
                    <article class="card hover-card" style="padding:20px 24px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;">
                            <div>
                                <h2 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 4px;">{{ $classroomTerm->name }}</h2>
                                <p style="font-size:12px;color:#64748b;font-weight:500;margin:0;">
                                    {{ $classroomTerm->academicTerm?->academicYear?->name }} &middot; {{ $classroomTerm->academicTerm?->name }}
                                </p>
                            </div>
                            <span class="badge badge-slate">{{ $classroomTerm->enrollments_count }} santri</span>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;">
                            <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
                                <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin:0 0 3px;">Wali Kelas</p>
                                <p style="font-size:13px;font-weight:700;color:#0f172a;margin:0;">
                                    {{ $classroomTerm->homeroomAssignments->pluck('teacher.name')->filter()->implode(', ') ?: '—' }}
                                </p>
                            </div>
                            <div style="background:#f8fafc;border-radius:10px;padding:10px 12px;">
                                <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin:0 0 3px;">Status</p>
                                <p style="font-size:13px;font-weight:700;color:#0f172a;margin:0;">{{ $classroomTerm->status }}</p>
                            </div>
                        </div>

                        <a href="{{ route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => $selectedMonth]) }}" class="btn-primary" style="width:100%;justify-content:center;">
                            Buka Presensi
                            <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    </article>
                @empty
                    <div style="border:2px dashed #e2e8f0;border-radius:16px;padding:48px;text-align:center;grid-column:1/-1;">
                        <svg style="width:36px;height:36px;color:#cbd5e1;margin:0 auto 12px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
                        <p style="color:#94a3b8;font-weight:600;font-size:14px;margin:0;">Belum ada kelas yang dapat Anda akses untuk presensi.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </main>
</body>
</html>
