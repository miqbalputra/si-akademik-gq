<x-layouts.portal title="Input Nilai Diniyyah" portalLabel="Portal Guru" breadcrumb="Input Nilai">
    <header class="portal-page-header animate-fade-in-up">
        <div>
            <p class="school-index">Panel Guru Pengajar</p>
            <h1 class="mt-2 text-heading ui-page-title">Input Nilai Diniyyah</h1>
            <p class="mt-2 text-theme-sm text-body font-normal">Isi dan evaluasi nilai mata pelajaran Diniyyah untuk kelas binaan Anda.</p>
        </div>
    </header>

    @php
        $totalSets = $assessmentSets->count();
        $completedSets = $summaries->filter(fn ($summary) => $summary['progress_percentage'] >= 100)->count();
        $needInputSets = max($totalSets - $completedSets, 0);
    @endphp

    <!-- Stats Overview -->
    <section class="mb-8 grid grid-cols-3 gap-3 animate-fade-in-up" style="animation-delay:100ms;">
        <div class="metric-card text-center transition-transform hover:scale-[1.02]">
            <p class="metric-label ui-metric-label">Mata Pelajaran</p>
            <p class="metric-value">{{ $totalSets }}</p>
        </div>
        <div class="metric-card border-success-line bg-success-soft/70 text-center transition-transform hover:scale-[1.02]">
            <p class="metric-label text-success-ink ui-metric-label">Lengkap</p>
            <p class="metric-value text-success-ink">{{ $completedSets }}</p>
        </div>
        <div class="metric-card border-brand-line bg-brand-soft/70 text-center transition-transform hover:scale-[1.02]">
            <p class="metric-label text-school-600 ui-metric-label">Perlu Input</p>
            <p class="metric-value text-school-800">{{ $needInputSets }}</p>
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
    <div class="section-title mt-8 animate-fade-in-up" style="animation-delay:200ms;">
        <h2>Daftar Mata Pelajaran</h2>
    </div>

    <!-- Task List -->
    <section class="space-y-4 animate-fade-in-up" style="animation-delay:250ms;">
        @forelse ($assessmentSets as $assessmentSet)
            @php
                $summary = $summaries[$assessmentSet->id] ?? null;
                $progress = $summary['progress_percentage'] ?? 0;
                $isComplete = $progress >= 100;
                $isReadOnly = in_array($assessmentSet->status, ['submitted', 'validated', 'published']);
                
                $badgeClass = 'border border-brand-line bg-brand-soft text-school-700';
                $badgeText = 'Perlu Isi';
                
                if ($isReadOnly) {
                    $badgeClass = 'border border-brand-line bg-brand-soft text-brand-ink';
                    $badgeText = \App\Support\UiLabel::statusLabel($assessmentSet->status);
                } elseif ($isComplete) {
                    $badgeClass = 'border border-success-line bg-success-soft text-success-ink';
                    $badgeText = 'Lengkap';
                }
            @endphp

            <article class="action-card group rounded-2xl p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-heading group-hover:text-school-700 transition-colors ui-card-title">
                            {{ $assessmentSet->classSubject?->subject?->name ?? $assessmentSet->title }}
                        </h2>
                        <p class="mt-1 text-theme-sm font-normal text-muted">
                            {{ $assessmentSet->classSubject?->classroomTerm?->name }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 {{ $badgeClass }} text-theme-xs font-medium">
                        {{ $badgeText }}
                    </span>
                </div>

                <div class="mt-5">
                    <div class="mb-2 flex justify-between text-theme-xs font-normal text-muted">
                        <span>{{ $summary['complete_students'] ?? 0 }} dari {{ $summary['total_students'] ?? 0 }} santri dinilai</span>
                        <span class="{{ $isComplete || $isReadOnly ? 'text-success-ink' : 'text-school-600' }}">{{ $progress }}%</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-surface-muted">
                        <div class="h-full rounded-full {{ $isComplete || $isReadOnly ? 'bg-success-500' : 'bg-neon' }}" style="width: {{ $progress }}%"></div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 text-theme-xs font-normal uppercase text-muted">
                    <span class="badge text-theme-xs font-medium">{{ $assessmentSet->components->count() }} komponen nilai</span>
                    <span class="badge text-theme-xs font-medium">KKM {{ $assessmentSet->kkm ?? '-' }}</span>
                </div>
                
                <div class="mt-5 flex gap-3">
                    <a href="{{ route('guru.diniyyah-scores.edit', $assessmentSet) }}" class="btn {{ $isReadOnly ? 'btn-outline' : 'btn-primary' }} flex-1 text-theme-sm font-medium">
                        {{ $isReadOnly ? 'Lihat Nilai' : 'Isi Nilai' }}
                    </a>
                </div>
            </article>
        @empty
            <div class="empty-state p-10">
                <svg class="mx-auto h-12 w-12 text-soft mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
                <p class="text-theme-sm font-normal">Belum ada mata pelajaran yang ditugaskan kepada Anda.</p>
            </div>
        @endforelse
    </section>
</x-layouts.portal>
