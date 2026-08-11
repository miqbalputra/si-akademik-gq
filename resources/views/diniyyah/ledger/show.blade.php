<x-layouts.portal :title="$snapshot->title" portalLabel="Portal Manajemen" breadcrumb="Leger Diniyyah">
    <div class="space-y-6">
        <header class="vantis-hero p-6 sm:p-8">
            <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="badge badge-amber">Leger Diniyyah</span>
                    <h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">{{ $snapshot->title }}</h1>
                    <p class="mt-2 text-sm font-medium text-slate-300">
                        {{ $snapshot->classroomTerm?->name }} &middot; {{ $snapshot->academicTerm?->name }} &middot; {{ $snapshot->academicTerm?->academicYear?->name }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="status-badge status-badge-{{ \App\Support\UiLabel::statusColor($snapshot->status) }}">{{ \App\Support\UiLabel::statusLabel($snapshot->status) }}</span>
                    <span class="badge bg-white/10 text-slate-200">Dibuat {{ $snapshot->generated_at?->format('d M Y H:i') ?? '—' }}</span>
                </div>
            </div>
        </header>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('diniyyah.monitoring.index') }}" class="btn btn-outline min-h-11">Kembali ke Monitoring</a>
            <a href="{{ route('diniyyah.ledger.export-excel', $snapshot) }}" class="btn min-h-11 bg-emerald-600 text-white hover:bg-emerald-700">Unduh Excel</a>
        </div>

        @if (session('status'))
            <div class="inline-feedback inline-feedback-success" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-label="Ringkasan leger">
            <div class="metric-card"><p class="metric-label">Santri</p><p class="metric-value">{{ $summary['total_students'] ?? $snapshot->rows->count() }}</p></div>
            <div class="metric-card"><p class="metric-label">Kolom mapel</p><p class="metric-value">{{ $summary['score_columns'] ?? $summary['total_columns'] ?? $columns->count() }}</p></div>
            <div class="metric-card border-emerald-200 bg-emerald-50"><p class="metric-label text-emerald-700">Lengkap</p><p class="metric-value text-emerald-900">{{ $summary['complete_rows'] ?? 0 }}</p></div>
            <div class="metric-card border-amber-200 bg-amber-50"><p class="metric-label text-amber-700">Belum lengkap</p><p class="metric-value text-amber-900">{{ $summary['incomplete_rows'] ?? 0 }}</p></div>
            <div class="metric-card {{ ($summary['blocking_issues'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }}"><p class="metric-label {{ ($summary['blocking_issues'] ?? 0) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Masalah</p><p class="metric-value {{ ($summary['blocking_issues'] ?? 0) > 0 ? 'text-rose-900' : 'text-emerald-900' }}">{{ $summary['blocking_issues'] ?? 0 }}</p></div>
        </section>

        @if (auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']))
            <section class="card-lg p-5 sm:p-6" aria-labelledby="report-actions-heading">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.14em] text-amber-700">Workflow rapor</p>
                        <h2 id="report-actions-heading" class="mt-1 text-lg font-black text-slate-900">Manajemen rapor kelas</h2>
                        <p class="mt-1 text-sm font-medium text-slate-500">Generate, kunci, dan terbitkan rapor Diniyyah untuk seluruh santri.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('report-cards.generate', $snapshot) }}">
                            @csrf
                            <button type="submit" class="btn min-h-11 bg-amber-600 text-white hover:bg-amber-700" @disabled(!in_array($snapshot->status, ['locked', 'published'], true) || ($summary['blocking_issues'] ?? 0) > 0)>Generate Rapor</button>
                        </form>
                        <form method="POST" action="{{ route('report-cards.ledger.lock', $snapshot) }}">
                            @csrf
                            <button type="submit" class="btn min-h-11 bg-slate-950 text-white hover:bg-slate-800" @disabled(($reportCardSummary['missing'] ?? 0) > 0 || ($reportCardSummary['draft'] ?? 0) === 0)>Kunci Semua</button>
                        </form>
                        <form method="POST" action="{{ route('report-cards.ledger.publish', $snapshot) }}">
                            @csrf
                            <button type="submit" class="btn min-h-11 bg-emerald-600 text-white hover:bg-emerald-700" @disabled(($reportCardSummary['missing'] ?? 0) > 0 || ($reportCardSummary['draft'] ?? 0) > 0 || ($reportCardSummary['locked'] ?? 0) === 0)>Terbitkan Semua</button>
                        </form>
                    </div>
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-xl bg-slate-50 p-3 text-center"><dt class="metric-label">Target</dt><dd class="mt-1 text-lg font-black text-slate-900">{{ $reportCardSummary['expected'] ?? 0 }}</dd></div>
                    <div class="rounded-xl bg-rose-50 p-3 text-center"><dt class="metric-label text-rose-700">Belum dibuat</dt><dd class="mt-1 text-lg font-black text-rose-900">{{ $reportCardSummary['missing'] ?? 0 }}</dd></div>
                    <div class="rounded-xl bg-amber-50 p-3 text-center"><dt class="metric-label text-amber-700">Draf</dt><dd class="mt-1 text-lg font-black text-amber-900">{{ $reportCardSummary['draft'] ?? 0 }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 text-center"><dt class="metric-label">Dikunci</dt><dd class="mt-1 text-lg font-black text-slate-900">{{ $reportCardSummary['locked'] ?? 0 }}</dd></div>
                    <div class="rounded-xl bg-emerald-50 p-3 text-center"><dt class="metric-label text-emerald-700">Sudah terbit</dt><dd class="mt-1 text-lg font-black text-emerald-900">{{ $reportCardSummary['published'] ?? 0 }}</dd></div>
                </dl>
            </section>
        @endif

        @if ($issues->isNotEmpty())
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5" aria-labelledby="ledger-issues-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="ledger-issues-heading" class="font-black text-amber-900">Leger belum siap dikunci</h2>
                        <p class="mt-1 text-sm font-medium text-amber-800">Selesaikan masalah berikut agar ranking dan rapor aman diproses.</p>
                    </div>
                    <span class="badge badge-amber">{{ $issues->count() }} catatan</span>
                </div>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($issues->take(10) as $issue)
                        <li class="rounded-xl border border-amber-200 bg-white/70 p-3 text-sm font-semibold text-amber-900">{{ $issue['message'] ?? 'Masalah leger belum teridentifikasi.' }}</li>
                    @endforeach
                </ul>
                @if ($issues->count() > 10)
                    <p class="mt-3 text-xs font-bold text-amber-800">+ {{ $issues->count() - 10 }} catatan lainnya.</p>
                @endif
            </section>
        @endif

        <section class="card-lg overflow-hidden" aria-labelledby="ledger-table-heading">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 id="ledger-table-heading" class="text-lg font-black text-slate-900">Data leger santri</h2>
                <p class="mt-1 text-xs font-medium text-slate-500">Geser tabel secara horizontal pada layar kecil untuk melihat seluruh kolom.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-left text-sm" aria-label="Data leger {{ $snapshot->title }}">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="sticky left-0 z-20 bg-slate-50 px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">No</th>
                            <th scope="col" class="sticky left-12 z-20 min-w-[220px] bg-slate-50 px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Santri</th>
                            <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">NIS</th>
                            @foreach ($columns as $column)
                                <th scope="col" class="min-w-[120px] border-l border-slate-100 px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-600">
                                    {{ $column['label'] }}
                                    @if (($column['source_type'] ?? null) === 'diniyyah_assessment_set')
                                        <span class="mt-1 block text-[10px] font-bold normal-case text-slate-500">{{ \App\Support\UiLabel::statusLabel($column['status'] ?? null) }}</span>
                                    @elseif (($column['source_type'] ?? null) === 'student_attendance_recap')
                                        <span class="mt-1 block text-[10px] font-bold normal-case text-sky-600">Rekap</span>
                                    @endif
                                </th>
                            @endforeach
                            <th scope="col" class="border-l-2 border-slate-200 px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-900">Total</th>
                            <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-900">Rata-rata</th>
                            <th scope="col" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-amber-700">Peringkat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($snapshot->rows->sortBy('row_number') as $row)
                            @php $rowIncomplete = $row->rank_in_class === null; @endphp
                            <tr class="{{ $rowIncomplete ? 'bg-amber-50/50' : ($loop->even ? 'bg-slate-50/50' : 'bg-white') }}">
                                <th scope="row" class="sticky left-0 z-10 bg-inherit px-4 py-3 text-left font-bold text-slate-400">{{ $row->row_number }}</th>
                                <td class="sticky left-12 z-10 bg-inherit px-4 py-3">
                                    <p class="font-black text-slate-900">{{ $row->student_name }}</p>
                                    @if ($rowIncomplete)<p class="mt-1 text-xs font-bold text-amber-700">Belum lengkap</p>@endif
                                </td>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-500">{{ $row->student_nis }}</td>
                                @foreach ($columns as $column)
                                    @php
                                        $cell = $row->cells->firstWhere('column_key', $column['key']);
                                        $isAttendance = ($column['source_type'] ?? null) === 'student_attendance_recap';
                                    @endphp
                                    <td class="border-l border-slate-100 px-4 py-3 font-semibold {{ !$isAttendance && $cell?->value_numeric === null ? 'text-amber-700' : 'text-slate-600' }}">
                                        {{ $isAttendance ? ($cell?->value_text ?? '0') : ($cell?->value_numeric ?? 'Belum ada') }}
                                    </td>
                                @endforeach
                                <td class="border-l-2 border-slate-200 px-4 py-3 font-black text-slate-900">{{ $row->total_diniyyah_score ?? '—' }}</td>
                                <td class="px-4 py-3 font-bold text-slate-600">{{ $row->average_diniyyah_score ?? '—' }}</td>
                                <td class="px-4 py-3 font-black text-amber-700">{{ $row->rank_in_class ? '#'.$row->rank_in_class : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.portal>
