<x-layouts.portal title="Dashboard Kabag Diniyyah" portalLabel="Portal Kabag Diniyyah" breadcrumb="Koordinasi Diniyyah">
    <section class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8"><div class="relative z-10"><span class="badge badge-amber text-theme-xs font-medium">Koordinasi Diniyyah</span><h1 class="mt-3 text-on-primary ui-page-title">Dashboard Kabag Diniyyah</h1><p class="mt-2 max-w-2xl text-theme-sm font-normal text-on-primary/80">Pantau kelengkapan nilai, jadwal, penugasan, perangkat pembelajaran, dan penerbitan rapor.</p></div></header>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan Diniyyah"><article class="metric-card"><p class="metric-label ui-metric-label">Menunggu validasi</p><p class="metric-value">{{ $summary['submitted_assessments'] }}</p></article><article class="metric-card"><p class="metric-label ui-metric-label">Perlu revisi</p><p class="metric-value">{{ $summary['revision_assessments'] }}</p></article><article class="metric-card"><p class="metric-label ui-metric-label">Penugasan aktif</p><p class="metric-value">{{ $summary['active_assignments'] }}</p></article><article class="metric-card"><p class="metric-label ui-metric-label">RPP tercatat</p><p class="metric-value">{{ $summary['rpps'] }}</p></article></section>

        <section class="card-lg p-5 sm:p-6" aria-labelledby="diniyyah-actions-heading"><div class="mb-4"><p class="text-theme-xs font-normal uppercase text-success-ink">Operasional</p><h2 id="diniyyah-actions-heading" class="mt-1 text-heading ui-card-title">Koordinasi Diniyyah</h2></div><div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @php
                $actions = [
                    ['Monitoring Input Nilai', 'Pantau kelengkapan dan validasi set penilaian guru.', route('diniyyah.monitoring.index')],
                    ['Pengingat Jurnal KBM', 'Siapkan rekap guru yang belum mengisi jurnal untuk dibagikan.', route('admin.journal-reminders.index')],
                    ['Jurnal KBM', 'Audit pelaksanaan pembelajaran dan materi tiap kelas.', \App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource::getUrl()],
                    ['Jadwal Mengajar', 'Kelola sesi dan jadwal pembelajaran Diniyyah.', \App\Filament\Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource::getUrl()],
                    ['Penugasan Guru', 'Atur guru pengampu untuk mapel dan kelas.', \App\Filament\Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource::getUrl()],
                    ['Mapel & Kelas', 'Kelola hubungan mapel Diniyyah dengan kelas periode.', \App\Filament\Resources\DiniyyahClassSubjects\DiniyyahClassSubjectResource::getUrl()],
                    ['Validasi Nilai', 'Kelola set penilaian, komponen, dan hasil Diniyyah.', \App\Filament\Resources\DiniyyahAssessmentSets\DiniyyahAssessmentSetResource::getUrl()],
                    ['Leger & Rapor', 'Pantau leger dan proses penerbitan rapor.', \App\Filament\Resources\DiniyyahLedgerSnapshots\DiniyyahLedgerSnapshotResource::getUrl()],
                    ['Monitoring RPP', 'Pantau RPP dan Promes Diniyyah.', \App\Filament\Resources\Rpps\RppResource::getUrl()],
                ];
            @endphp
            @foreach($actions as [$label, $description, $href])<a href="{{ $href }}" class="group rounded-2xl border border-line bg-surface-subtle p-4 transition hover:-translate-y-0.5 hover:border-success-line hover:bg-success-soft hover:shadow-md"><span class="text-theme-sm font-medium text-heading">{{ $label }}</span><span class="mt-1 block text-theme-xs text-muted font-normal">{{ $description }}</span><span class="mt-3 block text-theme-xs font-normal text-success-ink">Buka →</span></a>@endforeach
        </div></section>
    </section>
</x-layouts.portal>
