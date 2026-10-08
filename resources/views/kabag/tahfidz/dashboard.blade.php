<x-layouts.portal title="Dashboard Kabag Tahfidz" portalLabel="Portal Kabag Tahfidz" breadcrumb="Koordinasi Tahfidz">
    <section class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10">
                <span class="badge badge-amber">Koordinasi Tahfidz</span>
                <h1 class="mt-3 text-3xl font-semibold leading-tight text-on-primary sm:text-4xl">Dashboard Kabag Tahfidz</h1>
                <p class="mt-2 max-w-2xl text-sm font-medium text-on-primary/80">{{ $term?->academicYear?->name ? $term->academicYear->name.' · ' : '' }}{{ $term?->name ?? 'Belum ada periode akademik aktif' }}</p>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan Tahfidz">
            <article class="metric-card"><p class="metric-label">Halaqah aktif</p><p class="metric-value">{{ $summary['halaqahs'] }}</p></article>
            <article class="metric-card"><p class="metric-label">Santri halaqah</p><p class="metric-value">{{ $summary['members'] }}</p></article>
            <article class="metric-card"><p class="metric-label">PJ Tasmi' aktif</p><p class="metric-value">{{ $summary['examiners'] }}</p></article>
            <article class="metric-card"><p class="metric-label">Hasil Tasmi'</p><p class="metric-value">{{ $summary['tasmi_records'] }}</p></article>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Distribusi predikat Tasmi'">
            @foreach(\App\Models\TasmiRecord::predicateOptions() as $value => $label)
                <article class="rounded-2xl border border-line bg-surface px-4 py-3 shadow-sm"><p class="text-xs font-bold text-muted">{{ $label }}</p><p class="mt-1 text-2xl font-semibold text-heading">{{ $summary['predicates'][$value] ?? 0 }}</p></article>
            @endforeach
        </section>

        <section class="card-lg p-5 sm:p-6" aria-labelledby="tahfidz-actions-heading">
            <div class="mb-4"><p class="text-xs font-semibold uppercase tracking-[.14em] text-success-ink">Operasional</p><h2 id="tahfidz-actions-heading" class="mt-1 text-lg font-semibold text-heading">Koordinasi Tahfidz</h2></div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @php
                    $actions = [
                        ['Laporan Tasmi\' Semua Kelas', 'Pantau, filter, detail, dan ekspor hasil lintas PJ.', route('admin.tasmi-report.index')],
                        ['Penugasan PJ Tasmi\'', 'Tetapkan dan kelola penguji Tasmi\' pada periode berjalan.', \App\Filament\Resources\TasmiExaminerAssignments\TasmiExaminerAssignmentResource::getUrl()],
                        ['Penempatan Halaqah', 'Tempatkan santri ke halaqah dengan papan koordinasi.', \App\Filament\Pages\HalaqahPlacementBoard::getUrl()],
                        ['Halaqah', 'Kelola halaqah, pembina, pendamping, dan anggota.', \App\Filament\Resources\TahfidzHalaqahs\TahfidzHalaqahResource::getUrl()],
                        ['Pekan Tahfidz', 'Atur periode pekanan untuk pemantauan hafalan.', \App\Filament\Resources\TahfidzWeeks\TahfidzWeekResource::getUrl()],
                        ['Jadwal UAS Tahfidz', 'Atur hari dan jadwal pelaksanaan UAS Tahfidz.', \App\Filament\Resources\TahfidzUasDays\TahfidzUasDayResource::getUrl()],
                        ['Aspek Penilaian UAS', 'Atur aspek dan bobot penilaian UAS Tahfidz.', \App\Filament\Resources\TahfidzUasCategories\TahfidzUasCategoryResource::getUrl()],
                    ];
                @endphp
                @foreach($actions as [$label, $description, $href])
                    <a href="{{ $href }}" class="group rounded-2xl border border-line bg-surface-subtle p-4 transition hover:-translate-y-0.5 hover:border-success-line hover:bg-success-soft hover:shadow-md"><span class="text-sm font-semibold text-heading">{{ $label }}</span><span class="mt-1 block text-xs leading-5 text-muted">{{ $description }}</span><span class="mt-3 block text-xs font-semibold text-success-ink">Buka →</span></a>
                @endforeach
            </div>
        </section>

        <section class="card-lg overflow-hidden" aria-labelledby="recent-tasmi-heading">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6"><div><p class="text-xs font-semibold uppercase tracking-[.14em] text-soft">Hasil terbaru</p><h2 id="recent-tasmi-heading" class="mt-1 text-lg font-semibold text-heading">Tasmi' terbaru periode aktif</h2></div><a href="{{ route('admin.tasmi-report.index') }}" class="text-xs font-semibold text-success-ink underline decoration-success-300 decoration-2 underline-offset-4">Lihat semua</a></div>
            @if($recentRecords->isEmpty())
                <p class="p-8 text-sm font-bold text-muted">Belum ada hasil Tasmi' pada periode aktif.</p>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="bg-surface-subtle text-[11px] font-semibold uppercase tracking-wider text-muted"><tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Santri</th><th class="px-5 py-3">Kelas</th><th class="px-5 py-3">Predikat</th><th class="px-5 py-3">PJ</th></tr></thead><tbody class="divide-y divide-line">@foreach($recentRecords as $record)<tr><td class="px-5 py-3 font-semibold text-body">{{ $record->exam_date?->format('d M Y') }}</td><td class="px-5 py-3 font-semibold text-heading">{{ $record->student?->name }}</td><td class="px-5 py-3 text-body">{{ $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? '-' }}</td><td class="px-5 py-3 font-semibold text-body">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</td><td class="px-5 py-3 text-body">{{ $record->examinerTeacher?->name ?? '-' }}</td></tr>@endforeach</tbody></table></div>
            @endif
        </section>
    </section>
</x-layouts.portal>
