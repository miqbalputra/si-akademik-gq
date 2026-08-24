<x-layouts.portal title="Presensi Saya" portalLabel="Portal Guru" breadcrumb="Presensi Saya">
    @php
        $summary = $report['summary'] ?? [];
        $rows = collect($report['rows'] ?? []);
        $statusStyles = [
            'hadir' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            'hadir_terlambat' => 'bg-amber-50 text-amber-800 ring-amber-200',
            'hadir_izin_terlambat' => 'bg-sky-50 text-sky-800 ring-sky-200',
            'izin' => 'bg-orange-50 text-orange-800 ring-orange-200',
            'sakit' => 'bg-rose-50 text-rose-800 ring-rose-200',
            'alfa' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'libur' => 'bg-indigo-50 text-indigo-800 ring-indigo-200',
            'libur_override' => 'bg-violet-50 text-violet-800 ring-violet-200',
        ];
        $todayMonth = now('Asia/Jakarta')->format('Y-m');
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 font-mono text-[11px] font-black uppercase tracking-[.16em] text-neon">
                        <span class="h-1.5 w-1.5 rounded-full bg-neon"></span>
                        GeoPresensi tersinkron
                    </span>
                    <h1 class="mt-4 text-3xl font-black leading-tight tracking-tight sm:text-4xl">Presensi Saya</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">Pantau rekap kehadiran, rincian harian, dan unduh laporan Anda dari satu tempat.</p>
                    @if($report)
                        <p class="mt-2 text-xs font-bold text-emerald-100">{{ $report['teacher']['nama'] ?? $teacher->name }} · NIY {{ $report['teacher']['id_guru'] ?? $teacher->niy }}</p>
                    @endif
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-slate-100">
                    <p class="font-mono text-[10px] font-black uppercase tracking-[.14em] text-neon">Periode</p>
                    <p class="mt-1 font-bold">{{ $periodLabel }}</p>
                    @if($report)
                        <p class="mt-1 text-xs text-slate-300">Diperbarui {{ $report['synced_at_label'] }}</p>
                    @endif
                </div>
            </div>
        </header>

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="filter-heading">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div>
                        <label id="filter-heading" for="attendance-month" class="mb-1.5 block text-sm font-black text-slate-800">Pilih bulan</label>
                        <input id="attendance-month" name="month" type="month" value="{{ $monthValue }}" max="{{ $todayMonth }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800 outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <button type="submit" class="btn btn-primary">Tampilkan rekap</button>
                </form>
                <a href="{{ route('guru.attendance-report.index', ['month' => $monthValue, 'refresh' => 1]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 16l-4 4m0 0-4-4m4 4V4m8 4a8 8 0 1 0 1.3 8.7" /></svg>
                    Muat ulang dari GeoPresensi
                </a>
            </div>
        </section>

        @if(! $result['ok'])
            <section role="alert" class="rounded-[1.5rem] border {{ in_array($result['code'], ['mapping_missing', 'mapping_not_found'], true) ? 'border-amber-200 bg-amber-50' : 'border-rose-200 bg-rose-50' }} p-6 shadow-sm">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ in_array($result['code'], ['mapping_missing', 'mapping_not_found'], true) ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700' }}" aria-hidden="true">!</span>
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Rekap belum tersedia</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-700">{{ $result['message'] }}</p>
                        <p class="mt-3 text-xs font-semibold text-slate-500">Data presensi tetap dikelola di GeoPresensi dan tidak disimpan sebagai salinan di Edu.</p>
                    </div>
                </div>
            </section>
        @else
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6" aria-label="Ringkasan presensi">
                @foreach([
                    ['Hari kerja', $summary['total_hari'] ?? 0, 'bg-slate-50 text-slate-800 ring-slate-200'],
                    ['Hadir', $summary['hadir'] ?? 0, 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
                    ['Izin', $summary['izin'] ?? 0, 'bg-orange-50 text-orange-800 ring-orange-200'],
                    ['Sakit', $summary['sakit'] ?? 0, 'bg-rose-50 text-rose-800 ring-rose-200'],
                    ['Alfa', $summary['alfa'] ?? 0, 'bg-slate-100 text-slate-700 ring-slate-300'],
                    ['Kehadiran', ($summary['persentase'] ?? 0).'%', 'bg-sky-50 text-sky-800 ring-sky-200'],
                ] as [$label, $value, $classes])
                    <article class="rounded-2xl p-4 ring-1 ring-inset {{ $classes }}">
                        <p class="text-2xl font-black">{{ $value }}</p>
                        <p class="mt-1 text-[11px] font-black uppercase tracking-wide opacity-75">{{ $label }}</p>
                    </article>
                @endforeach
            </section>

            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Unduh laporan</h2>
                        <p class="mt-1 text-sm text-slate-500">Isi laporan sama dengan rekap GeoPresensi untuk periode yang dipilih.</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('guru.attendance-report.export', ['format' => 'pdf', 'month' => $monthValue]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-rose-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">
                            PDF
                        </a>
                        <a href="{{ route('guru.attendance-report.export', ['format' => 'xlsx', 'month' => $monthValue]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                            Excel
                        </a>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm" aria-labelledby="attendance-history-heading">
                <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
                    <h2 id="attendance-history-heading" class="text-lg font-black text-slate-900">Rincian presensi harian</h2>
                    <p class="mt-1 text-sm text-slate-500">Hadir, izin, sakit, Alfa, dan hari libur dihitung oleh GeoPresensi.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Jam masuk</th><th class="px-5 py-3">Jam pulang</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Keterangan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($rows as $row)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-5 py-3 font-bold text-slate-800">{{ $row['tanggal'] ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $row['jam_masuk'] ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $row['jam_pulang'] ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $statusStyles[$row['status']] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $row['status_label'] }}</span></td>
                                    <td class="px-5 py-3 text-slate-600">{{ $row['keterangan'] ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-sm font-semibold text-slate-500">Tidak ada data presensi pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs font-semibold text-slate-500 sm:px-6">Status warna: hadir · izin · sakit · Alfa · libur. Data ditampilkan baca-saja dari GeoPresensi.</div>
            </section>
        @endif
    </div>
</x-layouts.portal>
