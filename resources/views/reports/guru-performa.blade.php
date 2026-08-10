<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Performa Mengajar - {{ $teacher->name }}</title>
    <style>
        @page { margin: 13mm 12mm 15mm; }
        * { box-sizing: border-box; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 2px solid #12304a; margin-bottom: 12px; padding-bottom: 9px; }
        .eyebrow { color: #1f6b8f; font-size: 8px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
        h1 { color: #12304a; font-size: 18px; margin-top: 3px; }
        .subtitle { color: #64748b; font-size: 9px; margin-top: 3px; }
        .meta { border-collapse: collapse; margin: 8px 0 12px; width: 100%; }
        .meta td { padding: 3px 6px 3px 0; vertical-align: top; }
        .meta .label { color: #64748b; font-weight: bold; width: 58px; }
        .stats { border-collapse: separate; border-spacing: 5px; margin: 0 -5px 12px; width: calc(100% + 10px); }
        .stat { background: #f1f6f8; border: 1px solid #d6e0e8; padding: 7px 9px; }
        .stat .label { color: #64748b; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .stat .value { color: #12304a; font-size: 17px; font-weight: bold; margin-top: 3px; }
        .section-title { background: #1f6b8f; color: #fff; font-size: 9px; font-weight: bold; padding: 6px 8px; }
        table.data { border-collapse: collapse; table-layout: fixed; width: 100%; }
        table.data th, table.data td { border: 0.5px solid #cbd5e1; padding: 5px 4px; vertical-align: top; }
        table.data th { background: #eaf3f7; color: #12304a; font-size: 8px; font-weight: bold; text-align: left; }
        table.data td { font-size: 8.5px; line-height: 1.25; }
        .center { text-align: center; }
        .muted { color: #64748b; }
        .tafsir { color: #0e7490; font-weight: bold; }
        .empty { color: #64748b; padding: 18px !important; text-align: center; }
        .footer { border-top: 1px solid #d6e0e8; color: #64748b; font-size: 7.5px; margin-top: 10px; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $stats = $performa['stats'] ?? [];
        $slots = collect($performa['empty_slots'] ?? []);
    @endphp

    <div class="header">
        <p class="eyebrow">SIAKAD Griya Qur'an Tunas Ilmu</p>
        <h1>Laporan Performa Mengajar</h1>
        <p class="subtitle">Rekap jurnal Diniyyah berdasarkan jadwal guru pada periode {{ $performa['month_label'] ?? '-' }}.</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Guru</td><td>{{ $teacher->name }}</td>
            <td class="label">Periode</td><td>{{ $performa['month_label'] ?? '-' }}</td>
            <td class="label">Dicetak</td><td>{{ now()->locale('id')->translatedFormat('d F Y, H:i') }}</td>
        </tr>
    </table>

    <table class="stats">
        <tr>
            <td class="stat"><div class="label">Sudah diisi</div><div class="value">{{ $stats['sudah_diisi'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Kosong</div><div class="value">{{ $stats['kosong'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Digantikan</div><div class="value">{{ $stats['digantikan'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Total tercatat</div><div class="value">{{ $stats['total'] ?? 0 }}</div></td>
        </tr>
    </table>

    <div class="section-title">SLOT JURNAL YANG PERLU DILENGKAPI</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 16%;">Tanggal</th>
                <th style="width: 15%;">Sesi</th>
                <th style="width: 12%;">Jam</th>
                <th style="width: 20%;">Mapel</th>
                <th style="width: 33%;">Kelas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($slots as $index => $slot)
                @php
                    $time = collect([$slot['starts_at'] ?? null, $slot['ends_at'] ?? null])
                        ->filter()
                        ->map(fn ($value) => substr((string) $value, 0, 5))
                        ->implode(' - ');
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $slot['date_label'] ?? ($slot['date'] ?? '-') }}</td>
                    <td class="{{ ($slot['is_tafsir'] ?? false) ? 'tafsir' : '' }}">{{ $slot['session_label'] ?? '-' }}</td>
                    <td>{{ $time ?: '-' }}</td>
                    <td>{{ $slot['subject_name'] ?? '-' }}</td>
                    <td>{{ $slot['classroom_names'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td class="empty" colspan="6">Tidak ada slot jurnal kosong pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Slot kosong adalah jadwal yang sudah lewat, bukan hari libur, dan belum memiliki jurnal. Laporan ini tidak mengubah data jurnal.</p>
</body>
</html>
