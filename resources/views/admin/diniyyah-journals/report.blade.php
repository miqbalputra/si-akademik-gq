<x-layouts.portal title="Laporan Jurnal Diniyyah" portalLabel="Portal Admin" breadcrumb="Laporan Jurnal Diniyyah">
    <x-slot name="navLinks">
        <a href="{{ url('/admin') }}" class="btn btn-outline btn-sm text-theme-sm font-medium">Kembali ke Admin</a>
        <a href="{{ url('/admin/rekap-jurnal-guru') }}" class="btn btn-outline btn-sm text-theme-sm font-medium">Statistik Guru</a>
    </x-slot>

    @php
        $stats = $report['stats'];
        $filters = $report['filters'];
        $options = collect($options);
        $exportQuery = collect($filters)->filter(fn ($value) => filled($value))->all();
        $xlsxUrl = route('admin.diniyyah-journals.export', array_merge(['format' => 'xlsx'], $exportQuery));
        $pdfUrl = route('admin.diniyyah-journals.export', array_merge(['format' => 'pdf'], $exportQuery));
        $maxTeacherJournals = max(1, (int) ($stats['by_teacher']->max('journals') ?? 1));
        $activeFilterCount = collect($filters)->filter(fn ($value) => filled($value))->count();
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-5 rounded-2xl border border-line bg-surface p-6 shadow-sm lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-theme-xs font-normal uppercase text-warning-ink">Monitoring akademik</p>
                <h1 class="mt-1 text-heading ui-page-title">Laporan jurnal Diniyyah</h1>
                <p class="mt-2 max-w-3xl text-theme-sm text-muted">Pantau pengisian jurnal semua guru, telusuri detail berdasarkan nama, kelas, mapel, atau periode, lalu download laporan lengkap.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:min-w-80">
                <a href="{{ $xlsxUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-success-700 px-4 py-3 text-white transition-colors hover:bg-success-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700 text-theme-sm font-medium">Download XLSX <span aria-hidden="true">↓</span></a>
                <a href="{{ $pdfUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-danger-600 px-4 py-3 text-white transition-colors hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 text-theme-sm font-medium">Download PDF <span aria-hidden="true">↓</span></a>
            </div>
        </header>

        <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="management-filter-heading">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="management-filter-heading" class="text-heading ui-form-title">Filter laporan full data</h2>
                    <p class="mt-1 text-theme-xs text-muted font-normal">Filter aktif: <strong>{{ $activeFilterCount }}</strong>. Hasil filter juga dipakai pada XLSX dan PDF.</p>
                </div>
                <a href="{{ route('admin.diniyyah-journals.report') }}" class="text-theme-sm font-medium text-muted hover:text-warning-ink">Reset semua filter</a>
            </div>
            <form method="GET" action="{{ route('admin.diniyyah-journals.report') }}" class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <label class="block lg:col-span-2 ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Cari nama guru, kelas, mapel, atau materi</span>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: Ustadz Ahmad, Fiqih, Mustawa 2" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Periode ajaran</span>
                    <select name="academic_term_id" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                        <option value="">Semua periode</option>
                        @foreach($options['terms'] as $term)
                            <option value="{{ $term->id }}" @selected((int) ($filters['academic_term_id'] ?? 0) === $term->id)>{{ $term->academicYear?->name }} - {{ $term->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Guru mengajar</span>
                    <select name="teacher_id" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                        <option value="">Semua guru</option>
                        @foreach($options['teachers'] as $teacher)
                            <option value="{{ $teacher->id }}" @selected((int) ($filters['teacher_id'] ?? 0) === $teacher->id)>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Kelas</span>
                    <select name="classroom_term_id" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                        <option value="">Semua kelas</option>
                        @foreach($options['classes'] as $classroomTerm)
                            <option value="{{ $classroomTerm->id }}" @selected((int) ($filters['classroom_term_id'] ?? 0) === $classroomTerm->id)>{{ $classroomTerm->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Mapel</span>
                    <select name="subject_id" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                        <option value="">Semua mapel</option>
                        @foreach($options['subjects'] as $subject)
                            <option value="{{ $subject->id }}" @selected((int) ($filters['subject_id'] ?? 0) === $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal text-body">Tipe jurnal</span>
                    <select name="type" class="form-input mt-1 bg-surface text-theme-sm font-normal">
                        <option value="">Semua tipe</option>
                        <option value="regular" @selected(($filters['type'] ?? '') === 'regular')>Reguler</option>
                        <option value="substitute" @selected(($filters['type'] ?? '') === 'substitute')>Pengganti</option>
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
                <div class="flex items-end lg:col-span-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-5 py-3 text-white transition-colors hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 sm:w-auto text-theme-sm font-medium">Tampilkan hasil</button>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6" aria-label="Statistik laporan jurnal">
            <div class="rounded-2xl border border-line bg-surface p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-soft">Total jurnal</p><p class="mt-2 text-heading ui-metric-value">{{ $stats['total_jurnal'] }}</p></div>
            <div class="rounded-2xl border border-info-line bg-info-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-info-ink">Guru aktif di laporan</p><p class="mt-2 text-info-ink ui-metric-value">{{ $stats['total_guru'] }}</p></div>
            <div class="rounded-2xl border border-brand-line bg-brand-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-brand-ink">Kelas</p><p class="mt-2 text-brand-ink ui-metric-value">{{ $stats['total_kelas'] }}</p></div>
            <div class="rounded-2xl border border-warning-line bg-warning-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-warning-ink">Mapel</p><p class="mt-2 text-warning-ink ui-metric-value">{{ $stats['total_mapel'] }}</p></div>
            <div class="rounded-2xl border border-success-line bg-success-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-success-ink">Jurnal reguler</p><p class="mt-2 text-success-ink ui-metric-value">{{ $stats['jurnal_reguler'] }}</p></div>
            <div class="rounded-2xl border border-brand-line bg-brand-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-brand-ink">Jurnal pengganti</p><p class="mt-2 text-brand-ink ui-metric-value">{{ $stats['jurnal_pengganti'] }}</p></div>
            <div class="rounded-2xl border border-info-line bg-info-soft p-4 shadow-sm"><p class="text-theme-xs font-normal uppercase text-info-ink">Agenda tanpa KBM</p><p class="mt-2 text-info-ink ui-metric-value">{{ $stats['agenda'] ?? 0 }}</p></div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6 lg:col-span-1" aria-labelledby="teacher-stat-heading">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-theme-xs font-normal uppercase text-soft">Distribusi pengisian</p>
                        <h2 id="teacher-stat-heading" class="mt-1 text-heading ui-card-title">Jurnal per guru</h2>
                    </div>
                    <span class="text-theme-xs font-normal text-soft">Top 10</span>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse($stats['by_teacher']->take(10) as $teacherStat)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3 text-theme-xs font-normal">
                                <span class="truncate font-bold text-body">{{ $teacherStat['name'] }}</span>
                                <span class="shrink-0 font-semibold text-muted">{{ $teacherStat['journals'] }} jurnal</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-warning-500" style="width: {{ max(6, round(($teacherStat['journals'] / $maxTeacherJournals) * 100)) }}%"></div></div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-theme-sm font-normal text-soft">Belum ada data untuk ditampilkan.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6 lg:col-span-2" aria-labelledby="attendance-stat-heading">
                <div>
                    <p class="text-theme-xs font-normal uppercase text-soft">Ringkasan kehadiran santri</p>
                    <h2 id="attendance-stat-heading" class="mt-1 text-heading ui-card-title">Distribusi status dari jurnal</h2>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-xl bg-success-soft p-4"><p class="text-theme-xs font-normal uppercase text-success-ink">Hadir</p><p class="mt-2 text-success-ink ui-metric-value">{{ $stats['total_hadir'] }}</p></div>
                    <div class="rounded-xl bg-warning-soft p-4"><p class="text-theme-xs font-normal uppercase text-warning-ink">Sakit</p><p class="mt-2 text-warning-ink ui-metric-value">{{ $stats['total_sakit'] }}</p></div>
                    <div class="rounded-xl bg-info-soft p-4"><p class="text-theme-xs font-normal uppercase text-info-ink">Izin</p><p class="mt-2 text-info-ink ui-metric-value">{{ $stats['total_izin'] }}</p></div>
                    <div class="rounded-xl bg-danger-soft p-4"><p class="text-theme-xs font-normal uppercase text-danger-ink">Alpa</p><p class="mt-2 text-danger-ink ui-metric-value">{{ $stats['total_alpa'] }}</p></div>
                    <div class="rounded-xl bg-surface-muted p-4"><p class="text-theme-xs font-normal uppercase text-body">Bolos</p><p class="mt-2 text-heading ui-metric-value">{{ $stats['total_bolos'] }}</p></div>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-line bg-surface-subtle p-4"><p class="text-theme-xs font-normal text-muted">Hari dengan jurnal</p><p class="mt-1 text-xl font-semibold text-heading">{{ $stats['hari_tercatat'] }}</p></div>
                    <div class="rounded-xl border border-line bg-surface-subtle p-4"><p class="text-theme-xs font-normal text-muted">Total JP</p><p class="mt-1 text-xl font-semibold text-heading">{{ $stats['total_jp'] }}</p></div>
                    <div class="rounded-xl border border-line bg-surface-subtle p-4"><p class="text-theme-xs font-normal text-muted">Total kelas terisi</p><p class="mt-1 text-xl font-semibold text-heading">{{ $stats['total_kelas'] }}</p></div>
                </div>
            </section>
        </div>

        <section class="rounded-2xl border border-line bg-surface shadow-sm" aria-labelledby="management-detail-heading">
            <div class="flex flex-col gap-2 border-b border-line p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                <div>
                    <h2 id="management-detail-heading" class="text-heading ui-card-title">Detail full data jurnal</h2>
                    <p class="mt-1 text-theme-sm text-muted">{{ $stats['total_jurnal'] }} baris jurnal siap dibaca atau didownload.</p>
                </div>
                <span class="rounded-full bg-surface-muted px-3 py-1 text-body text-theme-xs font-medium">{{ $stats['total_jp'] }} JP</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1500px] w-full divide-y divide-line text-theme-sm">
                    <thead class="bg-surface-subtle text-theme-xs font-medium">
                        <tr>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Tanggal</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Jam</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Kelas</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Mapel</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Guru asli</th>
                            <th class="px-4 py-3 text-left text-muted text-theme-xs font-medium">Pengganti</th>
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
                                <td class="px-4 py-3 text-body">{{ $row['guru_asli'] }}</td>
                                <td class="px-4 py-3 text-body">{{ $row['pengganti'] ?? '-' }}</td>
                                <td class="px-4 py-3 font-medium text-heading">{{ $row['guru_mengajar'] }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 {{ $row['type'] === 'substitute' ? 'bg-brand-soft-strong text-brand-ink' : ($row['type'] === 'agenda' ? 'bg-info-soft-strong text-info-ink' : 'bg-success-soft-strong text-success-ink') }} text-theme-xs font-medium">{{ $row['type_label'] }}</span></td>
                                <td class="max-w-sm whitespace-normal px-4 py-3 text-body">{{ $row['material'] }}</td>
                                <td class="px-4 py-3 text-center font-medium text-heading">{{ $row['jp'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-body text-theme-sm">{{ $row['hadir'] }}/{{ $row['sakit'] }}/{{ $row['izin'] }}/{{ $row['alpa'] }}/{{ $row['bolos'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-6 py-12 text-center text-theme-sm font-medium text-muted">Tidak ada data jurnal sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.portal>
