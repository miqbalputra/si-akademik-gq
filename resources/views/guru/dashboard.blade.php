<x-layouts.portal title="Dashboard Guru" portalLabel="Portal Guru">
    <x-slot name="navLinks">
        <a href="{{ route('guru.calendar') }}" class="btn btn-ghost {{ request()->routeIs('guru.calendar') ? 'bg-slate-100 text-slate-800' : 'text-slate-500 hover:bg-slate-50' }}">Kalender</a>
    </x-slot>

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
            <div class="mt-4 grid grid-cols-2 gap-3">
                <a href="{{ route('attendance.index') }}" class="block w-full text-center rounded-xl bg-blue-600 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition-colors">
                    Portal Presensi
                </a>
                <a href="{{ route('wali.diniyyah-journals.index') }}" class="block w-full text-center rounded-xl bg-indigo-600 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                    Pantau Jurnal
                </a>
            </div>
        </section>
        @endif

        <!-- 2. GURU DINIYYAH WIDGET -->
        @if($diniyyahAssessmentSets->isNotEmpty() || $diniyyahAssignments->isNotEmpty())
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
            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 transition-transform hover:scale-[1.02]">
                    <div class="mb-1 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-slate-800">Tugas Aktif</p>
                            <p class="text-xs font-semibold text-emerald-600">Perlu diisi nilainya</p>
                        </div>
                        <span class="text-xl font-black text-emerald-700">{{ $diniyyahAssessmentSets->count() }}</span>
                    </div>
                </div>
                <div class="rounded-xl border border-teal-100 bg-teal-50/50 p-4 transition-transform hover:scale-[1.02]">
                    <div class="mb-1 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-slate-800">Jadwal Mengajar</p>
                            <p class="text-xs font-semibold text-teal-600">Aktif di semester ini</p>
                        </div>
                        <span class="text-xl font-black text-teal-700">{{ $diniyyahAssignments->count() }}</span>
                    </div>
                </div>
            </div>
            @php
                $singleScoresLink = route('guru.diniyyah-scores.index');
                    
                $diniyyahClasses = $diniyyahAssignments->pluck('classSubject.classroomTerm')->filter()->unique('id');
                $singleJournalLink = $diniyyahClasses->count() === 1 
                    ? route('guru.diniyyah-journals.index', ['classroom_term_id' => $diniyyahClasses->first()->id]) 
                    : route('guru.diniyyah-journals.index');
            @endphp
            <div class="flex flex-wrap gap-2">
                <a href="{{ $singleScoresLink }}" class="flex-1 min-w-[30%] text-center rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                    Input Nilai
                </a>
                <a href="{{ $singleJournalLink }}" class="flex-1 min-w-[30%] text-center rounded-xl bg-teal-600 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition-colors">
                    Jurnal Kelas
                </a>
            </div>
        </section>
        @endif

        <!-- 3. GURU TAHFIDZ WIDGET -->
        @if($tahfidzHalaqahs->isNotEmpty())
        <section class="rounded-3xl glass-card p-6 animate-fade-in-up" style="animation-delay: 150ms;">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477-4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
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

        <!-- 4. PENGUMUMAN & AGENDA -->
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
</x-layouts.portal>
