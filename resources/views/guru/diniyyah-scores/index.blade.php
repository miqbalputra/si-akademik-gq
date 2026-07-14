<x-layouts.portal title="Input Nilai Diniyyah" portalLabel="Portal Guru" breadcrumb="Input Nilai">
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
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Mata Pelajaran</p>
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
        <h2 class="text-lg font-bold text-slate-900">Daftar Mata Pelajaran</h2>
        <div class="h-px flex-1 bg-slate-200"></div>
    </div>

    <!-- Task List -->
    <section class="space-y-4 animate-fade-in-up" style="animation-delay: 250ms;">
        @forelse ($assessmentSets as $assessmentSet)
            @php
                $summary = $summaries[$assessmentSet->id] ?? null;
                $progress = $summary['progress_percentage'] ?? 0;
                $isComplete = $progress >= 100;
                $isReadOnly = in_array($assessmentSet->status, ['submitted', 'validated', 'published']);
                
                $badgeClass = 'bg-amber-100 text-amber-800';
                $badgeText = 'Perlu Isi';
                
                if ($isReadOnly) {
                    $badgeClass = 'bg-indigo-100 text-indigo-800';
                    $badgeText = $assessmentSet->status === 'validated' ? 'Tervalidasi' : ucfirst($assessmentSet->status);
                } elseif ($isComplete) {
                    $badgeClass = 'bg-emerald-100 text-emerald-800';
                    $badgeText = 'Lengkap';
                }
            @endphp

            <div class="hover-card block rounded-3xl glass-card p-6 border border-slate-100 group">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-black text-lg text-slate-900 group-hover:text-amber-700 transition-colors leading-tight">
                            {{ $assessmentSet->classSubject?->subject?->name ?? $assessmentSet->title }}
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">
                            {{ $assessmentSet->classSubject?->classroomTerm?->name }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                        {{ $badgeText }}
                    </span>
                </div>

                <div class="mt-5">
                    <div class="mb-2 flex justify-between text-xs font-bold text-slate-500">
                        <span>{{ $summary['complete_students'] ?? 0 }} dari {{ $summary['total_students'] ?? 0 }} santri dinilai</span>
                        <span class="{{ $isComplete || $isReadOnly ? 'text-emerald-600' : 'text-amber-600' }}">{{ $progress }}%</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $isComplete || $isReadOnly ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $progress }}%"></div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    <span class="rounded-lg bg-white/60 px-2.5 py-1.5 border border-slate-150">{{ $assessmentSet->components->count() }} komponen nilai</span>
                    <span class="rounded-lg bg-white/60 px-2.5 py-1.5 border border-slate-150">KKM {{ $assessmentSet->kkm ?? '-' }}</span>
                </div>
                
                <div class="mt-5 flex gap-3">
                    <a href="{{ route('guru.diniyyah-scores.edit', $assessmentSet) }}" class="flex-1 rounded-xl {{ $isReadOnly ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-amber-600 hover:bg-amber-700' }} px-4 py-2.5 text-center text-xs font-bold text-white transition-colors shadow-sm">
                        {{ $isReadOnly ? 'Lihat Nilai' : 'Isi Nilai' }}
                    </a>
                </div>
            </div>
        @empty
            <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white/50 p-10 text-center text-slate-500">
                <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
                <p class="text-sm font-bold">Belum ada mata pelajaran yang ditugaskan kepada Anda.</p>
            </div>
        @endforelse
    </section>
</x-layouts.portal>
