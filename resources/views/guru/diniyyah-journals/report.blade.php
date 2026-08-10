<x-layouts.portal title="Laporan Jurnal Saya" portalLabel="Portal Guru" breadcrumb="Laporan Jurnal Saya">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.riwayat') }}" class="btn btn-outline btn-sm">Riwayat Jurnal</a>
    </x-slot>

    @php
        $stats = $report['stats'];
        $filters = $report['filters'];
        $exportQuery = collect($filters)->filter(fn ($value) => filled($value))->all();
        $xlsxUrl = route('guru.diniyyah-journals.report.export', array_merge(['format' => 'xlsx'], $exportQuery));
        $pdfUrl = route('guru.diniyyah-journals.report.export', array_merge(['format' => 'pdf'], $exportQuery));
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-5 rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[.16em] text-amber-600">Laporan pribadi</p>
                <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-900">Download jurnal saya</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Pilih periode atau tipe jurnal, lalu download laporan dalam format XLSX atau PDF.</p>
            </div>
            <div class="flex flex-col gap-2 sm:min-w-52">
                <a href="{{ $xlsxUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white transition-colors hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                    Download XLSX
                    <span aria-hidden="true">↓</span>
                </a>
                <a href="{{ $pdfUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700 transition-colors hover:bg-rose-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">
                    Download PDF
                    <span aria-hidden="true">↓</span>
                </a>
            </div>
        </header>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="filter-heading">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 id="filter-heading" class="text-base font-black text-slate-900">Filter laporan</h2>
                    <p class="mt-1 text-xs text-slate-500">Filter dipakai untuk tabel dan file download.</p>
                </div>
                <a href="{{ route('guru.diniyyah-journals.report') }}" class="text-xs font-black text-slate-500 hover:text-amber-700">Reset</a>
            </div>
            <form method="GET" action="{{ route('guru.diniyyah-journals.report') }}" class="mt-5 grid gap-4 md:grid-cols-4">
                <label class="block md:col-span-2">
                    <span class="text-xs font-bold text-slate-600">Cari kelas, mapel, materi</span>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: Fiqih atau Mustawa 1" class="form-input mt-1 bg-white">
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Periode ajaran</span>
                    <select name="academic_term_id" class="form-input mt-1 bg-white">
                        <option value="">Semua periode</option>
                        @foreach($terms as $term)
                            <option value="{{ $term->id }}" @selected((int) ($filters['academic_term_id'] ?? 0) === $term->id)>{{ $term->academicYear?->name }} - {{ $term->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Dari tanggal</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input mt-1 bg-white">
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Sampai tanggal</span>
                    <input type="date" name="date_until" value="{{ $filters['date_until'] ?? '' }}" class="form-input mt-1 bg-white">
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Tipe jurnal</span>
                    <select name="type" class="form-input mt-1 bg-white">
                        <option value="">Semua tipe</option>
                        <option value="regular" @selected(($filters['type'] ?? '') === 'regular')>Reguler</option>
                        <option value="substitute" @selected(($filters['type'] ?? '') === 'substitute')>Pengganti</option>
                    </select>
                </label>
                <div class="flex items-end md:col-span-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-black text-white transition-colors hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 sm:w-auto">Tampilkan laporan</button>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" aria-label="Statistik laporan">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total jurnal</p><p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total_jurnal'] }}</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Jurnal reguler</p><p class="mt-2 text-3xl font-black text-emerald-800">{{ $stats['jurnal_reguler'] }}</p></div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Jurnal pengganti</p><p class="mt-2 text-3xl font-black text-indigo-800">{{ $stats['jurnal_pengganti'] }}</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Hari tercatat</p><p class="mt-2 text-3xl font-black text-amber-800">{{ $stats['hari_tercatat'] }}</p></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-slate-300">Total JP</p><p class="mt-2 text-3xl font-black text-white">{{ $stats['total_jp'] }}</p></div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white shadow-sm" aria-labelledby="detail-heading">
            <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                <div>
                    <h2 id="detail-heading" class="text-xl font-black text-slate-900">Detail jurnal</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $stats['total_jurnal'] }} jurnal sesuai filter yang dipilih.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $stats['total_hadir'] }} hadir tercatat</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Sesi</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Kelas</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Mapel</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Guru mengajar</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Jenis</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Materi</th>
                            <th class="px-4 py-3 text-center text-xs font-black text-slate-500">JP</th>
                            <th class="px-4 py-3 text-center text-xs font-black text-slate-500">H/S/I/A/B</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['rows'] as $row)
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-4 py-3 font-bold text-slate-800">{{ $row['date_label'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['session_label'] }}@if($row['session_time'])<span class="block text-[11px] text-slate-400">{{ $row['session_time'] }}</span>@endif</td>
                                <td class="px-4 py-3 font-semibold text-slate-700">{{ $row['kelas'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['mapel'] }}</td>
                                <td class="px-4 py-3 font-bold text-slate-800">{{ $row['guru_mengajar'] }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-black {{ $row['type'] === 'substitute' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $row['type_label'] }}</span></td>
                                <td class="max-w-xs whitespace-normal px-4 py-3 text-slate-600">{{ $row['material'] }}</td>
                                <td class="px-4 py-3 text-center font-black text-slate-800">{{ $row['jp'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-xs font-bold text-slate-600">{{ $row['hadir'] }}/{{ $row['sakit'] }}/{{ $row['izin'] }}/{{ $row['alpa'] }}/{{ $row['bolos'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-12 text-center text-sm font-semibold text-slate-500">Belum ada jurnal sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.portal>
