<x-layouts.portal :title="$snapshot->title" portalLabel="Portal Guru" breadcrumb="Wali Kelas / Leger">
    @php
        $combined = ($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1';
    @endphp
    @push('head')
    <style>
        .ledger-scroll { max-height:72vh; overflow:auto; isolation:isolate; scrollbar-gutter:stable; }
        .ledger-grid { width:max-content; min-width:100%; border-collapse:separate; border-spacing:0; font-variant-numeric:tabular-nums; }
        .ledger-grid th,.ledger-grid td { border-right:1px solid var(--ui-line,#e2e8f0); border-bottom:1px solid var(--ui-line,#e2e8f0); padding:10px 12px; }
        .ledger-grid thead { position:sticky; top:0; z-index:25; }
        .ledger-grid thead th { text-align:center; vertical-align:middle; }
        .ledger-grid .ledger-group { height:36px; padding:6px 12px; letter-spacing:.025em; font-size:12px; }
        .ledger-grid .ledger-label { height:72px; min-width:100px; max-width:160px; background:var(--ui-surface,#fff); font-size:12px; }
        .ledger-grid .ledger-kkm { height:32px; padding:5px 10px; background:var(--ui-surface,#fff); font-size:11px; color:var(--ui-muted,#64748b); }
        .ledger-grid .ledger-number { position:sticky; left:0; width:40px; min-width:40px; max-width:40px; padding:10px 6px; z-index:12; background:inherit; text-align:center; }
        .ledger-grid .ledger-student { position:sticky; left:40px; width:220px; min-width:220px; max-width:220px; z-index:12; background:inherit; text-align:left; box-shadow:3px 0 5px -3px #33415555; white-space:normal; }
        .ledger-grid thead .ledger-number,.ledger-grid thead .ledger-student { top:0; z-index:40; background:var(--ui-surface,#fff); }
        .ledger-grid tbody tr { background:var(--ui-surface,#fff); }
        .ledger-grid tbody tr:nth-child(even) { background:color-mix(in srgb,var(--ui-surface,#fff),#64748b 4%); }
        .ledger-grid tbody tr:hover { outline:2px solid #16a34a66; outline-offset:-2px; }
        .ledger-grid .ledger-score { text-align:center; font-weight:500; white-space:nowrap; }
        .ledger-grid .ledger-missing { color:#a16207; background:color-mix(in srgb,var(--ui-surface,#fff),#eab308 8%); }
        .ledger-grid .ledger-below { color:#b45309; background:#fff7ed; font-weight:700; }
        .ledger-grid .ledger-result { font-weight:700; background:color-mix(in srgb,var(--ui-surface,#fff),#16a34a 6%); }
        @media(max-width:640px) { .ledger-grid .ledger-student { width:164px; min-width:164px; max-width:164px; }.ledger-scroll { max-height:68vh; } }
    </style>
    @endpush
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="badge badge-amber text-theme-xs font-medium">{{ $combined ? 'Diniyyah & Tahfidz' : 'Leger Diniyyah' }}</span>
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

        <section class="card-lg overflow-hidden" aria-labelledby="ledger-table-heading" x-data="{
            search:'', state:'all', group:'all',
            hasBelow(groups) { return this.group==='all' ? groups.length>0 : groups.split(' ').includes(this.group); },
            matches(search,complete,belowGroups) { return search.includes(this.search.toLowerCase().trim()) && (this.state==='all' || (this.state==='incomplete' && complete==='0') || (this.state==='complete' && complete==='1') || (this.state==='below' && this.hasBelow(belowGroups))); },
            count() { return this.$refs.body ? Array.from(this.$refs.body.querySelectorAll('[data-ledger-row]')).filter(r=>this.matches(r.dataset.search,r.dataset.complete,r.dataset.belowGroups)).length : {{ $ledger['rows']->count() }}; },
            showGroup(id) { return this.group==='all' || id==='summary' || this.group===id; }
        }">
            <div class="space-y-4 border-b border-line px-5 py-5 sm:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="ledger-table-heading" class="text-heading ui-card-title">Data leger santri</h2><p class="mt-1 text-theme-sm text-muted">Nama santri dan judul kolom tetap terlihat saat tabel digeser.</p></div><span class="badge text-theme-xs" aria-live="polite"><span x-text="count()">{{ $ledger['rows']->count() }}</span> / {{ $ledger['rows']->count() }} santri</span></div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="ui-field"><span>Cari santri</span><input type="search" x-model.debounce.150ms="search" placeholder="Nama atau NIS…" class="form-input"></label>
                    <label class="ui-field"><span>Tampilkan nilai</span><select x-model="group" class="form-input"><option value="all">Semua kelompok</option>@foreach($ledger['groups'] as $group)@if($group['id'] !== 'summary')<option value="{{ $group['id'] }}">{{ $group['label'] }}</option>@endif@endforeach</select></label>
                    <label class="ui-field"><span>Kondisi nilai</span><select x-model="state" class="form-input"><option value="all">Semua santri</option><option value="complete">Nilai lengkap</option><option value="incomplete">Belum lengkap</option><option value="below">Ada nilai di bawah KKM</option></select></label>
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-theme-xs text-muted"><span><strong class="text-warning-ink">—</strong> Nilai belum ada</span><span><span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span> Nilai di bawah KKM</span><span>Geser tabel untuk melihat semua nilai.</span></div>
            </div>
            <div class="ledger-scroll" tabindex="0" role="region" aria-label="Tabel leger yang dapat digeser">
                <table class="ledger-grid text-theme-sm text-body" aria-label="Data leger {{ $snapshot->classroomTerm?->name }}">
                    <thead>
                        <tr><th scope="col" rowspan="3" class="ledger-number">No</th><th scope="col" rowspan="3" class="ledger-student">Santri <span class="mt-1 block text-theme-xs font-normal text-muted">Nama / NIS</span></th>
                            @foreach($ledger['groups'] as $group)<th scope="colgroup" colspan="{{ count($group['columns']) }}" class="ledger-group" style="background:#{{ $group['background'] }};color:#{{ $group['color'] }}" x-show="showGroup('{{ $group['id'] }}')">{{ $group['label'] }}</th>@endforeach
                        </tr>
                        <tr>@foreach($ledger['columns'] as $column)<th scope="col" class="ledger-label" x-show="showGroup('{{ $column['group'] }}')">{{ $column['display_label'] }}@if($column['tested_material'] && $column['tested_material'] !== '-')<span class="mt-1 block text-theme-xs font-normal text-muted">{{ $column['tested_material'] }}</span>@endif</th>@endforeach</tr>
                        <tr>@foreach($ledger['columns'] as $column)<th class="ledger-kkm" x-show="showGroup('{{ $column['group'] }}')">@if($column['kkm'] !== null)KKM <strong>{{ (float)$column['kkm'] }}</strong>@elseif(($column['source_type'] ?? '') === 'diniyyah_assessment_set')Menunggu ACC@else—@endif</th>@endforeach</tr>
                    </thead>
                    <tbody x-ref="body">
                        @foreach($ledger['rows'] as $row)
                            <tr data-ledger-row data-search="{{ $row['search'] }}" data-complete="{{ $row['complete'] ? '1':'0' }}" data-below-groups="{{ implode(' ', $row['below_groups']) }}" x-show="matches($el.dataset.search,$el.dataset.complete,$el.dataset.belowGroups)">
                                <td class="ledger-number text-muted">{{ $row['number'] }}</td><th scope="row" class="ledger-student"><span class="font-medium text-heading">{{ $row['name'] }}</span><span class="mt-1 block text-theme-xs font-normal text-muted">{{ $row['nis'] ?: 'NIS belum diisi' }}</span>@if(!$row['complete'])<span class="mt-1 block text-theme-xs text-warning-ink">Belum lengkap</span>@elseif($row['below'])<span class="mt-1 block text-theme-xs text-warning-ink" x-show="hasBelow($el.closest('tr').dataset.belowGroups)">Di bawah KKM</span>@endif</th>
                                @foreach($ledger['columns'] as $column)
                                    @php
                                        $value = $row['values'][$column['key']];
                                    @endphp
                                    <td x-show="showGroup('{{ $column['group'] }}')" class="ledger-score {{ $column['group'] === 'summary' ? 'ledger-result' : '' }} {{ $value['missing'] ? 'ledger-missing' : ($value['below'] ? 'ledger-below' : '') }}" @if($value['below'])title="Di bawah KKM {{ (float)$column['kkm'] }}"@elseif($value['missing'])title="Nilai belum ada"@endif>{{ $value['display'] }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr x-cloak x-show="count()===0"><td colspan="{{ $ledger['columns']->count()+2 }}" class="p-8 text-center text-muted">Tidak ada santri yang sesuai pencarian atau filter.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line p-4 text-theme-xs text-muted">Pencarian dan filter hanya mengatur tampilan; unduhan memuat seluruh kelas. @if($combined)Total menjumlahkan nilai Diniyyah dan Tahfidz. Juz dan presensi tidak masuk penjumlahan.@endif</div>
        </section>
    </div>
</x-layouts.portal>
