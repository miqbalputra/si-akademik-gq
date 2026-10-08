@php
    $emptyQuery = array_merge(request()->query(), ['status' => 'KOSONG']);
    $reconciliationQuery = array_merge(request()->query(), ['status' => 'REKONSILIASI']);
    $allQuery = request()->except('status');
    $hasEmptyFilter = ($filterStatus ?? '') === 'KOSONG';
    $hasReconciliationFilter = ($filterStatus ?? '') === 'REKONSILIASI';
@endphp

<x-layouts.portal title="Pantau Jurnal Kelas" portalLabel="Portal Guru" breadcrumb="Monitoring Jurnal Kelas">
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="badge badge-amber">Pemantauan Akademik</span>
                    <h1 class="mt-3 text-3xl font-semibold leading-tight text-on-primary sm:text-4xl">Pemantauan Jurnal Kelas Diniyyah</h1>
                    <p class="mt-2 max-w-2xl text-sm font-medium text-on-primary/80">Pantau keterisian jurnal berdasarkan jadwal mengajar kelas Anda.</p>
                </div>
                <a href="{{ route('guru.dashboard') }}" class="btn btn-outline min-h-11 border-white/20 bg-surface/10 text-on-primary hover:bg-surface/20 hover:text-on-primary">Kembali ke Dashboard Guru</a>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan jurnal kelas">
            <article class="metric-card"><p class="metric-label">Total slot</p><p class="metric-value">{{ $summary['total_slots'] }}</p><p class="mt-1 text-xs font-semibold text-muted">sesuai filter</p></article>
            <article class="metric-card border-success-line bg-success-soft/60"><p class="metric-label text-success-ink">Sudah terisi</p><p class="metric-value text-success-ink">{{ $summary['filled_slots'] }}</p><p class="mt-1 text-xs font-semibold text-success-ink">jurnal tercatat</p></article>
            <article class="metric-card {{ $summary['empty_slots'] > 0 ? 'border-danger-line bg-danger-soft' : 'border-success-line bg-success-soft/60' }}"><p class="metric-label {{ $summary['empty_slots'] > 0 ? 'text-danger-ink' : 'text-success-ink' }}">Jurnal kosong</p><p class="metric-value {{ $summary['empty_slots'] > 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $summary['empty_slots'] }}</p><p class="mt-1 text-xs font-semibold {{ $summary['empty_slots'] > 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $summary['empty_slots'] > 0 ? 'perlu diingatkan' : 'semua sudah terisi' }}</p></article>
            <article class="metric-card border-line bg-surface-subtle"><p class="metric-label">Hari libur</p><p class="metric-value text-body">{{ $summary['holiday_slots'] }}</p><p class="mt-1 text-xs font-semibold text-muted">slot tidak dihitung kosong</p></article>
            <article class="metric-card border-warning-line bg-warning-soft/70"><p class="metric-label text-warning-ink">Dibebaskan</p><p class="metric-value text-warning-ink">{{ $summary['excused_slots'] ?? 0 }}</p><p class="mt-1 text-xs font-semibold text-warning-ink">izin/sakit guru</p></article>
            <article class="metric-card border-info-line bg-info-soft/70"><p class="metric-label text-info-ink">Agenda tanpa KBM</p><p class="metric-value text-info-ink">{{ $summary['agenda_slots'] ?? 0 }}</p><p class="mt-1 text-xs font-semibold text-info-ink">dibebaskan oleh agenda</p></article>
            <article class="metric-card {{ ($summary['hadir_tanpa_jurnal_slots'] ?? 0) > 0 ? 'border-brand-line bg-brand-soft' : 'border-brand-line bg-brand-soft/50' }}"><p class="metric-label text-brand-ink">Hadir tanpa jurnal</p><p class="metric-value text-brand-ink">{{ $summary['hadir_tanpa_jurnal_slots'] ?? 0 }}</p><p class="mt-1 text-xs font-semibold text-brand-ink">perlu tindak lanjut guru</p></article>
            <article class="metric-card {{ ($summary['presensi_belum_tercatat_slots'] ?? 0) > 0 ? 'border-brand-line bg-brand-soft' : 'border-brand-line bg-brand-soft/50' }}"><p class="metric-label text-brand-ink">Presensi belum tercatat</p><p class="metric-value text-brand-ink">{{ $summary['presensi_belum_tercatat_slots'] ?? 0 }}</p><p class="mt-1 text-xs font-semibold text-brand-ink">jurnal/presensi perlu dicek</p></article>
        </section>

        @if(($summary['unverified_teachers'] ?? 0) > 0)
            <section class="rounded-2xl border border-warning-line bg-warning-soft px-5 py-4 shadow-sm" role="status">
                <p class="text-sm font-semibold text-warning-ink">Sebagian status presensi belum dapat diverifikasi dari GeoPresensi.</p>
                <p class="mt-1 text-xs font-semibold text-warning-ink">Penanda ketidaksesuaian hanya dibuat untuk guru dengan NIY yang terhubung dan respons presensi yang tersedia.</p>
            </section>
        @endif

        @if($summary['empty_slots'] > 0)
            <section class="rounded-2xl border-2 border-danger-line bg-danger-soft px-5 py-4 shadow-sm sm:px-6" role="alert" aria-labelledby="empty-journal-alert-title">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-danger-600 text-lg font-semibold text-white" aria-hidden="true">!</span>
                    <div class="min-w-0 flex-1">
                        <h2 id="empty-journal-alert-title" class="text-base font-semibold text-danger-ink">{{ $summary['empty_slots'] }} jurnal belum diisi</h2>
                        <p class="mt-1 text-sm font-semibold leading-6 text-danger-ink">Perhatikan slot berikut dan ingatkan guru pengajar yang bersangkutan.</p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold text-danger-ink">
                            <span class="rounded-full border border-danger-line bg-surface/80 px-2.5 py-1">Kelas: {{ $summary['empty_classrooms']->implode(', ') ?: '-' }}</span>
                            <span class="rounded-full border border-danger-line bg-surface/80 px-2.5 py-1">Guru: {{ $summary['empty_teachers']->implode(', ') ?: '-' }}</span>
                        </div>
                    </div>
                    @if(!$hasEmptyFilter)
                        <a href="{{ route('wali.diniyyah-journals.index', $emptyQuery) }}" class="hidden shrink-0 rounded-xl bg-danger-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-danger-700 sm:inline-flex">Lihat yang kosong</a>
                    @endif
                </div>
            </section>
        @endif

        <section class="card-lg p-5 sm:p-6" aria-labelledby="journal-filter-heading">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.14em] text-warning-ink">Filter data</p>
                    <h2 id="journal-filter-heading" class="mt-1 text-lg font-semibold text-heading">Pilih periode, kelas, dan status</h2>
                </div>
                <div class="flex flex-wrap gap-2" aria-label="Aksi monitoring jurnal">
                    <a href="{{ route('wali.diniyyah-journals.index', $emptyQuery) }}" class="btn min-h-11 {{ $hasEmptyFilter ? 'border-danger-600 bg-danger-600 text-white hover:bg-danger-700' : 'border-danger-line bg-danger-soft text-danger-ink hover:bg-danger-600 hover:text-white' }}">{{ $hasEmptyFilter ? 'Menampilkan jurnal kosong' : 'Hanya jurnal kosong' }}</a>
                    <a href="{{ route('wali.diniyyah-journals.index', $reconciliationQuery) }}" class="btn min-h-11 {{ $hasReconciliationFilter ? 'border-brand-700 bg-brand-700 text-white hover:bg-brand-800' : 'border-brand-line bg-brand-soft text-brand-ink hover:bg-brand-700 hover:text-white' }}">{{ $hasReconciliationFilter ? 'Menampilkan ketidaksesuaian' : 'Perlu dicek' }}</a>
                    @if($hasEmptyFilter || $hasReconciliationFilter)
                        <a href="{{ route('wali.diniyyah-journals.index', $allQuery) }}" class="btn btn-outline min-h-11">Tampilkan semua</a>
                    @endif
                    <button type="submit" form="journal-filter-form" formaction="{{ route('wali.diniyyah-journals.export-pdf') }}" formtarget="_blank" class="btn min-h-11 border border-danger-line bg-danger-soft text-danger-ink hover:bg-danger-600 hover:text-white">Unduh PDF</button>
                    <button type="submit" form="journal-filter-form" formaction="{{ route('wali.diniyyah-journals.export-excel') }}" class="btn min-h-11 border border-success-line bg-success-soft text-success-ink hover:bg-success-600 hover:text-white">Unduh Excel</button>
                </div>
            </div>

            <form id="journal-filter-form" method="GET" action="{{ route('wali.diniyyah-journals.index') }}" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="journal-month" class="mb-1.5 block text-xs font-bold text-body">Bulan</label>
                        <select id="journal-month" name="month" class="form-input min-h-11">
                            @foreach(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $m)
                                <option value="{{ $index + 1 }}" @selected($month == ($index + 1))>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="journal-year" class="mb-1.5 block text-xs font-bold text-body">Tahun</label>
                        <select id="journal-year" name="year" class="form-input min-h-11">
                            @for($y = date('Y'); $y >= date('Y') - 2; $y--)
                                <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label for="journal-class" class="mb-1.5 block text-xs font-bold text-body">Kelas</label>
                        <select id="journal-class" name="classroom_term_id" class="form-input min-h-11">
                            <option value="">Semua kelas</option>
                            @foreach($classOptions as $id => $name)
                                <option value="{{ $id }}" @selected(($filterClassroomTermId ?? '') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="journal-status" class="mb-1.5 block text-xs font-bold text-body">Status Pengisian</label>
                        <select id="journal-status" name="status" class="form-input min-h-11">
                            <option value="">Semua status</option>
                            <option value="TERISI" @selected(($filterStatus ?? '') === 'TERISI')>Sudah terisi</option>
                            <option value="KOSONG" @selected(($filterStatus ?? '') === 'KOSONG')>Kosong</option>
                            <option value="REKONSILIASI" @selected(($filterStatus ?? '') === 'REKONSILIASI')>Presensi &amp; jurnal perlu dicek</option>
                            <option value="TERISI_TIDAK_TERJADWAL" @selected(($filterStatus ?? '') === 'TERISI_TIDAK_TERJADWAL')>Terisi di luar jadwal</option>
                            <option value="LIBUR" @selected(($filterStatus ?? '') === 'LIBUR')>Hari libur</option>
                            <option value="IZIN" @selected(($filterStatus ?? '') === 'IZIN')>Guru izin</option>
                            <option value="SAKIT" @selected(($filterStatus ?? '') === 'SAKIT')>Guru sakit</option>
                            <option value="AGENDA" @selected(($filterStatus ?? '') === 'AGENDA')>Agenda tanpa KBM</option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-4 border-t border-line pt-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="journal-subject" class="mb-1.5 block text-xs font-bold text-body">Mata Pelajaran</label>
                        <select id="journal-subject" name="subject_id" class="form-input min-h-11">
                            <option value="">Semua mapel</option>
                            @foreach($subjectOptions as $id => $subject)
                                <option value="{{ $id }}" @selected(($filterSubjectId ?? '') == $id)>{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="journal-teacher" class="mb-1.5 block text-xs font-bold text-body">Guru Diniyyah</label>
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
            <div class="flex flex-col gap-2 border-b border-line bg-surface-subtle/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.14em] text-muted">Daftar slot jurnal</p>
                    <h2 id="journal-table-heading" class="mt-1 text-lg font-semibold text-heading">{{ $summary['total_slots'] }} slot ditampilkan</h2>
                </div>
                @if($hasEmptyFilter)
                    <span class="status-badge status-badge-danger">Mode: hanya jurnal kosong</span>
                @elseif($hasReconciliationFilter)
                    <span class="status-badge border border-brand-line bg-brand-soft text-brand-ink">Mode: presensi &amp; jurnal perlu dicek</span>
                @endif
            </div>

            @if($monitoringRows->isEmpty())
                <div class="empty-state m-5 border-success-line bg-success-soft/60">
                    @if($hasEmptyFilter)
                        <p class="text-sm font-semibold text-success-ink">Semua jurnal pada filter ini sudah terisi.</p>
                        <p class="mt-1 text-xs font-semibold text-success-ink">Tidak ada slot kosong yang perlu diingatkan.</p>
                    @else
                        <p class="text-sm font-bold text-body">Belum ada riwayat jadwal pada periode ini.</p>
                        <p class="mt-1 text-xs font-semibold text-muted">Ubah filter periode atau kelas untuk melihat data lain.</p>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-[1050px] w-full text-left" aria-label="Tabel monitoring jurnal kelas">
                        <thead class="bg-surface">
                            <tr class="border-b border-line">
                                <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted">Tanggal</th>
                                <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted">Jam / Sesi</th>
                                <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted">Kelas &amp; Mapel</th>
                                <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted">Guru</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-muted">Status</th>
                                <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted">Jurnal &amp; Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($monitoringRows as $row)
                                @php
                                    $isEmpty = $row['status'] === 'KOSONG';
                                    $isFilled = in_array($row['status'], ['TERISI', 'TERISI_TIDAK_TERJADWAL'], true) && $row['journal'];
                                    $reconciliation = $row['reconciliation'] ?? [];
                                    $isMismatch = (bool) ($reconciliation['actionable'] ?? false);
                                    $isUnverified = ($reconciliation['state'] ?? null) === 'belum_terverifikasi';
                                    $statusClass = match ($row['status']) {
                                        'TERISI' => 'status-badge-success',
                                        'TERISI_TIDAK_TERJADWAL' => 'status-badge-neutral',
                                        'LIBUR' => 'status-badge-neutral',
                                        'IZIN', 'SAKIT' => 'status-badge-neutral',
                                        'AGENDA' => 'border border-info-line bg-info-soft text-info-ink',
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
                                <tr class="align-top transition-colors {{ $isMismatch ? 'border-l-4 border-l-violet-600 bg-brand-soft/70 hover:bg-brand-soft' : ($isEmpty ? 'border-l-4 border-l-rose-600 bg-danger-soft hover:bg-danger-soft-strong' : (($row['status'] === 'AGENDA') ? 'border-l-4 border-l-sky-400 bg-info-soft/60 hover:bg-info-soft' : (($row['status'] === 'IZIN' || $row['status'] === 'SAKIT') ? 'border-l-4 border-l-amber-400 bg-warning-soft/50 hover:bg-warning-soft' : 'hover:bg-success-soft/30'))) }}" @if($isMismatch) aria-label="Presensi dan jurnal perlu dicek" @elseif($isEmpty) aria-label="Jurnal kosong, perlu diingatkan" @endif>
                                    <td class="whitespace-nowrap px-4 py-4 {{ $isEmpty ? 'font-semibold text-danger-ink' : 'font-bold text-body' }}">
                                        {{ $row['date']->translatedFormat('D, d M Y') }}
                                        @if($row['is_holiday'])<span class="mt-1 block text-[11px] font-bold text-muted">{{ $row['holiday_name'] ?? 'Hari Libur' }}</span>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <span class="badge {{ $isEmpty ? 'border-danger-line bg-surface text-danger-ink' : 'badge-slate' }}">Jam {{ $row['session_name'] }}</span>
                                        @if($row['session_time'])
                                            <span class="mt-1 block text-[11px] font-semibold {{ $isEmpty ? 'text-danger-ink' : 'text-muted' }}">{{ \Carbon\Carbon::parse($row['session_time']['starts_at'])->format('H:i') }} - {{ \Carbon\Carbon::parse($row['session_time']['ends_at'])->format('H:i') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold {{ $isEmpty ? 'text-danger-ink' : 'text-heading' }}">{{ $row['classroom_name'] }}</p>
                                        <p class="mt-1 text-xs font-semibold {{ $isEmpty ? 'text-danger-ink' : 'text-muted' }}">{{ $row['subject_name'] }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold {{ $isEmpty ? 'text-danger-ink' : 'text-body' }}">{{ $row['teacher_name'] }}</p>
                                        @if($row['substitute_teacher_name'])
                                            <span class="mt-1 inline-flex rounded-full border border-warning-line bg-warning-soft px-2 py-1 text-[11px] font-semibold text-warning-ink">Diisi pengganti: {{ $row['substitute_teacher_name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-center">
                                        <span class="status-badge {{ $statusClass }}">@if($isEmpty)<span aria-hidden="true">!</span>@endif{{ $statusLabel }}</span>
                                        @if($isEmpty)<span class="mt-2 block text-[11px] font-semibold uppercase tracking-wide text-danger-ink">Belum diisi</span>@endif
                                        @if($isMismatch)<span class="mt-2 inline-flex rounded-full border border-brand-line bg-brand-soft px-2 py-1 text-[10px] font-semibold text-brand-ink">{{ $reconciliation['label'] }}</span>@elseif($isUnverified)<span class="mt-2 inline-flex rounded-full border border-warning-line bg-warning-soft px-2 py-1 text-[10px] font-semibold text-warning-ink">Presensi belum dapat diverifikasi</span>@endif
                                    </td>
                                    <td class="min-w-[280px] px-4 py-4">
                                        @if($isMismatch)
                                            <div class="mb-3 rounded-xl border border-brand-line bg-brand-soft px-3 py-2 text-xs font-bold text-brand-ink">
                                                Presensi: {{ $reconciliation['attendance_label'] ?? '-' }} · Jurnal: {{ $reconciliation['journal_label'] ?? '-' }}
                                            </div>
                                        @endif
                                        @if($isFilled)
                                            <div class="space-y-3">
                                                <div><span class="field-label">Materi pembelajaran</span><p class="mt-1 rounded-xl border border-line bg-surface-subtle p-2 text-xs font-semibold text-body">{{ $row['journal']->material ?: '-' }}</p></div>
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
                                            <div class="rounded-xl border border-danger-line bg-surface/80 px-3 py-3 text-sm font-semibold text-danger-ink"><span aria-hidden="true">⚠</span> Belum ada data jurnal untuk slot ini.</div>
                                        @elseif($row['status'] === 'AGENDA')
                                            <div class="rounded-xl border border-info-line bg-info-soft px-3 py-3 text-sm font-semibold text-info-ink">{{ $row['agenda_reason'] ?? 'Libur Mengajar - Agenda' }}</div>
                                        @elseif(in_array($row['status'], ['IZIN', 'SAKIT'], true))
                                            <div class="rounded-xl border border-warning-line bg-warning-soft px-3 py-3 text-sm font-semibold text-warning-ink">Dibebaskan oleh presensi: {{ strtolower($row['status']) }}.</div>
                                        @else
                                            <span class="text-xs font-medium italic text-soft">Tidak ada data jurnal.</span>
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
