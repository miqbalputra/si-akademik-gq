<x-layouts.portal title="Performa Saya" portalLabel="Portal Guru" breadcrumb="Performa Saya">
    <x-slot name="navLinks">
        <a href="{{ route('guru.dashboard') }}" class="btn btn-outline btn-sm">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Dashboard</span>
        </a>
    </x-slot>

    {{-- Header + pilih bulan --}}
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between glass-card p-4 rounded-2xl">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Performa Mengajar Saya</h1>
            <p class="text-sm text-slate-500">Rekap jurnal mengajar Diniyyah Anda bulan {{ $performa['month_label'] }}.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <form method="GET" action="{{ route('guru.performa') }}" class="flex items-end gap-2">
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Bulan</label>
                    <select name="month" class="form-input py-1.5 text-sm" onchange="var o=this.options[this.selectedIndex]; this.form.year.value=o.dataset.year; this.form.submit()">
                        @foreach($monthOptions as $opt)
                            <option value="{{ $opt['value']['month'] }}" data-year="{{ $opt['value']['year'] }}" @if((int) $performa['month'] === $opt['value']['month'] && (int) $performa['year'] === $opt['value']['year']) selected @endif>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="year" value="{{ $performa['year'] }}">
                <noscript>
                    <button type="submit" class="btn btn-sm">Tampilkan</button>
                </noscript>
            </form>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2">
                <p class="px-1 pb-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Download laporan</p>
                <div class="flex gap-2">
                    <a href="{{ route('guru.performa.export', ['format' => 'xlsx', 'month' => $performa['month'], 'year' => $performa['year']]) }}" class="btn btn-sm border border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50" aria-label="Download Excel performa {{ $performa['month_label'] }}">Excel</a>
                    <a href="{{ route('guru.performa.export', ['format' => 'pdf', 'month' => $performa['month'], 'year' => $performa['year']]) }}" class="btn btn-sm border border-rose-200 bg-white text-rose-700 hover:bg-rose-50" aria-label="Download PDF performa {{ $performa['month_label'] }}">PDF</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $stats = $performa['stats'];
        $emptySlots = $performa['empty_slots'];
        $grouped = collect($emptySlots)->groupBy('date');
        $agendaRows = collect($performa['agenda_rows'] ?? []);
        $reconciliation = $performa['reconciliation'] ?? [];
        $reconciliationRows = collect($reconciliation['rows'] ?? []);
        $reconciliationStats = $reconciliation['stats'] ?? [];
    @endphp

    {{-- Total JP + kartu statistik --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 mb-6">
        <div class="rounded-2xl border border-emerald-800 bg-emerald-900 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-100">Total JP</p>
            <p class="mt-1 text-4xl font-black text-white">{{ $stats['jp_berhasil_terlaksana'] ?? 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-emerald-200">Jurnal yang Anda isi, termasuk pengganti</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Sudah Diisi</p>
            <p class="mt-1 text-4xl font-black text-emerald-700">{{ $stats['sudah_diisi'] }}</p>
            <p class="mt-1 text-xs font-semibold text-emerald-600">jam diisi jurnal Anda sendiri</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-rose-700">Kosong</p>
            <p class="mt-1 text-4xl font-black text-rose-700">{{ $stats['kosong'] }}</p>
            <p class="mt-1 text-xs font-semibold text-rose-600">jam belum diisi (tanggal lewat)</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Digantikan</p>
            <p class="mt-1 text-4xl font-black text-indigo-700">{{ $stats['digantikan'] }}</p>
            <p class="mt-1 text-xs font-semibold text-indigo-600">diisi guru pengganti</p>
        </div>
        <div class="rounded-2xl border border-violet-200 bg-violet-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-violet-700">Menggantikan Guru Lain</p>
            <p class="mt-1 text-4xl font-black text-violet-700">{{ $stats['menggantikan_guru_lain'] ?? 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-violet-600">sesi yang Anda isi sebagai pengganti</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Dibebaskan</p>
            <p class="mt-1 text-4xl font-black text-amber-700">{{ $stats['dibebaskan'] ?? 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-amber-600">izin/sakit dari presensi</p>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Agenda tanpa KBM</p>
            <p class="mt-1 text-4xl font-black text-sky-700">{{ $stats['agenda'] ?? 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-sky-600">libur mengajar terjadwal</p>
        </div>
    </div>

    <section id="attendance-journal-reconciliation" class="mb-6 rounded-2xl border {{ ($reconciliation['available'] ?? false) ? 'border-violet-200 bg-violet-50/50' : 'border-amber-200 bg-amber-50' }} p-5 shadow-sm sm:p-6" aria-labelledby="attendance-journal-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[.16em] {{ ($reconciliation['available'] ?? false) ? 'text-violet-700' : 'text-amber-700' }}">Pemeriksaan otomatis</p>
                <h2 id="attendance-journal-title" class="mt-1 text-lg font-black text-slate-900">Kesesuaian Presensi &amp; Jurnal</h2>
                <p class="mt-1 text-sm text-slate-600">Hari lampau diperiksa langsung; slot hari ini diperiksa 30 menit setelah sesi berakhir.</p>
            </div>
            @if(($reconciliation['available'] ?? false))
                <span class="rounded-full border border-violet-200 bg-white px-3 py-1 text-xs font-black text-violet-800">{{ $reconciliationRows->count() }} perlu dicek</span>
            @endif
        </div>

        @if(! ($reconciliation['available'] ?? false))
            <div class="mt-4 rounded-xl border border-amber-200 bg-white/80 px-4 py-3 text-sm font-bold text-amber-900">{{ $reconciliation['message'] ?? 'Status presensi belum dapat diverifikasi dari GeoPresensi.' }}</div>
        @elseif($reconciliationRows->isEmpty())
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">Tidak ada ketidaksesuaian pada periode ini.</div>
        @else
            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                <div class="rounded-xl border border-violet-200 bg-white px-3 py-3"><p class="text-2xl font-black text-violet-800">{{ $reconciliationStats['hadir_tanpa_jurnal'] ?? 0 }}</p><p class="text-xs font-bold text-violet-700">Hadir tanpa jurnal</p></div>
                <div class="rounded-xl border border-fuchsia-200 bg-white px-3 py-3"><p class="text-2xl font-black text-fuchsia-800">{{ $reconciliationStats['presensi_belum_tercatat'] ?? 0 }}</p><p class="text-xs font-bold text-fuchsia-700">Presensi belum tercatat</p></div>
                <div class="rounded-xl border border-rose-200 bg-white px-3 py-3"><p class="text-2xl font-black text-rose-800">{{ $reconciliationStats['presensi_dan_jurnal_belum_tercatat'] ?? 0 }}</p><p class="text-xs font-bold text-rose-700">Keduanya belum tercatat</p></div>
            </div>
            <div class="mt-4 overflow-x-auto rounded-xl border border-violet-100 bg-white">
                <table class="min-w-[760px] w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-violet-50 text-left text-[11px] font-black uppercase tracking-wide text-violet-800">
                        <tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Sesi / kelas</th><th class="px-4 py-3">Presensi</th><th class="px-4 py-3">Jurnal</th><th class="px-4 py-3">Tindakan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($reconciliationRows as $slot)
                            @php
                                $result = $slot['reconciliation'];
                            @endphp
                            <tr class="hover:bg-violet-50/50">
                                <td class="whitespace-nowrap px-4 py-3 font-bold text-slate-800">{{ $slot['date_label'] }}</td>
                                <td class="px-4 py-3"><span class="font-bold text-slate-800">{{ $slot['session_label'] }}</span><span class="block text-xs text-slate-500">{{ $slot['classroom_names'] }} · {{ $slot['subject_name'] }}</span></td>
                                <td class="px-4 py-3 text-slate-700">{{ $result['attendance_label'] }}</td>
                                <td class="px-4 py-3"><span class="font-bold text-violet-900">{{ $result['journal_label'] }}</span><span class="block text-xs text-violet-700">{{ $result['label'] }}</span></td>
                                <td class="px-4 py-3">
                                    @if(in_array($result['state'], [\App\Services\TeachingAttendanceReconciliationService::HADIR_TANPA_JURNAL, \App\Services\TeachingAttendanceReconciliationService::PRESENSI_DAN_JURNAL_BELUM_TERCATAT], true))
                                        <a href="{{ $slot['fill_url'] }}" class="inline-flex rounded-lg bg-violet-700 px-3 py-2 text-xs font-black text-white hover:bg-violet-800">Isi jurnal</a>
                                    @else
                                        <a href="{{ route('guru.attendance-report.index') }}" class="inline-flex rounded-lg border border-fuchsia-200 bg-fuchsia-50 px-3 py-2 text-xs font-black text-fuchsia-800 hover:bg-fuchsia-100">Lihat presensi</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <details class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm" data-jp-calculator="journal-session-v2">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 bg-emerald-50 px-5 py-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
            <span>
                <span class="block text-sm font-black text-emerald-900">Rincian perhitungan Total JP</span>
                <span class="mt-0.5 block text-xs font-semibold text-emerald-700">Tafsir serentak digabung berdasarkan tanggal dan jam pelaksanaan.</span>
            </span>
            <span class="shrink-0 rounded-full bg-emerald-900 px-3 py-1 text-xs font-black text-white">{{ $stats['jp_berhasil_terlaksana'] ?? 0 }} JP</span>
        </summary>

        <div class="overflow-x-auto border-t border-emerald-100">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-500">Tanggal</th>
                        <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-500">Sesi</th>
                        <th class="px-5 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-500">Kelas &amp; Mapel</th>
                        <th class="px-5 py-3 text-center text-xs font-black uppercase tracking-wider text-slate-500">Jurnal sumber</th>
                        <th class="px-5 py-3 text-center text-xs font-black uppercase tracking-wider text-slate-500">JP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse(collect($performa['jp_rows'] ?? []) as $jpRow)
                        <tr class="{{ ($jpRow['type'] ?? null) === 'tafsir_simultaneous' ? 'bg-emerald-50/40' : '' }}">
                            <td class="whitespace-nowrap px-5 py-3 font-bold text-slate-800">{{ $jpRow['date_label'] ?? '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="font-bold text-slate-800">{{ $jpRow['session_label'] ?? '-' }}</span>
                                @if($jpRow['session_time'] ?? null)
                                    <span class="block text-xs text-slate-500">{{ $jpRow['session_time'] }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-700">
                                <span class="font-bold">{{ $jpRow['mapel'] ?? '-' }}</span>
                                <span class="block text-xs text-slate-500">{{ $jpRow['kelas'] ?? '-' }}</span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if(($jpRow['type'] ?? null) === 'tafsir_simultaneous')
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-800">{{ $jpRow['journal_count'] ?? 0 }} jurnal kelas → 1 sesi</span>
                                @else
                                    <span class="font-bold text-slate-600">1 jurnal</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center text-lg font-black text-emerald-800">{{ $jpRow['jp'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm font-semibold text-slate-500">Belum ada JP yang berhasil terlaksana pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>

    @php
        $chartRows = [
            ['label' => 'Kosong', 'value' => (int) ($stats['kosong'] ?? 0), 'color' => '#e11d48', 'tone' => 'rose'],
            ['label' => 'Sudah Diisi', 'value' => (int) ($stats['sudah_diisi'] ?? 0), 'color' => '#149447', 'tone' => 'emerald'],
            ['label' => 'Digantikan', 'value' => (int) ($stats['digantikan'] ?? 0), 'color' => '#4f46e5', 'tone' => 'indigo'],
            ['label' => 'Dibebaskan', 'value' => (int) ($stats['dibebaskan'] ?? 0), 'color' => '#d97706', 'tone' => 'amber'],
            ['label' => 'Agenda tanpa KBM', 'value' => (int) ($stats['agenda'] ?? 0), 'color' => '#0284c7', 'tone' => 'sky'],
        ];
        $chartTotal = array_sum(array_column($chartRows, 'value'));
    @endphp

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="performa-chart-heading">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[.16em] text-emerald-700">Ringkasan visual</p>
                <h2 id="performa-chart-heading" class="mt-1 text-lg font-black text-slate-900">Status jadwal mengajar Anda</h2>
                <p class="mt-1 text-sm text-slate-500">Distribusi slot pada periode {{ $performa['month_label'] }}.</p>
            </div>
            <span class="font-mono text-xs font-bold text-slate-500">Total {{ $chartTotal }} slot</span>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_17rem] lg:items-center">
            <div class="relative h-64 min-h-0 sm:h-72">
                <canvas id="guru-performance-chart" role="img" aria-label="Grafik status pengisian jurnal {{ $performa['month_label'] }}"></canvas>
                <p data-performance-chart-fallback hidden class="absolute inset-0 grid place-items-center rounded-xl bg-slate-50 p-5 text-center text-sm font-semibold text-slate-500">Grafik belum dapat ditampilkan. Ringkasan angka tersedia di samping.</p>
            </div>
            <dl class="grid gap-2" aria-label="Rincian status pengisian jurnal">
                @foreach($chartRows as $chartRow)
                    @php
                        $percentage = $chartTotal > 0 ? round(($chartRow['value'] / $chartTotal) * 100) : 0;
                        $toneClasses = match ($chartRow['tone']) {
                            'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-800',
                            'rose' => 'border-rose-100 bg-rose-50 text-rose-800',
                            'indigo' => 'border-indigo-100 bg-indigo-50 text-indigo-800',
                            'sky' => 'border-sky-100 bg-sky-50 text-sky-800',
                            default => 'border-amber-100 bg-amber-50 text-amber-800',
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5 {{ $toneClasses }}">
                        <dt class="flex items-center gap-2 text-xs font-bold"><span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $chartRow['color'] }}"></span>{{ $chartRow['label'] }}</dt>
                        <dd class="text-right"><span class="text-base font-black">{{ $chartRow['value'] }}</span> <span class="text-[10px] font-bold opacity-70">({{ $percentage }}%)</span></dd>
                    </div>
                @endforeach
                @if($chartTotal === 0)
                    <p class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-center text-xs font-semibold text-slate-500">Belum ada slot jurnal pada periode ini.</p>
                @endif
            </dl>
        </div>
    </section>

    @if($agendaRows->isNotEmpty())
        <section class="mb-6 rounded-2xl border border-sky-200 bg-sky-50/60 p-5 shadow-sm sm:p-6" aria-labelledby="agenda-rows-heading">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[.16em] text-sky-700">Tidak perlu diisi</p>
                    <h2 id="agenda-rows-heading" class="mt-1 text-lg font-black text-sky-950">Agenda tanpa KBM</h2>
                </div>
                <span class="rounded-full border border-sky-200 bg-white px-3 py-1 text-xs font-black text-sky-800">{{ $agendaRows->count() }} slot</span>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($agendaRows as $row)
                    <div class="flex flex-col gap-1 rounded-xl border border-sky-200 bg-white px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-black text-sky-950">{{ $row['date_label'] }} · {{ $row['session_label'] }}</p>
                            <p class="text-xs font-semibold text-slate-600">{{ $row['kelas'] }} · {{ $row['mapel'] }}</p>
                        </div>
                        <p class="text-xs font-black text-sky-700">{{ $row['material'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Daftar slot kosong --}}
    <div class="glass-card rounded-2xl p-6 border border-slate-200">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-black text-slate-800">Slot Jurnal Kosong</h2>
            <span class="text-xs font-bold text-rose-700 bg-rose-100 px-3 py-1 rounded-full">{{ $stats['kosong'] }} slot</span>
        </div>

        @if($stats['total'] === 0)
            <p class="text-sm text-slate-500 italic text-center py-8">Anda belum memiliki jadwal mengajar Diniyyah yang sudah lewat pada bulan {{ $performa['month_label'] }}.</p>
        @elseif($emptySlots === [])
            <p class="text-sm text-slate-500 italic text-center py-8">Semua jurnal sudah terisi bulan {{ $performa['month_label'] }}. Kerja bagus!</p>
        @else
            <div class="space-y-5">
                @foreach($grouped as $date => $slots)
                    <div>
                        <div class="flex items-baseline gap-2 mb-2 pb-1 border-b border-slate-100">
                            <span class="text-sm font-black text-slate-800">{{ $slots[0]['date_label'] }}</span>
                            <span class="text-xs font-medium text-slate-400">· {{ count($slots) }} slot</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($slots as $slot)
                                @php
                                    $timeLabel = $slot['starts_at']
                                        ? \Carbon\Carbon::parse($slot['starts_at'])->format('H:i').' - '.\Carbon\Carbon::parse($slot['ends_at'])->format('H:i')
                                        : null;
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-xl border border-slate-200 bg-white">
                                    <div class="flex items-center gap-3 sm:w-44 shrink-0">
                                        <div class="flex flex-col items-center justify-center bg-slate-100 rounded-lg px-2 py-1 min-w-[5rem] border border-slate-200 {{ $slot['is_tafsir'] ? 'bg-cyan-50 border-cyan-200' : '' }}">
                                            <span class="font-bold text-slate-800 text-sm">{{ $slot['session_label'] }}</span>
                                            @if($timeLabel)
                                                <span class="text-[10px] text-slate-500 whitespace-nowrap">{{ $timeLabel }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-sm font-bold text-slate-800">{{ $slot['subject_name'] }}</span>
                                            @if($slot['is_tafsir'])
                                                <span class="text-[10px] font-bold text-cyan-700 bg-cyan-100 px-2 py-0.5 rounded">Tafsir Serentak</span>
                                            @endif
                                        </div>
                                        <p class="text-xs font-medium text-slate-500 mt-0.5">Kelas: {{ $slot['classroom_names'] }}</p>
                                    </div>
                                    <div class="shrink-0">
                                        <a href="{{ $slot['fill_url'] }}" class="inline-flex items-center gap-1 rounded-lg {{ $slot['is_tafsir'] ? 'bg-cyan-600 hover:bg-cyan-700' : 'bg-teal-600 hover:bg-teal-700' }} px-3 py-2 text-xs font-bold text-white transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                            </svg>
                                            {{ $slot['is_tafsir'] ? 'Isi Jurnal Tafsir' : 'Isi Jurnal' }}
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@push('scripts')
    <script>
        (() => {
            const initialiseChart = () => {
                const canvas = document.getElementById('guru-performance-chart');
                const fallback = document.querySelector('[data-performance-chart-fallback]');
                const chartRows = @json($chartRows);

                if (!canvas || typeof window.Chart !== 'function') {
                    fallback?.removeAttribute('hidden');
                    return;
                }

                try {
                    new window.Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: chartRows.map((row) => row.label),
                            datasets: [{
                                label: 'Slot',
                                data: chartRows.map((row) => row.value),
                                backgroundColor: chartRows.map((row) => row.color),
                                borderRadius: 8,
                                borderSkipped: false,
                                barThickness: 24,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            animation: { duration: 280 },
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: { label: (context) => ` ${context.parsed.x} slot` } },
                            },
                            scales: {
                                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e5e7eb' } },
                                y: { grid: { display: false } },
                            },
                        },
                    });
                } catch (_) {
                    fallback?.removeAttribute('hidden');
                }
            };

            // Aset Vite dimuat sebagai module (defer). Jalankan setelah event
            // load agar window.Chart dari resources/js/app.js sudah tersedia.
            if (document.readyState === 'complete') {
                initialiseChart();
            } else {
                window.addEventListener('load', initialiseChart, { once: true });
            }
        })();
    </script>
@endpush
</x-layouts.portal>
