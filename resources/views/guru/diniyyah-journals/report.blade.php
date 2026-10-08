<x-layouts.portal title="Laporan Jurnal Saya" portalLabel="Portal Guru" breadcrumb="Laporan Jurnal Saya">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.riwayat') }}" class="btn btn-outline btn-sm text-theme-sm font-medium">Riwayat Jurnal</a>
    </x-slot>

    @php
        $stats = $report['stats'];
        $filters = $report['filters'];
        $exportQuery = collect($filters)->filter(fn ($value) => filled($value))->all();
        $xlsxUrl = route('guru.diniyyah-journals.report.export', array_merge(['format' => 'xlsx'], $exportQuery));
        $pdfUrl = route('guru.diniyyah-journals.report.export', array_merge(['format' => 'pdf'], $exportQuery));
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-5 rounded-2xl border border-line bg-surface p-6 shadow-sm sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-theme-xs font-normal uppercase text-warning-ink">Laporan pribadi</p>
                <h1 class="mt-1 text-heading ui-page-title">Download jurnal saya</h1>
                <p class="mt-2 max-w-2xl text-theme-sm text-muted">Pilih periode atau tipe jurnal, lalu download laporan dalam format XLSX atau PDF.</p>
            </div>
            <div class="flex flex-col gap-2 sm:min-w-52">
                <a href="{{ $xlsxUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-success-700 px-4 py-3 text-white transition-colors hover:bg-success-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700 text-theme-sm font-medium">
                    Download XLSX
                    <span aria-hidden="true">↓</span>
                </a>
                <a href="{{ $pdfUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-danger-line bg-danger-soft px-4 py-3 text-danger-ink transition-colors hover:bg-danger-soft-strong focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 text-theme-sm font-medium">
                    Download PDF
                    <span aria-hidden="true">↓</span>
                </a>
            </div>
        </header>

        <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="filter-heading">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 id="filter-heading" class="text-heading ui-form-title">Filter laporan</h2>
                    <p class="mt-1 text-theme-xs text-muted font-normal">Filter dipakai untuk tabel dan file download.</p>
                </div>
                <a href="{{ route('guru.diniyyah-journals.report') }}" class="text-theme-sm font-medium text-muted hover:text-warning-ink">Reset</a>
            </div>
            <form method="GET" action="{{ route('guru.diniyyah-journals.report') }}" class="mt-5 grid gap-4 md:grid-cols-4">
                <label class="block md:col-span-2 ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Cari kelas, mapel, materi</span>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: Fiqih atau Mustawa 1" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Periode ajaran</span>
                    <select name="academic_term_id" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                        <option value="">Semua periode</option>
                        @foreach($terms as $term)
                            <option value="{{ $term->id }}" @selected((int) ($filters['academic_term_id'] ?? 0) === $term->id)>{{ $term->academicYear?->name }} - {{ $term->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Dari tanggal</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Sampai tanggal</span>
                    <input type="date" name="date_until" value="{{ $filters['date_until'] ?? '' }}" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Tipe jurnal</span>
                    <select name="type" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                        <option value="">Semua tipe</option>
                        <option value="regular" @selected(($filters['type'] ?? '') === 'regular')>Reguler</option>
                        <option value="substitute" @selected(($filters['type'] ?? '') === 'substitute')>Pengganti</option>
                    </select>
                </label>
                <div class="flex items-end md:col-span-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-white transition-colors hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 sm:w-auto text-theme-sm font-medium">Tampilkan laporan</button>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6" aria-label="Statistik laporan">
            <div class="rounded-2xl border border-line bg-surface p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-soft">Total jurnal</p><p class="mt-2 text-heading ui-metric-value">{{ $stats['total_jurnal'] }}</p></div>
            <div class="rounded-2xl border border-success-line bg-success-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-success-ink">Jurnal reguler</p><p class="mt-2 text-success-ink ui-metric-value">{{ $stats['jurnal_reguler'] }}</p></div>
            <div class="rounded-2xl border border-brand-line bg-brand-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-brand-ink">Jurnal pengganti</p><p class="mt-2 text-brand-ink ui-metric-value">{{ $stats['jurnal_pengganti'] }}</p></div>
            <div class="rounded-2xl border border-info-line bg-info-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-info-ink">Agenda tanpa KBM</p><p class="mt-2 text-info-ink ui-metric-value">{{ $stats['agenda'] ?? 0 }}</p></div>
            <div class="rounded-2xl border border-warning-line bg-warning-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-warning-ink">Hari tercatat</p><p class="mt-2 text-warning-ink ui-metric-value">{{ $stats['hari_tercatat'] }}</p></div>
            <div class="rounded-2xl border border-gray-800 bg-gray-900 p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-soft">Total JP</p><p class="mt-2 text-white ui-metric-value">{{ $stats['total_jp'] }}</p></div>
        </section>

        <section class="rounded-2xl border border-line bg-surface shadow-sm" aria-labelledby="detail-heading">
            <div class="flex flex-col gap-2 border-b border-line p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                <div>
                    <h2 id="detail-heading" class="text-heading ui-card-title">Detail jurnal</h2>
                    <p class="mt-1 text-theme-sm text-muted">{{ $stats['total_jurnal'] }} jurnal sesuai filter yang dipilih.</p>
                </div>
                <span class="rounded-full bg-surface-muted px-3 py-1 text-body text-theme-xs font-medium">{{ $stats['total_hadir'] }} hadir tercatat</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full divide-y divide-line text-theme-sm">
                    <thead class="bg-surface-subtle text-theme-xs font-medium">
                        <tr>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Tanggal</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Sesi</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Kelas</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Mapel</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Guru mengajar</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Jenis</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Materi</th>
                            <th class="px-4 py-3 text-center text-muted text-theme-xs font-medium">JP</th>
                            <th class="px-4 py-3 text-center text-muted text-theme-xs font-medium">H/S/I/A/B</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse($report['rows'] as $row)
                            <tr class="align-top hover:bg-surface-subtle/70">
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-heading">{{ $row['date_label'] }}</td>
                                <td class="px-4 py-3 text-body">{{ $row['session_label'] }}@if($row['session_time'])<span class="block text-theme-xs text-soft font-normal">{{ $row['session_time'] }}</span>@endif</td>
                                <td class="px-4 py-3 font-medium text-body">{{ $row['kelas'] }}</td>
                                <td class="px-4 py-3 text-body">{{ $row['mapel'] }}</td>
                                <td class="px-4 py-3 font-medium text-heading">{{ $row['guru_mengajar'] }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 {{ $row['type'] === 'substitute' ? 'bg-brand-soft-strong text-brand-ink' : ($row['type'] === 'agenda' ? 'bg-info-soft-strong text-info-ink' : 'bg-success-soft-strong text-success-ink') }} text-theme-xs font-medium">{{ $row['type_label'] }}</span></td>
                                <td class="max-w-xs whitespace-normal px-4 py-3 text-body">{{ $row['material'] }}</td>
                                <td class="px-4 py-3 text-center font-medium text-heading">{{ $row['jp'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-body text-theme-sm">{{ $row['hadir'] }}/{{ $row['sakit'] }}/{{ $row['izin'] }}/{{ $row['alpa'] }}/{{ $row['bolos'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-12 text-center text-theme-sm font-medium text-muted">Belum ada jurnal sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.portal>
