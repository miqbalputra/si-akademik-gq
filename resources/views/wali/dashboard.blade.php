<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Wali Santri - SIAKAD Griya Qur'an</title>

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
                        <span class="block text-[9px] font-semibold uppercase tracking-wider text-slate-500">Wali Santri</span>
                    </div>
                </a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('wali.calendar') }}" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition-colors">
                        Kalender
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition-colors">
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
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 mb-2">
                        Dashboard Wali Santri
                    </span>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900">Ahlan wa Sahlan, {{ $guardian?->name ?? auth()->user()->name }}</h1>
                    <p class="mt-2 text-sm text-slate-600 font-medium">Pantau rapor digital dan progres akademik ananda di sini.</p>
                </div>
            </div>
        </header>

        <!-- Stats Overview -->
        <section class="mb-8 grid grid-cols-3 gap-3 animate-fade-in-up" style="animation-delay: 100ms;">
            <div class="rounded-3xl glass-card p-5 text-center transition-transform hover:scale-[1.02]">
                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Jumlah Anak</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ $students->count() }}</p>
            </div>
            <div class="rounded-3xl border border-emerald-100 bg-emerald-50/80 backdrop-blur-md p-5 text-center transition-transform hover:scale-[1.02]">
                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296Z" />
                    </svg>
                </div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Rapor Terbit</p>
                <p class="mt-1 text-2xl font-black text-emerald-900">{{ $reportCards->count() }}</p>
            </div>
            <div class="rounded-3xl border border-amber-100 bg-amber-50/80 backdrop-blur-md p-5 text-center transition-transform hover:scale-[1.02]">
                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Belum Ada</p>
                <p class="mt-1 text-2xl font-black text-amber-900">{{ max($students->count() - $reportCardsByStudent->count(), 0) }}</p>
            </div>
        </section>

        @if (session('status'))
            <section class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 shadow-sm animate-fade-in-up">
                {{ session('status') }}
            </section>
        @endif

        @include('partials.upcoming-school-alerts', [
            'upcomingAlerts' => $upcomingAlerts ?? collect(),
            'heading' => 'Pengingat 7 Hari ke Depan',
            'subheading' => 'Ringkasan libur sekolah dan agenda terdekat.',
        ])

        @include('partials.upcoming-school-events', [
            'schoolEvents' => $schoolEvents,
            'guardianEventResponses' => $guardianEventResponses ?? collect(),
            'heading' => 'Agenda Sekolah untuk Wali Santri',
            'subheading' => 'Informasi event yang dibagikan admin untuk wali santri dan keluarga.',
        ])

        <!-- Children Connected -->
        <section class="mb-10 animate-fade-in-up" style="animation-delay: 200ms;">
            <div class="flex items-center gap-3 mb-5">
                <h2 class="text-lg font-bold text-slate-900">Anak Terhubung</h2>
                <div class="h-px flex-1 bg-slate-200"></div>
            </div>
            
            <div class="grid gap-5 md:grid-cols-2">
                @forelse ($students as $student)
                    @php($latestReport = $reportCardsByStudent->get($student->id)?->sortByDesc('published_at')->first())
                    <article class="group rounded-3xl glass-card p-6 transition-all hover:shadow-lg hover:shadow-slate-200/50 hover:-translate-y-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 font-bold text-lg">
                                    {{ substr($student->name, 0, 1) }}
                                </div>
                                <div>
                                    <h3 class="font-black text-lg text-slate-900">{{ $student->name }}</h3>
                                    <p class="text-xs font-bold text-slate-500">NIS {{ $student->nis }}</p>
                                </div>
                            </div>
                            <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider {{ $latestReport ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $latestReport ? 'Ada rapor' : 'Belum ada' }}
                            </span>
                        </div>
                        
                        @if ($latestReport)
                            <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                                <div class="rounded-2xl bg-white/60 p-3 border border-slate-100">
                                    <p class="text-[10px] font-bold uppercase text-slate-500 mb-1">Rata-rata</p>
                                    <p class="font-black text-slate-800 text-lg">{{ $latestReport->average_score ?? '-' }}</p>
                                </div>
                                <div class="rounded-2xl bg-white/60 p-3 border border-slate-100">
                                    <p class="text-[10px] font-bold uppercase text-slate-500 mb-1">Rank</p>
                                    <p class="font-black text-amber-600 text-lg">#{{ $latestReport->rank_in_class ?? '-' }}</p>
                                </div>
                                <div class="rounded-2xl bg-white/60 p-3 border border-slate-100">
                                    <p class="text-[10px] font-bold uppercase text-slate-500 mb-1">Periode</p>
                                    <p class="font-black text-slate-800">{{ $latestReport->academicTerm?->semester ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="mt-6 flex flex-col gap-2">
                                <a href="{{ route('report-cards.show', $latestReport) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white shadow-md hover:bg-amber-600 transition-colors">
                                    Buka Rapor Terbaru
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                                <a href="{{ route('report-cards.print', $latestReport) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:border-slate-300 hover:bg-slate-50 transition-colors">
                                    Cetak PDF
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                </a>
                            </div>
                        @else
                            <div class="mt-6 rounded-2xl bg-slate-50/80 border border-slate-100 p-4 text-center">
                                <p class="text-xs font-semibold text-slate-500">Rapor anak ini belum dipublikasikan.</p>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white/50 p-10 text-center md:col-span-2">
                        <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <p class="text-sm font-bold text-slate-500">Belum ada data anak yang terhubung ke akun ini.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <!-- History -->
        <section class="animate-fade-in-up" style="animation-delay: 300ms;">
            <div class="flex items-center gap-3 mb-5">
                <h2 class="text-lg font-bold text-slate-900">Riwayat Rapor</h2>
                <div class="h-px flex-1 bg-slate-200"></div>
            </div>
            
            <div class="space-y-4">
                @forelse ($reportCards as $reportCard)
                    <a href="{{ route('report-cards.show', $reportCard) }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-3xl glass-card p-5 transition-all hover:border-amber-300 hover:shadow-md hover:bg-white group">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-black text-slate-900 group-hover:text-amber-700 transition-colors">{{ $reportCard->student?->name }}</h3>
                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                    {{ $reportCard->classroomTerm?->name }} &middot; {{ $reportCard->academicTerm?->name }} {{ $reportCard->academicTerm?->academicYear?->name }}
                                </p>
                                <p class="mt-1 text-[10px] font-bold text-slate-400">Dipublish {{ $reportCard->published_at?->format('d M Y H:i') ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-2xl border border-slate-100">
                            <div class="text-center px-3">
                                <p class="text-[9px] font-bold uppercase text-slate-400">Rata-rata</p>
                                <p class="font-black text-slate-800">{{ $reportCard->average_score ?? '-' }}</p>
                            </div>
                            <div class="w-px h-8 bg-slate-200"></div>
                            <div class="text-center px-3">
                                <p class="text-[9px] font-bold uppercase text-slate-400">Rank</p>
                                <p class="font-black text-amber-600">#{{ $reportCard->rank_in_class ?? '-' }}</p>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-200 bg-white/50 p-8 text-center">
                        <p class="text-sm font-bold text-slate-500">Belum ada rapor yang dipublikasikan.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
