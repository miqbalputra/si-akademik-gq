<x-layouts.portal title="Pantau Jurnal Kelas" portalLabel="Portal Guru" breadcrumb="Monitoring Jurnal Kelas">
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="badge badge-amber">Pemantauan Akademik</span>
                    <h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">Pemantauan Jurnal Kelas Diniyyah</h1>
                    <p class="mt-2 max-w-2xl text-sm font-medium text-slate-300">Monitor pengisian jurnal oleh Guru Diniyyah berdasarkan jadwal.</p>
                </div>
                <a href="{{ route('guru.dashboard') }}" class="btn btn-outline min-h-11 border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white">Kembali ke Dashboard Guru</a>
            </div>
        </header>

        <section class="card-lg p-5 sm:p-6" aria-labelledby="journal-filter-heading">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.14em] text-amber-700">Filter data</p>
                    <h2 id="journal-filter-heading" class="mt-1 text-lg font-black text-slate-900">Pilih periode dan kelas</h2>
                </div>
                <div class="flex flex-wrap gap-2" aria-label="Export jurnal">
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

        <section class="space-y-6" aria-label="Daftar jurnal kelas">
            @forelse ($monitoringData as $dayData)
                <article class="card-lg overflow-hidden">
                    <header class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl {{ $dayData['is_holiday'] ? 'bg-slate-200 text-slate-500' : 'bg-amber-600 text-white' }}">
                                <span class="text-[10px] font-bold uppercase leading-none">{{ $dayData['date']->translatedFormat('D') }}</span>
                                <span class="mt-0.5 text-lg font-black leading-none">{{ $dayData['date']->format('d') }}</span>
                            </div>
                            <div>
                                <h2 class="font-black text-slate-900">{{ $dayData['date']->translatedFormat('l, d F Y') }}</h2>
                                @if($dayData['is_holiday'])
                                    <p class="text-xs font-bold text-slate-500">{{ $dayData['holiday_name'] ?? 'Hari Libur' }}</p>
                                @else
                                    <p class="text-xs font-semibold text-amber-700">{{ count($dayData['items']) }} jadwal mengajar</p>
                                @endif
                            </div>
                        </div>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="min-w-[860px] w-full text-left" aria-label="Jurnal tanggal {{ $dayData['date']->translatedFormat('d F Y') }}">
                            <thead>
                                <tr class="border-b border-slate-100 bg-white">
                                    <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Jam Sesi</th>
                                    <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Kelas &amp; Mapel</th>
                                    <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Guru</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-black uppercase tracking-wider text-slate-500">Status</th>
                                    <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Jurnal &amp; Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($dayData['items'] as $item)
                                    <tr class="align-top {{ $item['status'] === 'KOSONG' ? 'bg-rose-50/40' : '' }}">
                                        <th scope="row" class="whitespace-nowrap px-4 py-4 text-left">
                                            <span class="badge badge-slate">Jam {{ $item['schedule']->classSession->session_name ?? '?' }}</span>
                                            @if($item['session_time'])
                                                <span class="mt-1 block text-[11px] font-semibold text-slate-500">{{ \Carbon\Carbon::parse($item['session_time']['starts_at'])->format('H:i') }} - {{ \Carbon\Carbon::parse($item['session_time']['ends_at'])->format('H:i') }}</span>
                                            @endif
                                        </th>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-black text-slate-900">{{ $item['schedule']->teacherAssignment->classSubject->classroomTerm->name ?? 'Kelas' }}</p>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $item['schedule']->teacherAssignment->classSubject->subject->name ?? 'Mapel' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-slate-700">{{ $item['schedule']->teacherAssignment->teacher->name ?? '-' }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-center">
                                            @php
                                                $statusClass = match ($item['status']) {
                                                    'TERISI' => 'status-badge-success',
                                                    'TERISI_TIDAK_TERJADWAL' => 'status-badge-neutral',
                                                    'LIBUR' => 'status-badge-neutral',
                                                    default => 'status-badge-danger',
                                                };
                                                $statusLabel = match ($item['status']) {
                                                    'TERISI' => 'Terisi',
                                                    'TERISI_TIDAK_TERJADWAL' => 'Terisi di luar jadwal',
                                                    'LIBUR' => 'Libur',
                                                    default => 'Kosong',
                                                };
                                            @endphp
                                            <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                        </td>
                                        <td class="min-w-[280px] px-4 py-4">
                                            @if(in_array($item['status'], ['TERISI', 'TERISI_TIDAK_TERJADWAL']) && $item['journal'])
                                                <div class="space-y-3">
                                                    <div>
                                                        <span class="field-label">Materi Pembelajaran</span>
                                                        <p class="mt-1 rounded-xl border border-slate-100 bg-slate-50 p-2 text-xs font-semibold text-slate-700">{{ $item['journal']->material }}</p>
                                                    </div>
                                                    <div>
                                                        <span class="field-label">Kehadiran Santri</span>
                                                        @if($item['journal']->absences->isEmpty())
                                                            <span class="status-badge status-badge-success mt-1">Hadir semua</span>
                                                        @else
                                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                                @foreach($item['journal']->absences as $absence)
                                                                    <span class="badge badge-amber">{{ $absence->classEnrollment->student->name }} ({{ $absence->status === 'skipped' ? 'Bolos' : \App\Support\UiLabel::absenceLabel($absence->status) }})</span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-xs font-medium italic text-slate-400">Belum ada data jurnal.</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <p class="text-sm font-bold text-slate-600">Belum ada riwayat jadwal pada periode ini.</p>
                    <p>Ubah filter periode atau kelas untuk melihat data lain.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.portal>
