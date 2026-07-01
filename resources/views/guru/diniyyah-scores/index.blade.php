<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input Nilai Diniyyah - SIAKAD Griya Qur'an</title>

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
        .premium-card {
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .premium-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px -10px rgba(217, 119, 6, 0.1);
            border-color: rgba(217, 119, 6, 0.2);
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
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 font-bold text-white shadow-md">
                        GQ
                    </span>
                    <div>
                        <span class="block text-sm font-bold text-slate-800 leading-tight">Griya Qur'an</span>
                        <span class="block text-[9px] font-semibold uppercase tracking-wider text-slate-500">Portal Guru</span>
                    </div>
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('guru.calendar') }}" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition-colors">
                        Kalender
                    </a>
                    <a href="{{ route('attendance.index') }}" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-100 transition-colors">
                        Presensi
                    </a>
                    <a href="{{ route('guru.dashboard') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                        Dashboard
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition-colors">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">

        <!-- Welcome Header -->
        <header class="mb-8 rounded-3xl glass-card p-6 sm:p-8 animate-fade-in-up">
            <div>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 mb-2">
                    Panel Guru Pengajar
                </span>
                <h1 class="text-3xl font-black tracking-tight text-slate-900">Input Nilai Diniyyah</h1>
                <p class="mt-2 text-sm text-slate-600 font-medium">Isi dan evaluasi nilai mata pelajaran Diniyyah untuk kelas binaan Anda.</p>
            </div>
        </header>

        @php
            $totalSets = $assessmentSets->count();
            $completedSets = $summaries->filter(fn ($summary) => $summary['progress_percentage'] >= 100)->count();
            $needInputSets = max($totalSets - $completedSets, 0);
        @endphp

        <!-- Stats Overview -->
        <section class="mb-8 grid grid-cols-3 gap-3 animate-fade-in-up" style="animation-delay: 100ms;">
            <div class="rounded-3xl glass-card p-5 text-center transition-transform hover:scale-[1.02]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Tugas Penilaian</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ $totalSets }}</p>
            </div>
            <div class="rounded-3xl border border-emerald-100 bg-emerald-50/80 backdrop-blur-md p-5 text-center transition-transform hover:scale-[1.02]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Lengkap</p>
                <p class="mt-1 text-2xl font-black text-emerald-900">{{ $completedSets }}</p>
            </div>
            <div class="rounded-3xl border border-amber-100 bg-amber-50/80 backdrop-blur-md p-5 text-center transition-transform hover:scale-[1.02]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Perlu Input</p>
                <p class="mt-1 text-2xl font-black text-amber-900">{{ $needInputSets }}</p>
            </div>
        </section>

        @include('partials.upcoming-school-alerts', [
            'upcomingAlerts' => $upcomingAlerts ?? collect(),
            'heading' => 'Pengingat 7 Hari ke Depan',
            'subheading' => 'Libur sekolah dan event yang perlu diperhatikan guru minggu ini.',
        ])

        @include('partials.upcoming-school-events', [
            'schoolEvents' => $schoolEvents ?? collect(),
            'heading' => 'Agenda Sekolah untuk Guru',
            'subheading' => 'Agenda sekolah dan kegiatan yang dibagikan admin untuk guru.',
        ])

        <!-- Active Tasks Header -->
        <div class="flex items-center gap-3 mb-5 mt-8 animate-fade-in-up" style="animation-delay: 200ms;">
            <h2 class="text-lg font-bold text-slate-900">Daftar Tugas Penilaian</h2>
            <div class="h-px flex-1 bg-slate-200"></div>
        </div>

        <!-- Task List -->
        <section class="space-y-4 animate-fade-in-up" style="animation-delay: 250ms;">
            @forelse ($assessmentSets as $assessmentSet)
                @php
                    $summary = $summaries[$assessmentSet->id] ?? null;
                    $progress = $summary['progress_percentage'] ?? 0;
                    $isComplete = $progress >= 100;
                @endphp

                <div class="premium-card block rounded-3xl glass-card p-6 border border-slate-100 group">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-black text-lg text-slate-900 group-hover:text-amber-700 transition-colors leading-tight">{{ $assessmentSet->title }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">
                                {{ $assessmentSet->classSubject?->classroomTerm?->name }} &middot; {{ $assessmentSet->classSubject?->subject?->name }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider {{ $isComplete ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $isComplete ? 'Lengkap' : 'Perlu Isi' }}
                        </span>
                    </div>

                    <div class="mt-5">
                        <div class="mb-2 flex justify-between text-xs font-bold text-slate-500">
                            <span>{{ $summary['complete_students'] ?? 0 }} dari {{ $summary['total_students'] ?? 0 }} santri dinilai</span>
                            <span class="{{ $isComplete ? 'text-emerald-600' : 'text-amber-600' }}">{{ $progress }}%</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ $isComplete ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <span class="rounded-lg bg-white/60 px-2.5 py-1.5 border border-slate-150">{{ $assessmentSet->components->count() }} komponen nilai</span>
                        <span class="rounded-lg bg-white/60 px-2.5 py-1.5 border border-slate-150">KKM {{ $assessmentSet->kkm ?? '-' }}</span>
                        <span class="rounded-lg bg-white/60 px-2.5 py-1.5 border border-slate-150">{{ $assessmentSet->assessment_method }}</span>
                    </div>
                    
                    <div class="mt-5 flex gap-3">
                        <a href="{{ route('guru.diniyyah-scores.edit', $assessmentSet) }}" class="flex-1 rounded-xl bg-amber-600 px-4 py-2.5 text-center text-xs font-bold text-white hover:bg-amber-700 transition-colors shadow-sm">
                            Isi Nilai
                        </a>
                        <a href="{{ route('guru.diniyyah-attendance.edit', $assessmentSet) }}" class="flex-1 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-center text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition-colors shadow-sm">
                            Isi Presensi Harian
                        </a>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white/50 p-10 text-center text-slate-500">
                    <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                    <p class="text-sm font-bold">Belum ada assessment aktif yang ditugaskan kepada Anda.</p>
                </div>
            @endforelse
        </section>
    </main>
</body>
</html>
