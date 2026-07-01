<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $snapshot->title }} — SIAKAD Griya Qur'an</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']},colors:{brand:{50:'#fffbeb',100:'#fef3c7',500:'#f59e0b',600:'#d97706',700:'#b45309'}}}}}</script>
    @endif
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #1e293b; margin: 0; -webkit-font-smoothing: antialiased; }
        .bg-grid { background-size: 40px 40px; background-image: linear-gradient(to right, rgba(0,0,0,.025) 1px, transparent 1px), linear-gradient(to bottom, rgba(0,0,0,.025) 1px, transparent 1px); }
        .portal-nav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.95); backdrop-filter: blur(16px); border-bottom: 1px solid #f1f5f9; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-weight: 700; font-size: 13px; border-radius: 10px; padding: 8px 16px; transition: all .2s; cursor: pointer; border: 1.5px solid transparent; text-decoration: none; white-space: nowrap; font-family: 'Outfit', sans-serif; }
        .btn-amber { background: #d97706; color: #fff; border-color: #d97706; box-shadow: 0 2px 8px rgba(217,119,6,.25); }
        .btn-amber:hover { background: #b45309; border-color: #b45309; }
        .btn-amber:disabled { background: #e2e8f0; color: #94a3b8; border-color: #e2e8f0; box-shadow: none; cursor: not-allowed; }
        .btn-dark { background: #0f172a; color: #fff; border-color: #0f172a; }
        .btn-dark:hover { background: #1e293b; }
        .btn-dark:disabled { background: #e2e8f0; color: #94a3b8; border-color: #e2e8f0; cursor: not-allowed; }
        .btn-green { background: #059669; color: #fff; border-color: #059669; }
        .btn-green:hover { background: #047857; }
        .btn-green:disabled { background: #e2e8f0; color: #94a3b8; border-color: #e2e8f0; cursor: not-allowed; }
        .btn-ghost { background: transparent; border: 1.5px solid #e2e8f0; color: #475569; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all .2s; display: inline-flex; align-items: center; gap: 5px; font-family: 'Outfit', sans-serif; }
        .btn-ghost:hover { background: #f8fafc; border-color: #fde68a; color: #d97706; }
        .btn-excel { background: #059669; color: #fff; border-radius: 10px; padding: 7px 16px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; }
        .btn-excel:hover { background: #047857; transform: translateY(-1px); }
        .stat-card { background: #fff; border: 1px solid #f1f5f9; border-radius: 14px; padding: 16px 20px; text-align: center; }
        .stat-card .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin: 0 0 6px; }
        .stat-card .value { font-size: 24px; font-weight: 900; color: #0f172a; margin: 0; }
        .stat-card.emerald { background: #f0fdf4; border-color: #bbf7d0; }
        .stat-card.emerald .label { color: #059669; }
        .stat-card.emerald .value { color: #065f46; }
        .stat-card.amber { background: #fffbeb; border-color: #fde68a; }
        .stat-card.amber .label { color: #d97706; }
        .stat-card.amber .value { color: #78350f; }
        .stat-card.red { background: #fef2f2; border-color: #fecaca; }
        .stat-card.red .label { color: #dc2626; }
        .stat-card.red .value { color: #991b1b; }
        .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .badge-emerald { background: #dcfce7; color: #166534; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .score-cell-missing { color: #d97706; font-weight: 700; }
        @keyframes fadeInUp { 0%{opacity:0;transform:translateY(14px)} 100%{opacity:1;transform:translateY(0)} }
        .fade-up { animation: fadeInUp .5s cubic-bezier(.16,1,.3,1) forwards; opacity: 0; }
        .delay-1 { animation-delay: .1s; }
        .delay-2 { animation-delay: .2s; }
    </style>
</head>
<body>
    <div class="fixed inset-0 z-[-1] bg-grid opacity-50"></div>

    {{-- Nav --}}
    <nav class="portal-nav">
        <div style="max-width:1400px;margin:0 auto;padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;">
            <a href="{{ url('/') }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                <span style="width:36px;height:36px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;color:#fff;">GQ</span>
                <div>
                    <span style="display:block;font-size:14px;font-weight:800;color:#0f172a;line-height:1.2;">Griya Qur'an</span>
                    <span style="display:block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#d97706;">Leger Diniyyah</span>
                </div>
            </a>
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="{{ route('diniyyah.monitoring') }}" class="btn-ghost">
                    <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                    Monitoring
                </a>
                <a href="{{ route('diniyyah.ledger.export-excel', $snapshot) }}" class="btn-excel">
                    <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export Excel
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Keluar</button>
                </form>
            </div>
        </div>
    </nav>

    <main style="max-width:1400px;margin:0 auto;padding:28px 24px;">

        {{-- Header --}}
        <header class="fade-up" style="margin-bottom:24px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <div style="display:inline-flex;align-items:center;gap:6px;background:#fef3c7;border:1px solid #fde68a;border-radius:999px;padding:3px 12px;margin-bottom:10px;">
                        <span style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.05em;">Leger Diniyyah</span>
                    </div>
                    <h1 style="font-size:24px;font-weight:900;color:#0f172a;margin:0 0 4px;letter-spacing:-.02em;">{{ $snapshot->title }}</h1>
                    <p style="font-size:13px;color:#64748b;font-weight:500;margin:0;">
                        {{ $snapshot->classroomTerm?->name }} &middot; {{ $snapshot->academicTerm?->name }} &middot; {{ $snapshot->academicTerm?->academicYear?->name }}
                    </p>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span class="badge badge-slate">Status: {{ $snapshot->status }}</span>
                    <span class="badge badge-slate" style="font-weight:500;">Generated: {{ $snapshot->generated_at?->format('d M Y H:i') ?? '—' }}</span>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div style="margin-bottom:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:#166534;display:flex;align-items:center;gap:8px;" class="fade-up">
                <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div style="margin-bottom:20px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:#991b1b;" class="fade-up">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Summary Stats --}}
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;" class="fade-up delay-1">
            <div class="stat-card">
                <p class="label">Santri</p>
                <p class="value">{{ $summary['total_students'] ?? $snapshot->rows->count() }}</p>
            </div>
            <div class="stat-card">
                <p class="label">Kolom Mapel</p>
                <p class="value">{{ $summary['score_columns'] ?? $summary['total_columns'] ?? $columns->count() }}</p>
            </div>
            <div class="stat-card emerald">
                <p class="label">Lengkap</p>
                <p class="value">{{ $summary['complete_rows'] ?? 0 }}</p>
            </div>
            <div class="stat-card amber">
                <p class="label">Belum Lengkap</p>
                <p class="value">{{ $summary['incomplete_rows'] ?? 0 }}</p>
            </div>
            <div class="stat-card {{ ($summary['blocking_issues'] ?? 0) > 0 ? 'red' : 'emerald' }}">
                <p class="label">Masalah</p>
                <p class="value">{{ $summary['blocking_issues'] ?? 0 }}</p>
            </div>
        </div>

        {{-- Rapor Actions --}}
        @if (auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']))
            <div class="card fade-up delay-1" style="padding:20px 24px;margin-bottom:20px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
                    <div>
                        <h2 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 4px;">Manajemen Rapor Kelas</h2>
                        <p style="font-size:12px;color:#64748b;font-weight:500;margin:0;">Generate, lock, dan publish rapor Diniyyah untuk seluruh santri di kelas ini.</p>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <form method="POST" action="{{ route('report-cards.generate', $snapshot) }}">
                            @csrf
                            <button type="submit" class="btn btn-amber"
                                @disabled(!in_array($snapshot->status, ['locked', 'published'], true) || ($summary['blocking_issues'] ?? 0) > 0)>
                                Generate Rapor
                            </button>
                        </form>
                        <form method="POST" action="{{ route('report-cards.ledger.lock', $snapshot) }}">
                            @csrf
                            <button type="submit" class="btn btn-dark"
                                @disabled(($reportCardSummary['missing'] ?? 0) > 0 || ($reportCardSummary['draft'] ?? 0) === 0)>
                                Lock Semua
                            </button>
                        </form>
                        <form method="POST" action="{{ route('report-cards.ledger.publish', $snapshot) }}">
                            @csrf
                            <button type="submit" class="btn btn-green"
                                @disabled(($reportCardSummary['missing'] ?? 0) > 0 || ($reportCardSummary['draft'] ?? 0) > 0 || ($reportCardSummary['locked'] ?? 0) === 0)>
                                Publish Semua
                            </button>
                        </form>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;text-align:center;">
                    <div style="background:#f8fafc;border-radius:10px;padding:12px;">
                        <p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin:0 0 4px;">Target</p>
                        <p style="font-size:18px;font-weight:900;color:#0f172a;margin:0;">{{ $reportCardSummary['expected'] ?? 0 }}</p>
                    </div>
                    <div style="background:#fef2f2;border-radius:10px;padding:12px;">
                        <p style="font-size:10px;font-weight:700;color:#dc2626;text-transform:uppercase;margin:0 0 4px;">Belum Dibuat</p>
                        <p style="font-size:18px;font-weight:900;color:#991b1b;margin:0;">{{ $reportCardSummary['missing'] ?? 0 }}</p>
                    </div>
                    <div style="background:#fffbeb;border-radius:10px;padding:12px;">
                        <p style="font-size:10px;font-weight:700;color:#d97706;text-transform:uppercase;margin:0 0 4px;">Draft</p>
                        <p style="font-size:18px;font-weight:900;color:#78350f;margin:0;">{{ $reportCardSummary['draft'] ?? 0 }}</p>
                    </div>
                    <div style="background:#f8fafc;border-radius:10px;padding:12px;">
                        <p style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;margin:0 0 4px;">Locked</p>
                        <p style="font-size:18px;font-weight:900;color:#0f172a;margin:0;">{{ $reportCardSummary['locked'] ?? 0 }}</p>
                    </div>
                    <div style="background:#f0fdf4;border-radius:10px;padding:12px;">
                        <p style="font-size:10px;font-weight:700;color:#059669;text-transform:uppercase;margin:0 0 4px;">Published</p>
                        <p style="font-size:18px;font-weight:900;color:#065f46;margin:0;">{{ $reportCardSummary['published'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Issues Warning --}}
        @if ($issues->isNotEmpty())
            <div style="margin-bottom:20px;background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:18px 22px;" class="fade-up delay-1">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
                    <div>
                        <h2 style="font-size:14px;font-weight:800;color:#78350f;margin:0 0 4px;">⚠️ Leger belum siap dikunci</h2>
                        <p style="font-size:12px;color:#92400e;margin:0;">Selesaikan masalah berikut agar ranking dan rapor aman diproses.</p>
                    </div>
                    <span class="badge badge-amber">{{ $issues->count() }} catatan</span>
                </div>
                <ul style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:8px;list-style:none;margin:0;padding:0;">
                    @foreach ($issues->take(10) as $issue)
                        <li style="background:rgba(255,255,255,.7);border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:12px;font-weight:600;color:#78350f;">
                            {{ $issue['message'] ?? 'Masalah leger belum teridentifikasi.' }}
                        </li>
                    @endforeach
                </ul>
                @if ($issues->count() > 10)
                    <p style="margin-top:10px;font-size:12px;color:#92400e;font-weight:600;">+ {{ $issues->count() - 10 }} catatan lainnya.</p>
                @endif
            </div>
        @endif

        {{-- Data Table --}}
        <div class="card fade-up delay-2" style="overflow:hidden;">
            <div style="overflow-x:auto;">
                <table style="min-width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:2px solid #f1f5f9;">
                            <th style="position:sticky;left:0;z-index:20;background:#f8fafc;padding:12px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;white-space:nowrap;">No</th>
                            <th style="position:sticky;left:44px;z-index:20;background:#f8fafc;padding:12px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;min-width:200px;">Santri</th>
                            <th style="padding:12px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;">NIS</th>
                            @foreach ($columns as $column)
                                <th style="padding:12px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;min-width:120px;border-left:1px solid #f1f5f9;">
                                    {{ $column['label'] }}
                                    @if (($column['source_type'] ?? null) === 'diniyyah_assessment_set')
                                        <span style="display:block;font-size:9px;font-weight:700;text-transform:none;margin-top:2px;color:{{ ($column['status'] ?? null) === 'validated' ? '#059669' : '#d97706' }};">{{ $column['status'] ?? '—' }}</span>
                                    @elseif (($column['source_type'] ?? null) === 'student_attendance_recap')
                                        <span style="display:block;font-size:9px;font-weight:700;text-transform:none;margin-top:2px;color:#0ea5e9;">rekap</span>
                                    @endif
                                </th>
                            @endforeach
                            <th style="padding:12px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0f172a;border-left:2px solid #e2e8f0;">Total</th>
                            <th style="padding:12px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0f172a;">Rata-rata</th>
                            <th style="padding:12px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#d97706;">Rank</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshot->rows->sortBy('row_number') as $row)
                            @php $rowIncomplete = $row->rank_in_class === null; @endphp
                            <tr style="border-top:1px solid #f8fafc;background:{{ $rowIncomplete ? 'rgba(254,243,199,.4)' : ($loop->even ? '#fafaf8' : '#fff') }};">
                                <td style="position:sticky;left:0;z-index:10;background:inherit;padding:12px 14px;color:#94a3b8;font-size:12px;font-weight:600;">{{ $row->row_number }}</td>
                                <td style="position:sticky;left:44px;z-index:10;background:inherit;padding:12px 14px;">
                                    <div style="font-weight:700;color:#0f172a;">{{ $row->student_name }}</div>
                                    @if ($rowIncomplete)
                                        <div style="font-size:11px;font-weight:700;color:#d97706;margin-top:2px;">Belum lengkap</div>
                                    @endif
                                </td>
                                <td style="padding:12px 14px;color:#64748b;font-size:12px;">{{ $row->student_nis }}</td>
                                @foreach ($columns as $column)
                                    @php
                                        $cell = $row->cells->firstWhere('column_key', $column['key']);
                                        $isAttendance = ($column['source_type'] ?? null) === 'student_attendance_recap';
                                    @endphp
                                    <td style="padding:12px 14px;border-left:1px solid #f8fafc;{{ !$isAttendance && $cell?->value_numeric === null ? 'color:#d97706;font-weight:700;' : 'color:#475569;font-weight:500;' }}">
                                        {{ $isAttendance ? ($cell?->value_text ?? '0') : ($cell?->value_numeric ?? 'Belum ada') }}
                                    </td>
                                @endforeach
                                <td style="padding:12px 14px;font-weight:800;color:#0f172a;border-left:2px solid #e2e8f0;">{{ $row->total_diniyyah_score ?? '—' }}</td>
                                <td style="padding:12px 14px;font-weight:700;color:#64748b;">{{ $row->average_diniyyah_score ?? '—' }}</td>
                                <td style="padding:12px 14px;font-weight:900;color:#d97706;">{{ $row->rank_in_class ? '#'.$row->rank_in_class : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
