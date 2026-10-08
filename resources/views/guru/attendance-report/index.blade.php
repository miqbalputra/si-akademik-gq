<x-layouts.portal title="Presensi Saya" portalLabel="Portal Guru" breadcrumb="Presensi Saya">
    @php
        $summary = $report['summary'] ?? [];
        $rows = collect($report['rows'] ?? []);
        $statusStyles = [
            'hadir' => 'bg-success-soft text-success-ink ring-success-line',
            'hadir_terlambat' => 'bg-warning-soft text-warning-ink ring-warning-line',
            'hadir_izin_terlambat' => 'bg-info-soft text-info-ink ring-info-line',
            'izin' => 'bg-warning-soft text-warning-ink ring-warning-line',
            'sakit' => 'bg-danger-soft text-danger-ink ring-danger-line',
            'alfa' => 'bg-surface-muted text-body ring-line',
            'libur' => 'bg-brand-soft text-brand-ink ring-brand-line',
            'libur_override' => 'bg-brand-soft text-brand-ink ring-brand-line',
        ];
        $todayMonth = now('Asia/Jakarta')->format('Y-m');
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-surface/10 px-3 py-1 font-sans text-on-primary text-theme-xs font-medium">
                        <span class="h-1.5 w-1.5 rounded-full bg-on-primary"></span>
                        GeoPresensi tersinkron
                    </span>
                    <h1 class="mt-4 ui-page-title">Presensi Saya</h1>
                    <p class="mt-3 text-theme-sm text-on-primary/80 sm:text-base">Pantau rekap kehadiran, rincian harian, dan unduh laporan Anda dari satu tempat.</p>
                    @if($report)
                        <p class="mt-2 text-theme-xs font-normal text-success-100">{{ $report['teacher']['nama'] ?? $teacher->name }} · NIY {{ $report['teacher']['id_guru'] ?? $teacher->niy }}</p>
                    @endif
                </div>
                <div class="rounded-2xl border border-white/10 bg-surface/10 px-4 py-3 text-theme-sm text-on-primary/80">
                    <p class="font-sans text-theme-xs font-normal uppercase text-on-primary">Periode</p>
                    <p class="mt-1 font-bold">{{ $periodLabel }}</p>
                    @if($report)
                        <p class="mt-1 text-theme-xs text-on-primary/80 font-normal">Diperbarui {{ $report['synced_at_label'] }}</p>
                    @endif
                </div>
            </div>
        </header>

        <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="filter-heading">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div>
                        <label id="filter-heading" for="attendance-month" class="mb-1.5 block text-heading ui-form-label">Pilih bulan</label>
                        <input id="attendance-month" name="month" type="month" value="{{ $monthValue }}" max="{{ $todayMonth }}" class="rounded-xl border border-line-strong bg-surface px-3 py-2.5 text-heading outline-none transition focus:border-success-600 focus:ring-2 focus:ring-success-line text-theme-sm font-normal">
                    </div>
                    <button type="submit" class="btn btn-primary text-theme-sm font-medium">Tampilkan rekap</button>
                </form>
                <a href="{{ route('guru.attendance-report.index', ['month' => $monthValue, 'refresh' => 1]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-line-strong px-4 py-2.5 text-body transition hover:border-success-line hover:bg-success-soft hover:text-success-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-600 text-theme-sm font-medium">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 16l-4 4m0 0-4-4m4 4V4m8 4a8 8 0 1 0 1.3 8.7" /></svg>
                    Muat ulang dari GeoPresensi
                </a>
            </div>
        </section>

        @if(! $result['ok'])
            <section role="alert" class="rounded-2xl border {{ in_array($result['code'], ['mapping_missing', 'mapping_not_found'], true) ? 'border-warning-line bg-warning-soft' : 'border-danger-line bg-danger-soft' }} p-6 shadow-sm">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ in_array($result['code'], ['mapping_missing', 'mapping_not_found'], true) ? 'bg-warning-soft-strong text-warning-ink' : 'bg-danger-soft-strong text-danger-ink' }}" aria-hidden="true">!</span>
                    <div>
                        <h2 class="text-heading ui-card-title">Rekap belum tersedia</h2>
                        <p class="mt-1 text-theme-sm text-body">{{ $result['message'] }}</p>
                        <p class="mt-3 text-theme-xs font-normal text-muted">Data presensi tetap dikelola di GeoPresensi dan tidak disimpan sebagai salinan di Edu.</p>
                    </div>
                </div>
            </section>
        @else
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6" aria-label="Ringkasan presensi">
                @foreach([
                    ['Hari kerja', $summary['total_hari'] ?? 0, 'bg-surface-subtle text-heading ring-line'],
                    ['Hadir', $summary['hadir'] ?? 0, 'bg-success-soft text-success-ink ring-success-line'],
                    ['Izin', $summary['izin'] ?? 0, 'bg-warning-soft text-warning-ink ring-warning-line'],
                    ['Sakit', $summary['sakit'] ?? 0, 'bg-danger-soft text-danger-ink ring-danger-line'],
                    ['Alfa', $summary['alfa'] ?? 0, 'bg-surface-muted text-body ring-line-strong'],
                    ['Kehadiran', ($summary['persentase'] ?? 0).'%', 'bg-info-soft text-info-ink ring-info-line'],
                ] as [$label, $value, $classes])
                    <article class="rounded-2xl p-4 ring-1 ring-inset {{ $classes }}">
                        <p class="ui-metric-value">{{ $value }}</p>
                        <p class="mt-1 text-theme-xs font-normal uppercase opacity-75">{{ $label }}</p>
                    </article>
                @endforeach
            </section>

            <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-heading ui-card-title">Unduh laporan</h2>
                        <p class="mt-1 text-theme-sm text-muted">Isi laporan sama dengan rekap GeoPresensi untuk periode yang dipilih.</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('guru.attendance-report.export', ['format' => 'pdf', 'month' => $monthValue]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-danger-700 px-4 py-2.5 text-white transition hover:bg-danger-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-700 text-theme-sm font-medium">
                            PDF
                        </a>
                        <a href="{{ route('guru.attendance-report.export', ['format' => 'xlsx', 'month' => $monthValue]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-success-700 px-4 py-2.5 text-white transition hover:bg-success-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700 text-theme-sm font-medium">
                            Excel
                        </a>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-line bg-surface shadow-sm" aria-labelledby="attendance-history-heading">
                <div class="border-b border-line px-5 py-5 sm:px-6">
                    <h2 id="attendance-history-heading" class="text-heading ui-card-title">Rincian presensi harian</h2>
                    <p class="mt-1 text-theme-sm text-muted">Hadir, izin, sakit, Alfa, dan hari libur dihitung oleh GeoPresensi.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full divide-y divide-line text-theme-sm">
                        <thead class="bg-surface-subtle text-left text-muted text-theme-xs font-medium">
                            <tr><th class="px-5 py-3 text-theme-xs font-medium">Tanggal</th><th class="px-5 py-3 text-theme-xs font-medium">Jam masuk</th><th class="px-5 py-3 text-theme-xs font-medium">Jam pulang</th><th class="px-5 py-3 text-theme-xs font-medium">Status</th><th class="px-5 py-3 text-theme-xs font-medium">Keterangan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse($rows as $row)
                                <tr class="hover:bg-surface-subtle/70">
                                    <td class="whitespace-nowrap px-5 py-3 font-medium text-heading">{{ $row['tanggal'] ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-body">{{ $row['jam_masuk'] ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-body">{{ $row['jam_pulang'] ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-1 ring-1 ring-inset {{ $statusStyles[$row['status']] ?? 'bg-surface-muted text-body ring-line' }} text-theme-xs font-medium">{{ $row['status_label'] }}</span></td>
                                    <td class="px-5 py-3 text-body">{{ $row['keterangan'] ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-theme-sm font-medium text-muted">Tidak ada data presensi pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line bg-surface-subtle px-5 py-3 text-theme-xs font-normal text-muted sm:px-6">Status warna: hadir · izin · sakit · Alfa · libur. Data ditampilkan baca-saja dari GeoPresensi.</div>
            </section>
        @endif
    </div>
</x-layouts.portal>
