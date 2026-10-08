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
            <h1 class="text-2xl font-semibold text-heading">Performa Mengajar Saya</h1>
            <p class="text-sm text-muted">Rekap jurnal mengajar Diniyyah Anda bulan {{ $performa['month_label'] }}.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <form method="GET" action="{{ route('guru.performa') }}" class="flex items-end gap-2">
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-soft mb-1">Bulan</label>
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
            <div class="rounded-xl border border-line bg-surface-subtle p-2">
                <p class="px-1 pb-1 text-[10px] font-semibold uppercase tracking-wider text-soft">Download laporan</p>
                <div class="flex gap-2">
                    <a href="{{ route('guru.performa.export', ['format' => 'xlsx', 'month' => $performa['month'], 'year' => $performa['year']]) }}" class="btn btn-sm border border-success-line bg-surface text-success-ink hover:bg-success-soft" aria-label="Download Excel performa {{ $performa['month_label'] }}">Excel</a>
                    <a href="{{ route('guru.performa.export', ['format' => 'pdf', 'month' => $performa['month'], 'year' => $performa['year']]) }}" class="btn btn-sm border border-danger-line bg-surface text-danger-ink hover:bg-danger-soft" aria-label="Download PDF performa {{ $performa['month_label'] }}">PDF</a>
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
        <div class="rounded-2xl border border-success-800 bg-success-900 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-success-100">Total JP</p>
            <p class="mt-1 text-4xl font-semibold text-white">{{ $stats['jp_berhasil_terlaksana'] ?? 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-success-200">Jurnal yang Anda isi, termasuk pengganti</p>
        </div>
        <div class="rounded-2xl border border-success-line bg-success-soft/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-success-ink">Sudah Diisi</p>
            <p class="mt-1 text-4xl font-semibold text-success-ink">{{ $stats['sudah_diisi'] }}</p>
            <p class="mt-1 text-xs font-semibold text-success-ink">jam diisi jurnal Anda sendiri</p>
        </div>
        <div class="rounded-2xl border border-danger-line bg-danger-soft/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-danger-ink">Kosong</p>
            <p class="mt-1 text-4xl font-semibold text-danger-ink">{{ $stats['kosong'] }}</p>
            <p class="mt-1 text-xs font-semibold text-danger-ink">jam belum diisi (tanggal lewat)</p>
        </div>
        <div class="rounded-2xl border border-brand-line bg-brand-soft/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-brand-ink">Digantikan</p>
            <p class="mt-1 text-4xl font-semibold text-brand-ink">{{ $stats['digantikan'] }}</p>
            <p class="mt-1 text-xs font-semibold text-brand-ink">diisi guru pengganti</p>
        </div>
        <div class="rounded-2xl border border-brand-line bg-brand-soft/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-brand-ink">Menggantikan Guru Lain</p>
            <p class="mt-1 text-4xl font-semibold text-brand-ink">{{ $stats['menggantikan_guru_lain'] ?? 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-brand-ink">sesi yang Anda isi sebagai pengganti</p>
        </div>
        <div class="rounded-2xl border border-warning-line bg-warning-soft/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-warning-ink">Dibebaskan</p>
            <p class="mt-1 text-4xl font-semibold text-warning-ink">{{ $stats['dibebaskan'] ?? 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-warning-ink">izin/sakit dari presensi</p>
        </div>
        <div class="rounded-2xl border border-info-line bg-info-soft/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-info-ink">Agenda tanpa KBM</p>
            <p class="mt-1 text-4xl font-semibold text-info-ink">{{ $stats['agenda'] ?? 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-info-ink">libur mengajar terjadwal</p>
        </div>
    </div>

    <section id="attendance-journal-reconciliation" class="mb-6 rounded-2xl border {{ ($reconciliation['available'] ?? false) ? 'border-brand-line bg-brand-soft/50' : 'border-warning-line bg-warning-soft' }} p-5 shadow-sm sm:p-6" aria-labelledby="attendance-journal-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[.16em] {{ ($reconciliation['available'] ?? false) ? 'text-brand-ink' : 'text-warning-ink' }}">Pemeriksaan otomatis</p>
                <h2 id="attendance-journal-title" class="mt-1 text-lg font-semibold text-heading">Kesesuaian Presensi &amp; Jurnal</h2>
                <p class="mt-1 text-sm text-body">Hari lampau diperiksa langsung; slot hari ini diperiksa 30 menit setelah sesi berakhir.</p>
            </div>
            @if(($reconciliation['available'] ?? false))
                <span class="rounded-full border border-brand-line bg-surface px-3 py-1 text-xs font-semibold text-brand-ink">{{ $reconciliationRows->count() }} perlu dicek</span>
            @endif
        </div>

        @if(! ($reconciliation['available'] ?? false))
            <div class="mt-4 rounded-xl border border-warning-line bg-surface/80 px-4 py-3 text-sm font-bold text-warning-ink">{{ $reconciliation['message'] ?? 'Status presensi belum dapat diverifikasi dari GeoPresensi.' }}</div>
        @elseif($reconciliationRows->isEmpty())
            <div class="mt-4 rounded-xl border border-success-line bg-success-soft px-4 py-3 text-sm font-bold text-success-ink">Tidak ada ketidaksesuaian pada periode ini.</div>
        @else
            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                <div class="rounded-xl border border-brand-line bg-surface px-3 py-3"><p class="text-2xl font-semibold text-brand-ink">{{ $reconciliationStats['hadir_tanpa_jurnal'] ?? 0 }}</p><p class="text-xs font-bold text-brand-ink">Hadir tanpa jurnal</p></div>
                <div class="rounded-xl border border-brand-line bg-surface px-3 py-3"><p class="text-2xl font-semibold text-brand-ink">{{ $reconciliationStats['presensi_belum_tercatat'] ?? 0 }}</p><p class="text-xs font-bold text-brand-ink">Presensi belum tercatat</p></div>
                <div class="rounded-xl border border-danger-line bg-surface px-3 py-3"><p class="text-2xl font-semibold text-danger-ink">{{ $reconciliationStats['presensi_dan_jurnal_belum_tercatat'] ?? 0 }}</p><p class="text-xs font-bold text-danger-ink">Keduanya belum tercatat</p></div>
            </div>
            <div class="mt-4 overflow-x-auto rounded-xl border border-brand-line bg-surface">
                <table class="min-w-[760px] w-full divide-y divide-line text-sm">
                    <thead class="bg-brand-soft text-left text-[11px] font-semibold uppercase tracking-wide text-brand-ink">
                        <tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Sesi / kelas</th><th class="px-4 py-3">Presensi</th><th class="px-4 py-3">Jurnal</th><th class="px-4 py-3">Tindakan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($reconciliationRows as $slot)
                            @php
                                $result = $slot['reconciliation'];
                            @endphp
                            <tr class="hover:bg-brand-soft/50">
                                <td class="whitespace-nowrap px-4 py-3 font-bold text-heading">{{ $slot['date_label'] }}</td>
                                <td class="px-4 py-3"><span class="font-bold text-heading">{{ $slot['session_label'] }}</span><span class="block text-xs text-muted">{{ $slot['classroom_names'] }} · {{ $slot['subject_name'] }}</span></td>
                                <td class="px-4 py-3 text-body">{{ $result['attendance_label'] }}</td>
                                <td class="px-4 py-3"><span class="font-bold text-brand-ink">{{ $result['journal_label'] }}</span><span class="block text-xs text-brand-ink">{{ $result['label'] }}</span></td>
                                <td class="px-4 py-3">
                                    @if(in_array($result['state'], [\App\Services\TeachingAttendanceReconciliationService::HADIR_TANPA_JURNAL, \App\Services\TeachingAttendanceReconciliationService::PRESENSI_DAN_JURNAL_BELUM_TERCATAT], true))
                                        <a href="{{ $slot['fill_url'] }}" class="inline-flex rounded-lg bg-brand-700 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-800">Isi jurnal</a>
                                    @else
                                        <a href="{{ route('guru.attendance-report.index') }}" class="inline-flex rounded-lg border border-brand-line bg-brand-soft px-3 py-2 text-xs font-semibold text-brand-ink hover:bg-brand-soft-strong">Lihat presensi</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <details class="mb-6 overflow-hidden rounded-2xl border border-success-line bg-surface shadow-sm" data-jp-calculator="journal-session-v2">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 bg-success-soft px-5 py-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-600">
            <span>
                <span class="block text-sm font-semibold text-success-ink">Rincian perhitungan Total JP</span>
                <span class="mt-0.5 block text-xs font-semibold text-success-ink">Tafsir serentak digabung berdasarkan tanggal dan jam pelaksanaan.</span>
            </span>
            <span class="shrink-0 rounded-full bg-success-900 px-3 py-1 text-xs font-semibold text-white">{{ $stats['jp_berhasil_terlaksana'] ?? 0 }} JP</span>
        </summary>

        <div class="overflow-x-auto border-t border-success-line">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="bg-surface-subtle">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted">Tanggal</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted">Sesi</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted">Kelas &amp; Mapel</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-muted">Jurnal sumber</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-muted">JP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse(collect($performa['jp_rows'] ?? []) as $jpRow)
                        <tr class="{{ ($jpRow['type'] ?? null) === 'tafsir_simultaneous' ? 'bg-success-soft/40' : '' }}">
                            <td class="whitespace-nowrap px-5 py-3 font-bold text-heading">{{ $jpRow['date_label'] ?? '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="font-bold text-heading">{{ $jpRow['session_label'] ?? '-' }}</span>
                                @if($jpRow['session_time'] ?? null)
                                    <span class="block text-xs text-muted">{{ $jpRow['session_time'] }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-body">
                                <span class="font-bold">{{ $jpRow['mapel'] ?? '-' }}</span>
                                <span class="block text-xs text-muted">{{ $jpRow['kelas'] ?? '-' }}</span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if(($jpRow['type'] ?? null) === 'tafsir_simultaneous')
                                    <span class="inline-flex rounded-full bg-success-soft-strong px-2.5 py-1 text-xs font-semibold text-success-ink">{{ $jpRow['journal_count'] ?? 0 }} jurnal kelas → 1 sesi</span>
                                @else
                                    <span class="font-bold text-body">1 jurnal</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center text-lg font-semibold text-success-ink">{{ $jpRow['jp'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm font-semibold text-muted">Belum ada JP yang berhasil terlaksana pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>

    @php
        $chartRows = [
            ['label' => 'Kosong', 'value' => (int) ($stats['kosong'] ?? 0), 'color' => 'var(--color-danger-500)', 'tone' => 'rose'],
            ['label' => 'Sudah Diisi', 'value' => (int) ($stats['sudah_diisi'] ?? 0), 'color' => 'var(--ui-success-ink)', 'tone' => 'emerald'],
            ['label' => 'Digantikan', 'value' => (int) ($stats['digantikan'] ?? 0), 'color' => 'var(--color-brand-600)', 'tone' => 'indigo'],
            ['label' => 'Dibebaskan', 'value' => (int) ($stats['dibebaskan'] ?? 0), 'color' => 'var(--color-warning-600)', 'tone' => 'amber'],
            ['label' => 'Agenda tanpa KBM', 'value' => (int) ($stats['agenda'] ?? 0), 'color' => 'var(--ui-info-ink)', 'tone' => 'sky'],
        ];
        $chartTotal = array_sum(array_column($chartRows, 'value'));
    @endphp

    <section class="mb-6 rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="performa-chart-heading">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-success-ink">Ringkasan visual</p>
                <h2 id="performa-chart-heading" class="mt-1 text-lg font-semibold text-heading">Status jadwal mengajar Anda</h2>
                <p class="mt-1 text-sm text-muted">Distribusi slot pada periode {{ $performa['month_label'] }}.</p>
            </div>
            <span class="font-mono text-xs font-bold text-muted">Total {{ $chartTotal }} slot</span>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_17rem] lg:items-center">
            <div class="relative h-64 min-h-0 sm:h-72">
                <canvas id="guru-performance-chart" role="img" aria-label="Grafik status pengisian jurnal {{ $performa['month_label'] }}"></canvas>
                <p data-performance-chart-fallback hidden class="absolute inset-0 grid place-items-center rounded-xl bg-surface-subtle p-5 text-center text-sm font-semibold text-muted">Grafik belum dapat ditampilkan. Ringkasan angka tersedia di samping.</p>
            </div>
            <dl class="grid gap-2" aria-label="Rincian status pengisian jurnal">
                @foreach($chartRows as $chartRow)
                    @php
                        $percentage = $chartTotal > 0 ? round(($chartRow['value'] / $chartTotal) * 100) : 0;
                        $toneClasses = match ($chartRow['tone']) {
                            'emerald' => 'border-success-line bg-success-soft text-success-ink',
                            'rose' => 'border-danger-line bg-danger-soft text-danger-ink',
                            'indigo' => 'border-brand-line bg-brand-soft text-brand-ink',
                            'sky' => 'border-info-line bg-info-soft text-info-ink',
                            default => 'border-warning-line bg-warning-soft text-warning-ink',
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5 {{ $toneClasses }}">
                        <dt class="flex items-center gap-2 text-xs font-bold"><span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $chartRow['color'] }}"></span>{{ $chartRow['label'] }}</dt>
                        <dd class="text-right"><span class="text-base font-semibold">{{ $chartRow['value'] }}</span> <span class="text-[10px] font-bold opacity-70">({{ $percentage }}%)</span></dd>
                    </div>
                @endforeach
                @if($chartTotal === 0)
                    <p class="rounded-xl border border-line bg-surface-subtle p-3 text-center text-xs font-semibold text-muted">Belum ada slot jurnal pada periode ini.</p>
                @endif
            </dl>
        </div>
    </section>

    @if($agendaRows->isNotEmpty())
        <section class="mb-6 rounded-2xl border border-info-line bg-info-soft/60 p-5 shadow-sm sm:p-6" aria-labelledby="agenda-rows-heading">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-info-ink">Tidak perlu diisi</p>
                    <h2 id="agenda-rows-heading" class="mt-1 text-lg font-semibold text-info-ink">Agenda tanpa KBM</h2>
                </div>
                <span class="rounded-full border border-info-line bg-surface px-3 py-1 text-xs font-semibold text-info-ink">{{ $agendaRows->count() }} slot</span>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($agendaRows as $row)
                    <div class="flex flex-col gap-1 rounded-xl border border-info-line bg-surface px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-info-ink">{{ $row['date_label'] }} · {{ $row['session_label'] }}</p>
                            <p class="text-xs font-semibold text-body">{{ $row['kelas'] }} · {{ $row['mapel'] }}</p>
                        </div>
                        <p class="text-xs font-semibold text-info-ink">{{ $row['material'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Daftar slot kosong --}}
    <div class="glass-card rounded-2xl p-6 border border-line">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-heading">Slot Jurnal Kosong</h2>
            <span class="text-xs font-bold text-danger-ink bg-danger-soft-strong px-3 py-1 rounded-full">{{ $stats['kosong'] }} slot</span>
        </div>

        @if($stats['total'] === 0)
            <p class="text-sm text-muted italic text-center py-8">Anda belum memiliki jadwal mengajar Diniyyah yang sudah lewat pada bulan {{ $performa['month_label'] }}.</p>
        @elseif($emptySlots === [])
            <p class="text-sm text-muted italic text-center py-8">Semua jurnal sudah terisi bulan {{ $performa['month_label'] }}. Kerja bagus!</p>
        @else
            <div class="space-y-5">
                @foreach($grouped as $date => $slots)
                    <div>
                        <div class="flex items-baseline gap-2 mb-2 pb-1 border-b border-line">
                            <span class="text-sm font-semibold text-heading">{{ $slots[0]['date_label'] }}</span>
                            <span class="text-xs font-medium text-soft">· {{ count($slots) }} slot</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($slots as $slot)
                                @php
                                    $timeLabel = $slot['starts_at']
                                        ? \Carbon\Carbon::parse($slot['starts_at'])->format('H:i').' - '.\Carbon\Carbon::parse($slot['ends_at'])->format('H:i')
                                        : null;
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-xl border border-line bg-surface">
                                    <div class="flex items-center gap-3 sm:w-44 shrink-0">
                                        <div class="flex flex-col items-center justify-center bg-surface-muted rounded-lg px-2 py-1 min-w-[5rem] border border-line {{ $slot['is_tafsir'] ? 'bg-info-soft border-info-line' : '' }}">
                                            <span class="font-bold text-heading text-sm">{{ $slot['session_label'] }}</span>
                                            @if($timeLabel)
                                                <span class="text-[10px] text-muted whitespace-nowrap">{{ $timeLabel }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-sm font-bold text-heading">{{ $slot['subject_name'] }}</span>
                                            @if($slot['is_tafsir'])
                                                <span class="text-[10px] font-bold text-info-ink bg-info-soft-strong px-2 py-0.5 rounded">Tafsir Serentak</span>
                                            @endif
                                        </div>
                                        <p class="text-xs font-medium text-muted mt-0.5">Kelas: {{ $slot['classroom_names'] }}</p>
                                    </div>
                                    <div class="shrink-0">
                                        <a href="{{ $slot['fill_url'] }}" class="inline-flex items-center gap-1 rounded-lg {{ $slot['is_tafsir'] ? 'bg-info-600 hover:bg-info-700' : 'bg-info-600 hover:bg-info-700' }} px-3 py-2 text-xs font-bold text-white transition-colors">
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
                const palette = {
                    rose: 'danger',
                    emerald: 'success',
                    indigo: 'primary',
                    amber: 'warning',
                    sky: 'info',
                };
                const colorFor = (tone) => {
                    const hex = getComputedStyle(document.documentElement).getPropertyValue(`--color-${palette[tone] ?? 'primary'}-500`).trim();
                    return /^#[0-9a-f]{6}$/i.test(hex) ? hex : '#465fff';
                };

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
                                backgroundColor: chartRows.map((row) => colorFor(row.tone)),
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
                                x: { beginAtZero: true, ticks: { precision: 0 } },
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
