<x-layouts.portal title="Rapor {{ $reportCard->student?->name }}" portalLabel="Portal Wali Santri" breadcrumb="Rapor">
    <div class="animate-fade-in-up">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('wali.dashboard') }}" class="btn btn-outline min-h-11 text-theme-sm font-medium">Kembali ke Dashboard</a>
            <a href="{{ route('report-cards.download-pdf', $reportCard) }}" class="btn btn-primary min-h-11 text-theme-sm font-medium">Unduh PDF</a>
        </div>
        
        <section class="rounded-2xl print-sheet p-6 sm:p-10 border border-line">
            
            <!-- Header -->
            <header class="border-b border-line pb-8 text-center">
                <p class="text-theme-xs font-normal uppercase text-warning-ink">Rapor Hasil Belajar Diniyyah</p>
                <h1 class="mt-2 text-heading ui-page-title">GRIYA QUR'AN</h1>
                <span class="mt-4 inline-flex items-center rounded-full bg-success-soft px-3 py-1 text-success-ink border border-success-line text-theme-xs font-medium">
                    Status: {{ \App\Support\UiLabel::statusLabel($reportCard->status) }}
                </span>
            </header>

            <!-- Student Metadata -->
            <section class="mt-8 grid gap-4 text-theme-sm md:grid-cols-2">
                <dl class="grid grid-cols-[8rem_1fr] gap-y-2 rounded-2xl bg-surface-subtle p-5 font-semibold text-body">
                    <dt class="text-soft">Nama Santri</dt>
                    <dd class="font-bold text-heading">{{ $reportCard->student?->name }}</dd>
                    <dt class="text-soft">Nomor Induk (NIS)</dt>
                    <dd class="text-heading">{{ $reportCard->student?->nis }}</dd>
                    <dt class="text-soft">Mustawa (Kelas)</dt>
                    <dd class="text-heading">{{ $reportCard->classroomTerm?->name }}</dd>
                </dl>
                <dl class="grid grid-cols-[8rem_1fr] gap-y-2 rounded-2xl bg-surface-subtle p-5 font-semibold text-body">
                    <dt class="text-soft">Periode Ajaran</dt>
                    <dd class="font-bold text-heading">{{ $reportCard->academicTerm?->name }}</dd>
                    <dt class="text-soft">Tahun Pelajaran</dt>
                    <dd class="text-heading">{{ $reportCard->academicTerm?->academicYear?->name }}</dd>
                    <dt class="text-soft">Tanggal Terbit</dt>
                    <dd class="text-heading">{{ $reportCard->issue_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
                </dl>
            </section>

            <!-- Scores Table -->
            <div class="mt-8 overflow-x-auto rounded-2xl border border-line">
                <table class="w-full text-left text-theme-sm whitespace-nowrap">
                    <thead>
                        <tr class="bg-surface-subtle border-b border-line text-theme-xs font-normal uppercase text-muted">
                            <th class="px-5 py-4 text-theme-xs font-medium">No</th>
                            <th class="px-5 py-4 text-theme-xs font-medium">Mata Pelajaran</th>
                            <th class="px-5 py-4 text-center text-theme-xs font-medium">KKM</th>
                            <th class="px-5 py-4 text-center text-theme-xs font-medium">Nilai</th>
                            <th class="px-5 py-4 text-theme-xs font-medium">Terbilang</th>
                            <th class="px-5 py-4 text-theme-xs font-medium">Predikat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line font-medium text-body">
                        @foreach ($reportCard->lines->sortBy('sort_order') as $line)
                            <tr class="hover:bg-surface-subtle/50 transition-colors">
                                <td class="px-5 py-4 font-medium text-soft">{{ $loop->iteration }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-heading">{{ $line->subject_name }}</div>
                                    @if ($line->tested_material)
                                        <div class="mt-1 text-theme-xs font-normal text-soft">{{ $line->tested_material }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-center font-medium">{{ $line->kkm ?? '-' }}</td>
                                <td class="px-5 py-4 text-center font-medium text-heading text-theme-sm">{{ $line->score_numeric ?? '-' }}</td>
                                <td class="px-5 py-4 text-muted text-theme-sm">{{ $line->score_words ?? '-' }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-3 py-1 {{ $line->is_passed ? 'bg-success-soft text-success-ink border border-success-line' : 'bg-warning-soft text-warning-ink border border-warning-line' }} text-theme-xs font-medium">
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
                <div class="rounded-2xl bg-surface-subtle p-5 text-center transition-transform hover:scale-[1.02]">
                    <p class="text-theme-xs font-normal uppercase text-muted">Jumlah Nilai</p>
                    <p class="mt-1 text-heading ui-metric-value">{{ $reportCard->total_score ?? '-' }}</p>
                </div>
                <div class="rounded-2xl bg-surface-subtle p-5 text-center transition-transform hover:scale-[1.02]">
                    <p class="text-theme-xs font-normal uppercase text-muted">Rata-rata Nilai</p>
                    <p class="mt-1 text-heading ui-metric-value">{{ $reportCard->average_score ?? '-' }}</p>
                </div>
                <div class="rounded-2xl border border-warning-line bg-warning-soft/50 p-5 text-center transition-transform hover:scale-[1.02]">
                    <p class="text-theme-xs font-normal uppercase text-warning-ink">Peringkat Kelas</p>
                    <p class="mt-1 text-warning-ink ui-metric-value">#{{ $reportCard->rank_in_class ?? '-' }}</p>
                </div>
            </section>

            <!-- Attendance & Notes Grid -->
            <section class="mt-8 grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl bg-surface-subtle p-5">
                    <h3 class="text-heading mb-4 text-theme-sm font-medium">Ketidakhadiran (Absensi)</h3>
                    <dl class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl bg-surface p-3 border border-line">
                            <dt class="text-theme-xs font-normal uppercase text-soft">Sakit</dt>
                            <dd class="font-semibold text-danger-ink text-lg mt-1">{{ $reportCard->attendance?->sick_count ?? 0 }}</dd>
                        </div>
                        <div class="rounded-xl bg-surface p-3 border border-line">
                            <dt class="text-theme-xs font-normal uppercase text-soft">Izin</dt>
                            <dd class="font-semibold text-info-ink text-lg mt-1">{{ $reportCard->attendance?->permission_count ?? 0 }}</dd>
                        </div>
                        <div class="rounded-xl bg-surface p-3 border border-line">
                            <dt class="text-theme-xs font-normal uppercase text-soft">Alpa</dt>
                            <dd class="font-semibold text-heading text-lg mt-1">{{ $reportCard->attendance?->absent_count ?? 0 }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="rounded-2xl bg-surface-subtle p-5">
                    <h3 class="text-heading mb-3 text-theme-sm font-medium">Catatan Wali Kelas</h3>
                    <p class="min-h-[70px] text-theme-xs font-normal text-muted bg-surface p-4 rounded-xl border border-line">{{ $reportCard->homeroom_note ?: 'Tidak ada catatan khusus.' }}</p>
                </div>
            </section>

            <!-- Signatures Section -->
            <section class="mt-12 grid gap-6 text-center text-theme-xs font-normal uppercase text-body sm:grid-cols-3 pt-8 border-t border-line">
                @forelse ($reportCard->signatures->sortBy('sort_order') as $signature)
                    <div>
                        <p class="text-soft text-theme-xs font-normal uppercase">{{ $signature->role_label }}</p>
                        <div class="h-20"></div>
                        <p class="font-semibold text-heading border-t border-line w-fit mx-auto pt-1 px-4">{{ $signature->person_name ?? $signature->teacher?->name ?? '-' }}</p>
                    </div>
                @empty
                    <div>
                        <p class="text-soft text-theme-xs font-normal uppercase">Wali Kelas</p>
                        <div class="h-20"></div>
                        <p class="font-semibold text-heading border-t border-line w-fit mx-auto pt-1 px-4">-</p>
                    </div>
                    <div>
                        <p class="text-soft text-theme-xs font-normal uppercase">Kepala Bagian Diniyyah</p>
                        <div class="h-20"></div>
                        <p class="font-semibold text-heading border-t border-line w-fit mx-auto pt-1 px-4">-</p>
                    </div>
                    <div>
                        <p class="text-soft text-theme-xs font-normal uppercase">Kepala Sekolah</p>
                        <div class="h-20"></div>
                        <p class="font-semibold text-heading border-t border-line w-fit mx-auto pt-1 px-4">-</p>
                    </div>
                @endforelse
            </section>
        </section>
    </div>
</x-layouts.portal>
