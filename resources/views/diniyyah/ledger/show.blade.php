<x-layouts.portal :title="$snapshot->title" portalLabel="Portal Guru" breadcrumb="Wali Kelas / Leger">
    @php
        $combined = ($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1';
    @endphp
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="badge badge-amber text-theme-xs font-medium">Leger Diniyyah</span>
                    <h1 class="mt-3 text-on-primary ui-page-title">{{ $snapshot->title }}</h1>
                    <p class="mt-2 text-theme-sm font-normal text-on-primary/80">
                        {{ $snapshot->classroomTerm?->name }} &middot; {{ $snapshot->academicTerm?->name }} &middot; {{ $snapshot->academicTerm?->academicYear?->name }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="status-badge status-badge-{{ \App\Support\UiLabel::statusColor($snapshot->status) }} text-theme-xs font-medium">{{ \App\Support\UiLabel::statusLabel($snapshot->status) }}</span>
                    <span class="badge bg-surface/10 text-on-primary/80 text-theme-xs font-medium">Dibuat {{ $snapshot->generated_at?->format('d M Y H:i') ?? '—' }}</span>
                </div>
            </div>
        </header>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('diniyyah.ledger.index') }}" class="btn btn-outline min-h-11 text-theme-sm font-medium">Kembali ke kelas saya</a>
            <a href="{{ route('diniyyah.ledger.export-excel', $snapshot) }}" class="btn min-h-11 bg-success-600 text-white hover:bg-success-700 text-theme-sm font-medium">Unduh Excel</a>
            <a href="{{ route('diniyyah.ledger.export-pdf', $snapshot) }}" class="btn min-h-11">Unduh PDF</a>
            @if(!in_array($snapshot->status, ['locked','published'], true))
                <form method="POST" action="{{ route('diniyyah.ledger.generate', $snapshot->classroom_term_id) }}">@csrf<button class="btn">Segarkan leger</button></form>
                <form method="POST" action="{{ route('diniyyah.ledger.lock', $snapshot) }}">@csrf<button class="btn btn-primary" @disabled(($summary['blocking_issues'] ?? 0) > 0)>Kunci leger final</button></form>
            @endif
        </div>
        @foreach($exports as $export)
            @if($export->status === 'completed')<a class="btn" href="{{ route('diniyyah.ledger.download', $export) }}">Unduh Excel yang selesai diproses</a>
            @elseif(in_array($export->status, ['queued','processing'],true))<p class="text-muted" role="status">Ekspor Excel sedang diproses. Muat ulang halaman untuk memeriksa hasilnya.</p>
            @elseif($export->status === 'failed')<p class="text-danger-ink" role="alert">Ekspor Excel gagal. Silakan unduh ulang.</p>@endif
        @endforeach

        @if (session('status'))
            <div class="inline-feedback inline-feedback-success" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-label="Ringkasan leger">
            <div class="metric-card"><p class="metric-label ui-metric-label">Santri</p><p class="metric-value">{{ $summary['total_students'] ?? $snapshot->rows->count() }}</p></div>
            <div class="metric-card"><p class="metric-label ui-metric-label">Kolom mapel</p><p class="metric-value">{{ $summary['score_columns'] ?? $summary['total_columns'] ?? $columns->count() }}</p></div>
            <div class="metric-card border-success-line bg-success-soft"><p class="metric-label text-success-ink ui-metric-label">Lengkap</p><p class="metric-value text-success-ink">{{ $summary['complete_rows'] ?? 0 }}</p></div>
            <div class="metric-card border-warning-line bg-warning-soft"><p class="metric-label text-warning-ink ui-metric-label">Belum lengkap</p><p class="metric-value text-warning-ink">{{ $summary['incomplete_rows'] ?? 0 }}</p></div>
            <div class="metric-card {{ ($summary['blocking_issues'] ?? 0) > 0 ? 'border-danger-line bg-danger-soft' : 'border-success-line bg-success-soft' }}"><p class="metric-label {{ ($summary['blocking_issues'] ?? 0) > 0 ? 'text-danger-ink' : 'text-success-ink' }} ui-metric-label">Masalah</p><p class="metric-value {{ ($summary['blocking_issues'] ?? 0) > 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $summary['blocking_issues'] ?? 0 }}</p></div>
        </section>

        @if ($issues->isNotEmpty())
            <section class="rounded-2xl border border-warning-line bg-warning-soft p-5" aria-labelledby="ledger-issues-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="ledger-issues-heading" class="text-warning-ink ui-card-title">Leger belum siap dikunci</h2>
                        <p class="mt-1 text-theme-sm font-normal text-warning-ink">Selesaikan masalah berikut agar ranking dan rapor aman diproses.</p>
                    </div>
                    <span class="badge badge-amber text-theme-xs font-medium">{{ $issues->count() }} catatan</span>
                </div>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($issues->take(10) as $issue)
                        <li class="rounded-xl border border-warning-line bg-surface/70 p-3 text-theme-sm font-medium text-warning-ink">{{ $issue['message'] ?? 'Masalah leger belum teridentifikasi.' }}</li>
                    @endforeach
                </ul>
                @if ($issues->count() > 10)
                    <p class="mt-3 text-theme-xs font-normal text-warning-ink">+ {{ $issues->count() - 10 }} catatan lainnya.</p>
                @endif
            </section>
        @endif

        <section class="card-lg overflow-hidden" aria-labelledby="ledger-table-heading">
            <div class="border-b border-line px-5 py-4 sm:px-6">
                <h2 id="ledger-table-heading" class="text-heading ui-card-title">Data leger santri</h2>
                <p class="mt-1 text-theme-xs font-normal text-muted">Geser tabel secara horizontal pada layar kecil untuk melihat seluruh kolom. @if($combined)Total gabungan menjumlahkan nilai Diniyyah dan Tahfidz; juz dan jumlah presensi tidak masuk perhitungan.@endif</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-left text-theme-sm" aria-label="Data leger {{ $snapshot->title }}">
                    <thead class="bg-surface-subtle text-theme-xs font-medium">
                        <tr>
                            <th scope="col" class="sticky left-0 z-20 bg-surface-subtle px-4 py-3 text-muted text-theme-xs font-medium">No</th>
                            <th scope="col" class="sticky left-12 z-20 min-w-[220px] bg-surface-subtle px-4 py-3 text-muted text-theme-xs font-medium">Santri</th>
                            <th scope="col" class="px-4 py-3 text-muted text-theme-xs font-medium">NIS</th>
                            @foreach ($columns as $column)
                                <th scope="col" class="min-w-[120px] border-l border-line px-4 py-3 text-body text-theme-xs font-medium">
                                    {{ $column['label'] }}
                                    @if (($column['source_type'] ?? null) === 'diniyyah_assessment_set')
                                        <span class="mt-1 block text-theme-xs font-normal normal-case text-muted">{{ \App\Support\UiLabel::statusLabel($column['status'] ?? null) }}</span>
                                    @elseif (($column['source_type'] ?? null) === 'student_attendance_recap')
                                        <span class="mt-1 block text-theme-xs font-normal normal-case text-info-ink">Rekap</span>
                                    @endif
                                </th>
                            @endforeach
                            <th scope="col" class="border-l-2 border-line px-4 py-3 text-heading text-theme-xs font-medium">Total</th>
                            <th scope="col" class="px-4 py-3 text-heading text-theme-xs font-medium">Rata-rata</th>
                            <th scope="col" class="px-4 py-3 text-warning-ink text-theme-xs font-medium">Peringkat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($snapshot->rows->sortBy('row_number') as $row)
                            @php
                                $cells = $row->cells->keyBy('column_key');
                                $total = $combined ? $cells->get('combined_total')?->value_numeric : $row->total_diniyyah_score;
                                $average = $combined ? $cells->get('combined_average')?->value_numeric : $row->average_diniyyah_score;
                                $rank = $combined ? $cells->get('combined_rank')?->value_numeric : $row->rank_in_class;
                                $rowIncomplete = $total === null;
                            @endphp
                            <tr class="{{ $rowIncomplete ? 'bg-warning-soft/50' : ($loop->even ? 'bg-surface-subtle/50' : 'bg-surface') }}">
                                <th scope="row" class="sticky left-0 z-10 bg-inherit px-4 py-3 text-left text-soft text-theme-xs font-medium">{{ $row->row_number }}</th>
                                <td class="sticky left-12 z-10 bg-inherit px-4 py-3">
                                    <p class="font-medium text-heading">{{ $row->student_name }}</p>
                                    @if ($rowIncomplete)<p class="mt-1 text-theme-xs font-normal text-warning-ink">Belum lengkap</p>@endif
                                </td>
                                <td class="px-4 py-3 text-muted text-theme-sm">{{ $row->student_nis }}</td>
                                @foreach ($columns as $column)
                                    @php
                                        $cell = $cells->get($column['key']);
                                        $isAttendance = ($column['source_type'] ?? null) === 'student_attendance_recap';
                                        $missing = ($column['numeric'] ?? true) ? $cell?->value_numeric === null : $cell?->value_text === null;
                                    @endphp
                                    <td class="border-l border-line px-4 py-3 font-medium {{ $missing ? 'text-warning-ink' : 'text-body' }}">
                                        {{ $isAttendance || !($column['numeric'] ?? true) ? ($cell?->value_text ?? 'Belum ada') : ($cell?->value_numeric ?? 'Belum ada') }}
                                    </td>
                                @endforeach
                                <td class="border-l-2 border-line px-4 py-3 font-medium text-heading">{{ $total ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-body">{{ $average ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-warning-ink">{{ $rank !== null ? '#'.(int)$rank : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.portal>
