<x-layouts.portal title="Rapor {{ $reportCard->student?->name }}" portalLabel="Portal Wali Santri" breadcrumb="Rapor">
    <div class="animate-fade-in-up">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('wali.dashboard') }}" class="btn btn-outline min-h-11">Kembali ke Dashboard</a>
            <a href="{{ route('report-cards.download-pdf', $reportCard) }}" class="btn btn-primary min-h-11">Unduh PDF</a>
        </div>
        
        <section class="rounded-[2.5rem] print-sheet p-6 sm:p-10 border border-slate-100">
            
            <!-- Header -->
            <header class="border-b border-slate-100 pb-8 text-center">
                <p class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Rapor Hasil Belajar Diniyyah</p>
                <h1 class="mt-2 text-3xl font-black text-slate-900 tracking-tight">GRIYA QUR'AN</h1>
                <span class="mt-4 inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-800 border border-emerald-200">
                    Status: {{ \App\Support\UiLabel::statusLabel($reportCard->status) }}
                </span>
            </header>

            <!-- Student Metadata -->
            <section class="mt-8 grid gap-4 text-sm md:grid-cols-2">
                <dl class="grid grid-cols-[8rem_1fr] gap-y-2 rounded-2xl bg-slate-50 p-5 font-semibold text-slate-700">
                    <dt class="text-slate-400">Nama Santri</dt>
                    <dd class="font-extrabold text-slate-900">{{ $reportCard->student?->name }}</dd>
                    <dt class="text-slate-400">Nomor Induk (NIS)</dt>
                    <dd class="text-slate-900">{{ $reportCard->student?->nis }}</dd>
                    <dt class="text-slate-400">Mustawa (Kelas)</dt>
                    <dd class="text-slate-900">{{ $reportCard->classroomTerm?->name }}</dd>
                </dl>
                <dl class="grid grid-cols-[8rem_1fr] gap-y-2 rounded-2xl bg-slate-50 p-5 font-semibold text-slate-700">
                    <dt class="text-slate-400">Periode Ajaran</dt>
                    <dd class="font-extrabold text-slate-900">{{ $reportCard->academicTerm?->name }}</dd>
                    <dt class="text-slate-400">Tahun Pelajaran</dt>
                    <dd class="text-slate-900">{{ $reportCard->academicTerm?->academicYear?->name }}</dd>
                    <dt class="text-slate-400">Tanggal Terbit</dt>
                    <dd class="text-slate-900">{{ $reportCard->issue_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
                </dl>
            </section>

            <!-- Scores Table -->
            <div class="mt-8 overflow-x-auto rounded-3xl border border-slate-100">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-5 py-4">No</th>
                            <th class="px-5 py-4">Mata Pelajaran</th>
                            <th class="px-5 py-4 text-center">KKM</th>
                            <th class="px-5 py-4 text-center">Nilai</th>
                            <th class="px-5 py-4">Terbilang</th>
                            <th class="px-5 py-4">Predikat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @foreach ($reportCard->lines->sortBy('sort_order') as $line)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-4 font-bold text-slate-400">{{ $loop->iteration }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-extrabold text-slate-900">{{ $line->subject_name }}</div>
                                    @if ($line->tested_material)
                                        <div class="mt-1 text-xs font-semibold text-slate-400">{{ $line->tested_material }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-center font-extrabold">{{ $line->kkm ?? '-' }}</td>
                                <td class="px-5 py-4 text-center font-black text-slate-900 text-base">{{ $line->score_numeric ?? '-' }}</td>
                                <td class="px-5 py-4 text-xs font-semibold text-slate-500 leading-snug">{{ $line->score_words ?? '-' }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider {{ $line->is_passed ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                        {{ $line->score_letter ?? '-' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Total Metrics Card -->
            <section class="mt-8 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-5 text-center transition-transform hover:scale-[1.02]">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Jumlah Nilai</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $reportCard->total_score ?? '-' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-5 text-center transition-transform hover:scale-[1.02]">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Rata-rata Nilai</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $reportCard->average_score ?? '-' }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 text-center transition-transform hover:scale-[1.02]">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Peringkat Kelas</p>
                    <p class="mt-1 text-2xl font-black text-amber-900">#{{ $reportCard->rank_in_class ?? '-' }}</p>
                </div>
            </section>

            <!-- Attendance & Notes Grid -->
            <section class="mt-8 grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-5">
                    <h3 class="font-extrabold text-slate-900 text-sm mb-4">Ketidakhadiran (Absensi)</h3>
                    <dl class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl bg-white p-3 border border-slate-100">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sakit</dt>
                            <dd class="font-black text-red-700 text-lg mt-1">{{ $reportCard->attendance?->sick_count ?? 0 }}</dd>
                        </div>
                        <div class="rounded-xl bg-white p-3 border border-slate-100">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Izin</dt>
                            <dd class="font-black text-sky-700 text-lg mt-1">{{ $reportCard->attendance?->permission_count ?? 0 }}</dd>
                        </div>
                        <div class="rounded-xl bg-white p-3 border border-slate-100">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Alpa</dt>
                            <dd class="font-black text-slate-800 text-lg mt-1">{{ $reportCard->attendance?->absent_count ?? 0 }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="rounded-2xl bg-slate-50 p-5">
                    <h3 class="font-extrabold text-slate-900 text-sm mb-3">Catatan Wali Kelas</h3>
                    <p class="min-h-[70px] text-xs font-semibold text-slate-500 leading-relaxed bg-white p-4 rounded-xl border border-slate-100">{{ $reportCard->homeroom_note ?: 'Tidak ada catatan khusus.' }}</p>
                </div>
            </section>

            <!-- Signatures Section -->
            <section class="mt-12 grid gap-6 text-center text-xs font-bold uppercase tracking-wider text-slate-700 sm:grid-cols-3 pt-8 border-t border-slate-100">
                @forelse ($reportCard->signatures->sortBy('sort_order') as $signature)
                    <div>
                        <p class="text-slate-400 text-[10px] font-bold uppercase">{{ $signature->role_label }}</p>
                        <div class="h-20"></div>
                        <p class="font-black text-slate-900 border-t border-slate-200 w-fit mx-auto pt-1 px-4">{{ $signature->person_name ?? $signature->teacher?->name ?? '-' }}</p>
                    </div>
                @empty
                    <div>
                        <p class="text-slate-400 text-[10px] font-bold uppercase">Wali Kelas</p>
                        <div class="h-20"></div>
                        <p class="font-black text-slate-900 border-t border-slate-200 w-fit mx-auto pt-1 px-4">-</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-[10px] font-bold uppercase">Kepala Bagian Diniyyah</p>
                        <div class="h-20"></div>
                        <p class="font-black text-slate-900 border-t border-slate-200 w-fit mx-auto pt-1 px-4">-</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-[10px] font-bold uppercase">Kepala Sekolah</p>
                        <div class="h-20"></div>
                        <p class="font-black text-slate-900 border-t border-slate-200 w-fit mx-auto pt-1 px-4">-</p>
                    </div>
                @endforelse
            </section>
        </section>
    </div>
</x-layouts.portal>
