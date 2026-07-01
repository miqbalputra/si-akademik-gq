<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal Guru - SIAKAD Griya Qur'an</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Outfit', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #fafafa; }
        .bg-grid {
            background-size: 40px 40px;
            background-image: linear-gradient(to right, rgba(0, 0, 0, 0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(0, 0, 0, 0.03) 1px, transparent 1px);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease-out forwards;
        }
    </style>
    @include("partials.pwa-head")
</head>
<body class="min-h-screen text-slate-800 antialiased overflow-x-hidden selection:bg-amber-200 selection:text-amber-900">

    <!-- Background Elements -->
    <div class="fixed inset-0 z-[-1] pointer-events-none">
        <div class="absolute inset-0 bg-grid"></div>
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-amber-400/10 rounded-full mix-blend-multiply filter blur-[80px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[400px] h-[400px] bg-brand-500/10 rounded-full mix-blend-multiply filter blur-[80px]"></div>
    </div>

    <!-- Top Navigation -->
    <nav class="sticky top-0 z-50 glass-card border-b-0 border-white/40">
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <div class="flex h-16 items-center justify-between">
                <a href="{{ route('guru.dashboard') }}" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 font-bold text-white shadow-md">
                        GQ
                    </span>
                    <div>
                        <span class="block text-sm font-bold text-slate-800 leading-tight">Griya Qur'an</span>
                        <span class="block text-[9px] font-semibold uppercase tracking-wider text-slate-500">Portal Guru</span>
                    </div>
                </a>
                <div class="flex items-center gap-4">
                    <a href="{{ route('guru.calendar') }}" class="text-sm font-semibold text-slate-600 hover:text-amber-600">Kalender</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50 transition-colors shadow-sm">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        
        <!-- Header -->
        <header class="mb-8 rounded-3xl glass-card p-6 sm:p-8 animate-fade-in-up">
            <h1 class="text-3xl font-black text-slate-900 leading-tight">Selamat Datang, {{ $teacher->name ?? auth()->user()->name }}</h1>
            <p class="mt-2 text-sm font-semibold text-slate-500">Pilih menu di bawah sesuai dengan tugas dan tanggung jawab Anda.</p>
        </header>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <!-- 1. WALI KELAS WIDGET -->
            @if($homeroomClassroomTerms->isNotEmpty())
            <section class="rounded-3xl glass-card p-6 animate-fade-in-up" style="animation-delay: 50ms;">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-black text-slate-800">Wali Kelas</h2>
                </div>
                <p class="text-sm text-slate-500 mb-4">Kelola data absensi harian dan rekap data santri untuk kelas yang Anda ampu.</p>
                <div class="space-y-3 mb-4">
                    @foreach($homeroomClassroomTerms as $term)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-xs font-bold text-slate-800">{{ $term->name }}</p>
                            <p class="text-[10px] uppercase text-slate-400 font-semibold">{{ $term->academicTerm->name ?? 'Periode Aktif' }}</p>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('attendance.index') }}" class="block w-full text-center rounded-xl bg-blue-600 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition-colors">
                    Buka Portal Presensi
                </a>
            </section>
            @endif

            <!-- 2. GURU DINIYYAH WIDGET -->
            @if($diniyyahAssessmentSets->isNotEmpty())
            <section class="rounded-3xl glass-card p-6 animate-fade-in-up" style="animation-delay: 100ms;">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-black text-slate-800">Guru Diniyyah</h2>
                </div>
                <p class="text-sm text-slate-500 mb-4">Input nilai ujian dan tugas untuk mata pelajaran Diniyyah yang Anda ampu.</p>
                <div class="space-y-3 mb-4">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-bold text-emerald-800">Tugas Aktif</p>
                            <p class="text-[10px] text-emerald-600 font-semibold">Perlu diisi nilainya</p>
                        </div>
                        <span class="text-xl font-black text-emerald-700">{{ $diniyyahAssessmentSets->count() }}</span>
                    </div>
                </div>
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="block w-full text-center rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                    Input Nilai Diniyyah
                </a>
            </section>
            @endif

            <!-- 3. GURU TAHFIDZ WIDGET -->
            @if($tahfidzHalaqahs->isNotEmpty())
            <section class="rounded-3xl glass-card p-6 animate-fade-in-up" style="animation-delay: 150ms;">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-black text-slate-800">Guru Tahfidz</h2>
                </div>
                <p class="text-sm text-slate-500 mb-4">Catat setoran hafalan (Sabaq, Ziyadah) santri pada halaqah Anda.</p>
                <div class="space-y-3 mb-4">
                    @foreach($tahfidzHalaqahs->take(3) as $halaqah)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-xs font-bold text-slate-800">Halaqah {{ $halaqah->academicTerm->academicYear->name ?? '' }}</p>
                            <p class="text-[10px] uppercase text-slate-400 font-semibold">{{ $halaqah->activeMembers->count() }} Santri Aktif</p>
                        </div>
                    @endforeach
                    @if($tahfidzHalaqahs->count() > 3)
                        <p class="text-[10px] text-center text-slate-400 font-semibold">+ {{ $tahfidzHalaqahs->count() - 3 }} Halaqah Lainnya</p>
                    @endif
                </div>
                <a href="{{ route('guru.tahfidz.index') }}" class="block w-full text-center rounded-xl bg-purple-600 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-purple-700 transition-colors">
                    Buka Jurnal Tahfidz
                </a>
            </section>
            @endif

            <!-- 4. PENGUMUMAN & AGENDA (Fall back for empty cases, or just general info) -->
            @if($upcomingAlerts->isNotEmpty())
            <section class="rounded-3xl glass-card p-6 animate-fade-in-up lg:col-span-3" style="animation-delay: 200ms;">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-black text-slate-800">Agenda Terdekat</h2>
                </div>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($upcomingAlerts as $alert)
                        <article class="rounded-2xl border {{ $alert['kind'] === 'holiday' ? 'border-amber-200 bg-amber-50' : 'border-indigo-200 bg-indigo-50' }} p-4 shadow-sm relative overflow-hidden group">
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[9px] font-bold uppercase tracking-wider {{ $alert['kind'] === 'holiday' ? 'text-amber-800' : 'text-indigo-700' }}">{{ $alert['kind_label'] }}</span>
                                    <span class="inline-flex items-center rounded bg-white/60 px-1.5 py-0.5 text-[9px] font-bold {{ $alert['countdown_label'] === 'Hari ini' ? 'text-red-600' : 'text-slate-500' }}">
                                        {{ $alert['countdown_label'] }}
                                    </span>
                                </div>
                                <h4 class="font-black text-slate-800 leading-snug">{{ $alert['title'] }}</h4>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ $alert['date_label'] }}</p>
                                @if($alert['meta'])
                                    <p class="mt-1.5 text-[10px] font-semibold text-slate-500">{{ $alert['meta'] }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-4 text-center">
                    <a href="{{ route('guru.calendar') }}" class="inline-flex items-center gap-1 text-xs font-bold text-amber-600 hover:text-amber-700">
                        Lihat kalender lengkap &rarr;
                    </a>
                </div>
            </section>
            @else
            <section class="rounded-3xl border-2 border-dashed border-slate-200 bg-white/50 p-10 text-center text-slate-500 lg:col-span-3">
                <p class="text-sm font-bold">Tidak ada agenda sekolah terdekat yang dijadwalkan untuk Anda.</p>
            </section>
            @endif

        </div>
    </main>
</body>
</html>
