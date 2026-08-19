@php
    $emptyQuery = array_merge(request()->query(), ['status' => 'KOSONG']);
    $allQuery = request()->except('status');
    $hasEmptyFilter = ($filterStatus ?? '') === 'KOSONG';
@endphp

<x-layouts.portal title="Pantau Jurnal Kelas" portalLabel="Portal Guru" breadcrumb="Monitoring Jurnal Kelas">
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="badge badge-amber">Pemantauan Akademik</span>
                    <h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">Pemantauan Jurnal Kelas Diniyyah</h1>
                    <p class="mt-2 max-w-2xl text-sm font-medium text-slate-300">Pantau keterisian jurnal berdasarkan jadwal mengajar kelas Anda.</p>
                </div>
                <a href="{{ route('guru.dashboard') }}" class="btn btn-outline min-h-11 border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white">Kembali ke Dashboard Guru</a>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" aria-label="Ringkasan jurnal kelas">
            <article class="metric-card"><p class="metric-label">Total slot</p><p class="metric-value">{{ $summary['total_slots'] }}</p><p class="mt-1 text-xs font-semibold text-slate-500">sesuai filter</p></article>
            <article class="metric-card border-emerald-200 bg-emerald-50/60"><p class="metric-label text-emerald-700">Sudah terisi</p><p class="metric-value text-emerald-800">{{ $summary['filled_slots'] }}</p><p class="mt-1 text-xs font-semibold text-emerald-700">jurnal tercatat</p></article>
            <article class="metric-card {{ $summary['empty_slots'] > 0 ? 'border-rose-300 bg-rose-50' : 'border-emerald-200 bg-emerald-50/60' }}"><p class="metric-label {{ $summary['empty_slots'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Jurnal kosong</p><p class="metric-value {{ $summary['empty_slots'] > 0 ? 'text-rose-800' : 'text-emerald-800' }}">{{ $summary['empty_slots'] }}</p><p class="mt-1 text-xs font-semibold {{ $summary['empty_slots'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $summary['empty_slots'] > 0 ? 'perlu diingatkan' : 'semua sudah terisi' }}</p></article>
            <article class="metric-card border-slate-200 bg-slate-50"><p class="metric-label">Hari libur</p><p class="metric-value text-slate-700">{{ $summary['holiday_slots'] }}</p><p class="mt-1 text-xs font-semibold text-slate-500">slot tidak dihitung kosong</p></article>
            <article class="metric-card border-amber-200 bg-amber-50/70"><p class="metric-label text-amber-700">Dibebaskan</p><p class="metric-value text-amber-800">{{ $summary['excused_slots'] ?? 0 }}</p><p class="mt-1 text-xs font-semibold text-amber-700">izin/sakit guru</p></article>
            <article class="metric-card border-sky-200 bg-sky-50/70"><p class="metric-label text-sky-700">Agenda tanpa KBM</p><p class="metric-value text-sky-800">{{ $summary['agenda_slots'] ?? 0 }}</p><p class="mt-1 text-xs font-semibold text-sky-700">dibebaskan oleh agenda</p></article>
        </section>

        @if($summary['empty_slots'] > 0)
            <section class="rounded-2xl border-2 border-rose-300 bg-rose-50 px-5 py-4 shadow-sm sm:px-6" role="alert" aria-labelledby="empty-journal-alert-title">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-lg font-black text-white" aria-hidden="true">!</span>
                    <div class="min-w-0 flex-1">
                        <h2 id="empty-journal-alert-title" class="text-base font-black text-rose-950">{{ $summary['empty_slots'] }} jurnal belum diisi</h2>
                        <p class="mt-1 text-sm font-semibold leading-6 text-rose-800">Perhatikan slot berikut dan ingatkan guru pengajar yang bersangkutan.</p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold text-rose-900">
                            <span class="rounded-full border border-rose-200 bg-white/80 px-2.5 py-1">Kelas: {{ $summary['empty_classrooms']->implode(', ') ?: '-' }}</span>
                            <span class="rounded-full border border-rose-200 bg-white/80 px-2.5 py-1">Guru: {{ $summary['empty_teachers']->implode(', ') ?: '-' }}</span>
                        </div>
                    </div>
                    @if(!$hasEmptyFilter)
                        <a href="{{ route('wali.diniyyah-journals.index', $emptyQuery) }}" class="hidden shrink-0 rounded-xl bg-rose-600 px-3 py-2 text-xs font-black text-white transition hover:bg-rose-700 sm:inline-flex">Lihat yang kosong</a>
                    @endif
                </div>
            </section>
        @endif

        <section class="card-lg p-5 sm:p-6" aria-labelledby="journal-filter-heading">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.14em] text-amber-700">Filter data</p>
                    <h2 id="journal-filter-heading" class="mt-1 text-lg font-black text-slate-900">Pilih periode, kelas, dan status</h2>
                </div>
                <div class="flex flex-wrap gap-2" aria-label="Aksi monitoring jurnal">
                    <a href="{{ route('wali.diniyyah-journals.index', $emptyQuery) }}" class="btn min-h-11 {{ $hasEmptyFilter ? 'border-rose-600 bg-rose-600 text-white hover:bg-rose-700' : 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white' }}">{{ $hasEmptyFilter ? 'Menampilkan jurnal kosong' : 'Hanya jurnal kosong' }}</a>
                    @if($hasEmptyFilter)
                        <a href="{{ route('wali.diniyyah-journals.index', $allQuery) }}" class="btn btn-outline min-h-11">Tampilkan semua</a>
                    @endif
                    <button type="submit" form="journal-filter-form" formaction="{{ route('wali.diniyyah-journals.export-pdf') }}" formtarget="_blank" class="btn min-h-11 border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white">Unduh PDF</button>
                    <button type="submit" form="journal-filter-form" formaction="{{ route('wali.diniyyah-journals.export-excel') }}" class="btn min-h-11 border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white">Unduh Excel</button>
                </div>
            </div>

            <form id="journal-filter-form" method="GET" action="{{ route('wali.diniyyah-journals.index') }}" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="journal-month" class="mb-1.5 block text-xs font-bold text-slate-600">Bulan</label>
                        <select id="journal-month" name="month" class="form-input min-h-11">
                            @foreach(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $m)
                                <option value="{{ $index + 1 }}" @selected($month == ($index + 1))>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="journal-year" class="mb-1.5 block text-xs font-bold text-slate-600">Tahun</label>
                        <select id="journal-year" name="year" class="form-input min-h-11">
                            @for($y = date('Y'); $y >= date('Y') - 2; $y--)
                                <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label for="journal-class" class="mb-1.5 block text-xs font-bold text-slate-600">Kelas</label>
                        <select id="journal-class" name="classroom_term_id" class="form-input min-h-11">
                            <option value="">Semua kelas</option>
                            @foreach($classOptions as $id => $name)
                                <option value="{{ $id }}" @selected(($filterClassroomTermId ?? '') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="journal-status" class="mb-1.5 block text-xs font-bold text-slate-600">Status Pengisian</label>
                        <select id="journal-status" name="status" class="form-input min-h-11">
                            <option value="">Semua status</option>
                            <option value="TERISI" @selected(($filterStatus ?? '') === 'TERISI')>Sudah terisi</option>
                            <option value="KOSONG" @selected(($filterStatus ?? '') === 'KOSONG')>Kosong</option>
                            <option value="TERISI_TIDAK_TERJADWAL" @selected(($filterStatus ?? '') === 'TERISI_TIDAK_TERJADWAL')>Terisi di luar jadwal</option>
                            <option value="LIBUR" @selected(($filterStatus ?? '') === 'LIBUR')>Hari libur</option>
                            <option value="IZIN" @selected(($filterStatus ?? '') === 'IZIN')>Guru izin</option>
                            <option value="SAKIT" @selected(($filterStatus ?? '') === 'SAKIT')>Guru sakit</option>
                            <option value="AGENDA" @selected(($filterStatus ?? '') === 'AGENDA')>Agenda tanpa KBM</option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="journal-subject" class="mb-1.5 block text-xs font-bold text-slate-600">Mata Pelajaran</label>
                        <select id="journal-subject" name="subject_id" class="form-input min-h-11">
                            <option value="">Semua mapel</option>
                            @foreach($subjectOptions as $id => $subject)
                                <option value="{{ $id }}" @selected(($filterSubjectId ?? '') == $id)>{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="journal-teacher" class="mb-1.5 block text-xs font-bold text-slate-600">Guru Diniyyah</label>
                        <select id="journal-teacher" name="teacher_id" class="form-input min-h-11">
                            <option value="">Semua guru</option>
                            @foreach($teacherOptions as $id => $t)
                                <option value="{{ $id }}" @selected(($filterTeacherId ?? '') == $id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <a href="{{ route('wali.diniyyah-journals.index') }}" class="btn btn-outline min-h-11 flex-1">Reset</a>
                        <button type="submit" class="btn btn-primary min-h-11 flex-1">Tampilkan</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="card-lg overflow-hidden" aria-labelledby="journal-table-heading">
            <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.14em] text-slate-500">Daftar slot jurnal</p>
                    <h2 id="journal-table-heading" class="mt-1 text-lg font-black text-slate-900">{{ $summary['total_slots'] }} slot ditampilkan</h2>
                </div>
                @if($hasEmptyFilter)
                    <span class="status-badge status-badge-danger">Mode: hanya jurnal kosong</span>
                @endif
            </div>

            @if($monitoringRows->isEmpty())
                <div class="empty-state m-5 border-emerald-200 bg-emerald-50/60">
                    @if($hasEmptyFilter)
                        <p class="text-sm font-black text-emerald-800">Semua jurnal pada filter ini sudah terisi.</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-700">Tidak ada slot kosong yang perlu diingatkan.</p>
                    @else
                        <p class="text-sm font-bold text-slate-600">Belum ada riwayat jadwal pada periode ini.</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Ubah filter periode atau kelas untuk melihat data lain.</p>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-[1050px] w-full text-left" aria-label="Tabel monitoring jurnal kelas">
                        <thead class="bg-white">
                            <tr class="border-b border-slate-200">
                                <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Tanggal</th>
                                <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Jam / Sesi</th>
                                <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Kelas &amp; Mapel</th>
                                <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Guru</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-black uppercase tracking-wider text-slate-500">Status</th>
                                <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Jurnal &amp; Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($monitoringRows as $row)
                                @php
                                    $isEmpty = $row['status'] === 'KOSONG';
                                    $isFilled = in_array($row['status'], ['TERISI', 'TERISI_TIDAK_TERJADWAL'], true) && $row['journal'];
                                    $statusClass = match ($row['status']) {
                                        'TERISI' => 'status-badge-success',
                                        'TERISI_TIDAK_TERJADWAL' => 'status-badge-neutral',
                                        'LIBUR' => 'status-badge-neutral',
                                        'IZIN', 'SAKIT' => 'status-badge-neutral',
                                        'AGENDA' => 'border border-sky-200 bg-sky-50 text-sky-800',
                                        default => 'status-badge-danger',
                                    };
                                    $statusLabel = match ($row['status']) {
                                        'TERISI' => 'Terisi',
                                        'TERISI_TIDAK_TERJADWAL' => 'Terisi di luar jadwal',
                                        'LIBUR' => 'Libur',
                                        'IZIN' => 'IZIN · Dibebaskan',
                                        'SAKIT' => 'SAKIT · Dibebaskan',
                                        'AGENDA' => 'AGENDA · Tanpa KBM',
                                        default => 'KOSONG',
                                    };
                                @endphp
                                <tr class="align-top transition-colors {{ $isEmpty ? 'border-l-4 border-l-rose-600 bg-rose-50 hover:bg-rose-100' : (($row['status'] === 'AGENDA') ? 'border-l-4 border-l-sky-400 bg-sky-50/60 hover:bg-sky-50' : (($row['status'] === 'IZIN' || $row['status'] === 'SAKIT') ? 'border-l-4 border-l-amber-400 bg-amber-50/50 hover:bg-amber-50' : 'hover:bg-emerald-50/30')) }}" @if($isEmpty) aria-label="Jurnal kosong, perlu diingatkan" @endif>
                                    <td class="whitespace-nowrap px-4 py-4 {{ $isEmpty ? 'font-black text-rose-950' : 'font-bold text-slate-700' }}">
                                        {{ $row['date']->translatedFormat('D, d M Y') }}
                                        @if($row['is_holiday'])<span class="mt-1 block text-[11px] font-bold text-slate-500">{{ $row['holiday_name'] ?? 'Hari Libur' }}</span>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <span class="badge {{ $isEmpty ? 'border-rose-200 bg-white text-rose-800' : 'badge-slate' }}">Jam {{ $row['session_name'] }}</span>
                                        @if($row['session_time'])
                                            <span class="mt-1 block text-[11px] font-semibold {{ $isEmpty ? 'text-rose-700' : 'text-slate-500' }}">{{ \Carbon\Carbon::parse($row['session_time']['starts_at'])->format('H:i') }} - {{ \Carbon\Carbon::parse($row['session_time']['ends_at'])->format('H:i') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-black {{ $isEmpty ? 'text-rose-950' : 'text-slate-900' }}">{{ $row['classroom_name'] }}</p>
                                        <p class="mt-1 text-xs font-semibold {{ $isEmpty ? 'text-rose-700' : 'text-slate-500' }}">{{ $row['subject_name'] }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold {{ $isEmpty ? 'text-rose-950' : 'text-slate-700' }}">{{ $row['teacher_name'] }}</p>
                                        @if($row['substitute_teacher_name'])
                                            <span class="mt-1 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-black text-amber-800">Diisi pengganti: {{ $row['substitute_teacher_name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-center">
                                        <span class="status-badge {{ $statusClass }}">@if($isEmpty)<span aria-hidden="true">!</span>@endif{{ $statusLabel }}</span>
                                        @if($isEmpty)<span class="mt-2 block text-[11px] font-black uppercase tracking-wide text-rose-700">Belum diisi</span>@endif
                                    </td>
                                    <td class="min-w-[280px] px-4 py-4">
                                        @if($isFilled)
                                            <div class="space-y-3">
                                                <div><span class="field-label">Materi pembelajaran</span><p class="mt-1 rounded-xl border border-slate-100 bg-slate-50 p-2 text-xs font-semibold text-slate-700">{{ $row['journal']->material ?: '-' }}</p></div>
                                                <div>
                                                    <span class="field-label">Kehadiran santri</span>
                                                    @if($row['journal']->absences->isEmpty())
                                                        <span class="status-badge status-badge-success mt-1">Hadir semua</span>
                                                    @else
                                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                                            @foreach($row['journal']->absences as $absence)
                                                                <span class="badge badge-amber">{{ $absence->classEnrollment->student->name }} ({{ $absence->status === 'skipped' ? 'Bolos' : \App\Support\UiLabel::absenceLabel($absence->status) }})</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @elseif($isEmpty)
                                            <div class="rounded-xl border border-rose-200 bg-white/80 px-3 py-3 text-sm font-black text-rose-800"><span aria-hidden="true">⚠</span> Belum ada data jurnal untuk slot ini.</div>
                                        @elseif($row['status'] === 'AGENDA')
                                            <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-3 text-sm font-black text-sky-800">{{ $row['agenda_reason'] ?? 'Libur Mengajar - Agenda' }}</div>
                                        @elseif(in_array($row['status'], ['IZIN', 'SAKIT'], true))
                                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm font-black text-amber-800">Dibebaskan oleh presensi: {{ strtolower($row['status']) }}.</div>
                                        @else
                                            <span class="text-xs font-medium italic text-slate-400">Tidak ada data jurnal.</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts.portal>
