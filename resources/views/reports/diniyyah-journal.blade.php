<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        body { color: #111512; font-family: DejaVu Sans, sans-serif; font-size: 8px; margin: 0; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 3px solid #111512; margin-bottom: 10px; padding-bottom: 8px; }
        .eyebrow { color: #0b6e37; font-size: 8px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
        h1 { color: #111512; font-size: 17px; margin-top: 3px; }
        .subtitle { color: #58625c; font-size: 8px; margin-top: 3px; }
        .meta { border-collapse: collapse; margin: 7px 0 10px; width: 100%; }
        .meta td { padding: 2px 5px 2px 0; vertical-align: top; }
        .meta .label { color: #58625c; font-weight: bold; width: 62px; }
        .stats { border-collapse: separate; border-spacing: 4px; margin: 0 -4px 9px; width: calc(100% + 8px); }
        .stat { background: #f4f6f3; border: 1px solid #c8d0ca; padding: 5px 7px; }
        .stat .label { color: #58625c; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .stat .value { color: #111512; font-size: 13px; font-weight: bold; margin-top: 2px; }
        .section-title { border-left: 4px solid #00df66; background: #111512; color: #fff; font-size: 8px; font-weight: bold; padding: 5px 7px; }
        table.data { border-collapse: collapse; table-layout: fixed; width: 100%; }
        table.data th, table.data td { border: 0.5px solid #c8d0ca; padding: 4px 3px; vertical-align: top; }
        table.data th { background: #f0f4f0; color: #111512; font-size: 7px; font-weight: bold; text-align: left; }
        table.data td { font-size: 7.5px; line-height: 1.25; }
        .center { text-align: center; }
        .muted { color: #58625c; }
        .type-regular { color: #166534; font-weight: bold; }
        .type-substitute { color: #3730a3; font-weight: bold; }
        .material { white-space: normal; word-wrap: break-word; }
        .empty { color: #58625c; padding: 16px !important; text-align: center; }
        .footer { border-top: 1px solid #c8d0ca; color: #58625c; font-size: 7px; margin-top: 9px; padding-top: 5px; }
    </style>
</head>
<body>
    @php
        $stats = $report['stats'] ?? [];
        $filters = $report['filter_labels'] ?? [];
        $rows = collect($report['rows'] ?? []);
    @endphp

    <div class="header">
        <p class="eyebrow">SIAKAD Griya Qur'an Tunas Ilmu</p>
        <h1>{{ $title }}</h1>
        <p class="subtitle">Ringkasan pengisian jurnal KBM Diniyyah dan detail kehadiran santri.</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Periode</td><td>{{ $filters['Periode'] ?? 'Semua periode' }}</td>
            <td class="label">Tanggal</td><td>{{ $filters['Tanggal'] ?? 'Semua tanggal' }}</td>
            <td class="label">Dicetak</td><td>{{ now()->locale('id')->translatedFormat('d F Y, H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Guru</td><td>{{ $filters['Guru'] ?? 'Semua guru' }}</td>
            <td class="label">Kelas</td><td>{{ $filters['Kelas'] ?? 'Semua kelas' }}</td>
            <td class="label">Mapel</td><td>{{ $filters['Mapel'] ?? 'Semua mapel' }}</td>
        </tr>
    </table>

    <table class="stats">
        <tr>
            <td class="stat"><div class="label">Total jurnal</div><div class="value">{{ $stats['total_jurnal'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Guru</div><div class="value">{{ $stats['total_guru'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Kelas</div><div class="value">{{ $stats['total_kelas'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Mapel</div><div class="value">{{ $stats['total_mapel'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Total JP</div><div class="value">{{ $stats['total_jp'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Pengganti</div><div class="value">{{ $stats['jurnal_pengganti'] ?? 0 }}</div></td>
        </tr>
    </table>

    <div class="section-title">DETAIL JURNAL</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th style="width: 7%;">Tanggal</th>
                <th style="width: 7%;">Jam</th>
                <th style="width: 10%;">Kelas</th>
                <th style="width: 10%;">Mapel</th>
                <th style="width: 10%;">Guru Asli</th>
                <th style="width: 10%;">Pengganti</th>
                <th style="width: 10%;">Guru Mengajar</th>
                <th style="width: 6%;">Jenis</th>
                <th style="width: 15%;">Materi</th>
                <th style="width: 3%;">JP</th>
                <th style="width: 3%;">H</th>
                <th style="width: 3%;">S</th>
                <th style="width: 3%;">I</th>
                <th style="width: 3%;">A</th>
                <th style="width: 3%;">B</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['date'] ?? '-' }}</td>
                    <td>{{ $row['session_label'] ?? '-' }}@if($row['session_time'])<br><span class="muted">{{ $row['session_time'] }}</span>@endif</td>
                    <td>{{ $row['kelas'] ?? '-' }}</td>
                    <td>{{ $row['mapel'] ?? '-' }}</td>
                    <td>{{ $row['guru_asli'] ?? '-' }}</td>
                    <td>{{ $row['pengganti'] ?? '-' }}</td>
                    <td><strong>{{ $row['guru_mengajar'] ?? '-' }}</strong></td>
                    <td class="{{ ($row['type'] ?? null) === 'substitute' ? 'type-substitute' : 'type-regular' }}">{{ $row['type_label'] ?? '-' }}</td>
                    <td class="material">{{ $row['material'] ?? '-' }}</td>
                    <td class="center">{{ $row['jp'] ?? 0 }}</td>
                    <td class="center">{{ $row['hadir'] ?? 0 }}</td>
                    <td class="center">{{ $row['sakit'] ?? 0 }}</td>
                    <td class="center">{{ $row['izin'] ?? 0 }}</td>
                    <td class="center">{{ $row['alpa'] ?? 0 }}</td>
                    <td class="center">{{ $row['bolos'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td class="empty" colspan="16">Tidak ada data jurnal untuk filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">H = Hadir, S = Sakit, I = Izin, A = Alpa, B = Bolos. Guru Mengajar adalah guru efektif untuk pencatatan JP/gaji.</p>
</body>
</html>
