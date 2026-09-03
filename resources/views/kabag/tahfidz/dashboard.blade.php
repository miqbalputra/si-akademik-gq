<x-layouts.portal title="Dashboard Kabag Tahfidz" portalLabel="Portal Kabag Tahfidz" breadcrumb="Koordinasi Tahfidz">
    <section class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10">
                <span class="badge badge-amber">Koordinasi Tahfidz</span>
                <h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">Dashboard Kabag Tahfidz</h1>
                <p class="mt-2 max-w-2xl text-sm font-medium text-slate-300">{{ $term?->academicYear?->name ? $term->academicYear->name.' · ' : '' }}{{ $term?->name ?? 'Belum ada periode akademik aktif' }}</p>
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
                <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm"><p class="text-xs font-bold text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-black text-slate-900">{{ $summary['predicates'][$value] ?? 0 }}</p></article>
            @endforeach
        </section>

        <section class="card-lg p-5 sm:p-6" aria-labelledby="tahfidz-actions-heading">
            <div class="mb-4"><p class="text-xs font-black uppercase tracking-[.14em] text-emerald-700">Operasional</p><h2 id="tahfidz-actions-heading" class="mt-1 text-lg font-black text-slate-900">Koordinasi Tahfidz</h2></div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @php
                    $actions = [
                        ['Laporan Tasmi\' Semua Kelas', 'Pantau, filter, detail, dan ekspor hasil lintas PJ.', route('admin.tasmi-report.index')],
                        ['Penugasan PJ Tasmi\'', 'Tetapkan dan kelola penguji Tasmi\' pada periode berjalan.', \App\Filament\Resources\TasmiExaminerAssignments\TasmiExaminerAssignmentResource::getUrl()],
                        ['Penempatan Halaqah', 'Tempatkan santri ke halaqah dengan papan koordinasi.', \App\Filament\Pages\HalaqahPlacementBoard::getUrl()],
                        ['Halaqah', 'Kelola halaqah, pembina, pendamping, dan anggota.', \App\Filament\Resources\TahfidzHalaqahs\TahfidzHalaqahResource::getUrl()],
                        ['Pekan Tahfidz', 'Atur periode pekanan untuk pemantauan hafalan.', \App\Filament\Resources\TahfidzWeeks\TahfidzWeekResource::getUrl()],
                        ['Konfigurasi UAS Tahfidz', 'Atur jadwal dan aspek penilaian UAS Tahfidz.', \App\Filament\Resources\TahfidzUasDays\TahfidzUasDayResource::getUrl()],
                    ];
                @endphp
                @foreach($actions as [$label, $description, $href])
                    <a href="{{ $href }}" class="group rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50 hover:shadow-md"><span class="text-sm font-black text-slate-900">{{ $label }}</span><span class="mt-1 block text-xs leading-5 text-slate-500">{{ $description }}</span><span class="mt-3 block text-xs font-black text-emerald-800">Buka →</span></a>
                @endforeach
            </div>
        </section>

        <section class="card-lg overflow-hidden" aria-labelledby="recent-tasmi-heading">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6"><div><p class="text-xs font-black uppercase tracking-[.14em] text-slate-400">Hasil terbaru</p><h2 id="recent-tasmi-heading" class="mt-1 text-lg font-black text-slate-900">Tasmi' terbaru periode aktif</h2></div><a href="{{ route('admin.tasmi-report.index') }}" class="text-xs font-black text-emerald-800 underline decoration-emerald-300 decoration-2 underline-offset-4">Lihat semua</a></div>
            @if($recentRecords->isEmpty())
                <p class="p-8 text-sm font-bold text-slate-500">Belum ada hasil Tasmi' pada periode aktif.</p>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Santri</th><th class="px-5 py-3">Kelas</th><th class="px-5 py-3">Predikat</th><th class="px-5 py-3">PJ</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($recentRecords as $record)<tr><td class="px-5 py-3 font-semibold text-slate-600">{{ $record->exam_date?->format('d M Y') }}</td><td class="px-5 py-3 font-black text-slate-900">{{ $record->student?->name }}</td><td class="px-5 py-3 text-slate-600">{{ $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? '-' }}</td><td class="px-5 py-3 font-semibold text-slate-600">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</td><td class="px-5 py-3 text-slate-600">{{ $record->examinerTeacher?->name ?? '-' }}</td></tr>@endforeach</tbody></table></div>
            @endif
        </section>
    </section>
</x-layouts.portal>
