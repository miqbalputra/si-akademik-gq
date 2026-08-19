<x-layouts.portal title="Laporan Jurnal Diniyyah" portalLabel="Portal Admin" breadcrumb="Laporan Jurnal Diniyyah">
    <x-slot name="navLinks">
        <a href="{{ url('/admin') }}" class="btn btn-outline btn-sm">Kembali ke Admin</a>
        <a href="{{ url('/admin/rekap-jurnal-guru') }}" class="btn btn-outline btn-sm">Statistik Guru</a>
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
        <header class="flex flex-col gap-5 rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[.16em] text-amber-600">Monitoring akademik</p>
                <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-900">Laporan jurnal Diniyyah</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Pantau pengisian jurnal semua guru, telusuri detail berdasarkan nama, kelas, mapel, atau periode, lalu download laporan lengkap.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:min-w-80">
                <a href="{{ $xlsxUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white transition-colors hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Download XLSX <span aria-hidden="true">↓</span></a>
                <a href="{{ $pdfUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-3 text-sm font-black text-white transition-colors hover:bg-rose-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">Download PDF <span aria-hidden="true">↓</span></a>
            </div>
        </header>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="management-filter-heading">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="management-filter-heading" class="text-base font-black text-slate-900">Filter laporan full data</h2>
                    <p class="mt-1 text-xs text-slate-500">Filter aktif: <strong>{{ $activeFilterCount }}</strong>. Hasil filter juga dipakai pada XLSX dan PDF.</p>
                </div>
                <a href="{{ route('admin.diniyyah-journals.report') }}" class="text-xs font-black text-slate-500 hover:text-amber-700">Reset semua filter</a>
            </div>
            <form method="GET" action="{{ route('admin.diniyyah-journals.report') }}" class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <label class="block lg:col-span-2">
                    <span class="text-xs font-bold text-slate-600">Cari nama guru, kelas, mapel, atau materi</span>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: Ustadz Ahmad, Fiqih, Mustawa 2" class="form-input mt-1 bg-white">
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Periode ajaran</span>
                    <select name="academic_term_id" class="form-input mt-1 bg-white">
                        <option value="">Semua periode</option>
                        @foreach($options['terms'] as $term)
                            <option value="{{ $term->id }}" @selected((int) ($filters['academic_term_id'] ?? 0) === $term->id)>{{ $term->academicYear?->name }} - {{ $term->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Guru mengajar</span>
                    <select name="teacher_id" class="form-input mt-1 bg-white">
                        <option value="">Semua guru</option>
                        @foreach($options['teachers'] as $teacher)
                            <option value="{{ $teacher->id }}" @selected((int) ($filters['teacher_id'] ?? 0) === $teacher->id)>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Kelas</span>
                    <select name="classroom_term_id" class="form-input mt-1 bg-white">
                        <option value="">Semua kelas</option>
                        @foreach($options['classes'] as $classroomTerm)
                            <option value="{{ $classroomTerm->id }}" @selected((int) ($filters['classroom_term_id'] ?? 0) === $classroomTerm->id)>{{ $classroomTerm->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Mapel</span>
                    <select name="subject_id" class="form-input mt-1 bg-white">
                        <option value="">Semua mapel</option>
                        @foreach($options['subjects'] as $subject)
                            <option value="{{ $subject->id }}" @selected((int) ($filters['subject_id'] ?? 0) === $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Tipe jurnal</span>
                    <select name="type" class="form-input mt-1 bg-white">
                        <option value="">Semua tipe</option>
                        <option value="regular" @selected(($filters['type'] ?? '') === 'regular')>Reguler</option>
                        <option value="substitute" @selected(($filters['type'] ?? '') === 'substitute')>Pengganti</option>
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
                <div class="flex items-end lg:col-span-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-black text-white transition-colors hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 sm:w-auto">Tampilkan hasil</button>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6" aria-label="Statistik laporan jurnal">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total jurnal</p><p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total_jurnal'] }}</p></div>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-blue-700">Guru aktif di laporan</p><p class="mt-2 text-3xl font-black text-blue-800">{{ $stats['total_guru'] }}</p></div>
            <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-violet-700">Kelas</p><p class="mt-2 text-3xl font-black text-violet-800">{{ $stats['total_kelas'] }}</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Mapel</p><p class="mt-2 text-3xl font-black text-amber-800">{{ $stats['total_mapel'] }}</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Jurnal reguler</p><p class="mt-2 text-3xl font-black text-emerald-800">{{ $stats['jurnal_reguler'] }}</p></div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Jurnal pengganti</p><p class="mt-2 text-3xl font-black text-indigo-800">{{ $stats['jurnal_pengganti'] }}</p></div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-sky-700">Agenda tanpa KBM</p><p class="mt-2 text-3xl font-black text-sky-800">{{ $stats['agenda'] ?? 0 }}</p></div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-1" aria-labelledby="teacher-stat-heading">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[.16em] text-slate-400">Distribusi pengisian</p>
                        <h2 id="teacher-stat-heading" class="mt-1 text-lg font-black text-slate-900">Jurnal per guru</h2>
                    </div>
                    <span class="text-xs font-bold text-slate-400">Top 10</span>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse($stats['by_teacher']->take(10) as $teacherStat)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3 text-xs">
                                <span class="truncate font-bold text-slate-700">{{ $teacherStat['name'] }}</span>
                                <span class="shrink-0 font-black text-slate-500">{{ $teacherStat['journals'] }} jurnal</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-amber-500" style="width: {{ max(6, round(($teacherStat['journals'] / $maxTeacherJournals) * 100)) }}%"></div></div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm font-semibold text-slate-400">Belum ada data untuk ditampilkan.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-2" aria-labelledby="attendance-stat-heading">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[.16em] text-slate-400">Ringkasan kehadiran santri</p>
                    <h2 id="attendance-stat-heading" class="mt-1 text-lg font-black text-slate-900">Distribusi status dari jurnal</h2>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-xl bg-emerald-50 p-4"><p class="text-[10px] font-black uppercase text-emerald-700">Hadir</p><p class="mt-2 text-2xl font-black text-emerald-800">{{ $stats['total_hadir'] }}</p></div>
                    <div class="rounded-xl bg-amber-50 p-4"><p class="text-[10px] font-black uppercase text-amber-700">Sakit</p><p class="mt-2 text-2xl font-black text-amber-800">{{ $stats['total_sakit'] }}</p></div>
                    <div class="rounded-xl bg-sky-50 p-4"><p class="text-[10px] font-black uppercase text-sky-700">Izin</p><p class="mt-2 text-2xl font-black text-sky-800">{{ $stats['total_izin'] }}</p></div>
                    <div class="rounded-xl bg-rose-50 p-4"><p class="text-[10px] font-black uppercase text-rose-700">Alpa</p><p class="mt-2 text-2xl font-black text-rose-800">{{ $stats['total_alpa'] }}</p></div>
                    <div class="rounded-xl bg-slate-100 p-4"><p class="text-[10px] font-black uppercase text-slate-600">Bolos</p><p class="mt-2 text-2xl font-black text-slate-800">{{ $stats['total_bolos'] }}</p></div>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">Hari dengan jurnal</p><p class="mt-1 text-xl font-black text-slate-900">{{ $stats['hari_tercatat'] }}</p></div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">Total JP</p><p class="mt-1 text-xl font-black text-slate-900">{{ $stats['total_jp'] }}</p></div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">Total kelas terisi</p><p class="mt-1 text-xl font-black text-slate-900">{{ $stats['total_kelas'] }}</p></div>
                </div>
            </section>
        </div>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white shadow-sm" aria-labelledby="management-detail-heading">
            <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                <div>
                    <h2 id="management-detail-heading" class="text-xl font-black text-slate-900">Detail full data jurnal</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $stats['total_jurnal'] }} baris jurnal siap dibaca atau didownload.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $stats['total_jp'] }} JP</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1500px] w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Jam</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Kelas</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Mapel</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Guru asli</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500">Pengganti</th>
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
                                <td class="px-4 py-3 text-slate-700">{{ $row['guru_asli'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['pengganti'] ?? '-' }}</td>
                                <td class="px-4 py-3 font-bold text-slate-800">{{ $row['guru_mengajar'] }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-black {{ $row['type'] === 'substitute' ? 'bg-indigo-100 text-indigo-700' : ($row['type'] === 'agenda' ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700') }}">{{ $row['type_label'] }}</span></td>
                                <td class="max-w-sm whitespace-normal px-4 py-3 text-slate-600">{{ $row['material'] }}</td>
                                <td class="px-4 py-3 text-center font-black text-slate-800">{{ $row['jp'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-xs font-bold text-slate-600">{{ $row['hadir'] }}/{{ $row['sakit'] }}/{{ $row['izin'] }}/{{ $row['alpa'] }}/{{ $row['bolos'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-6 py-12 text-center text-sm font-semibold text-slate-500">Tidak ada data jurnal sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.portal>
