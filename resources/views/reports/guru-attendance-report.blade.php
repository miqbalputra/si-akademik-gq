<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Presensi - {{ $report['teacher']['nama'] ?? $teacher->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 11mm 14mm; }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 3px solid #111827; padding-bottom: 8px; }
        .eyebrow { color: #087443; font-size: 8px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        h1 { font-size: 18px; margin-top: 3px; }
        .subtitle { color: #64748b; margin-top: 3px; }
        .meta, .stats, .data { border-collapse: collapse; width: 100%; }
        .meta { margin: 10px 0; }
        .meta td { padding: 2px 6px 2px 0; vertical-align: top; }
        .meta .label { color: #64748b; font-weight: bold; width: 60px; }
        .stats { margin-bottom: 10px; }
        .stats td { border: 1px solid #d9e2dc; padding: 6px; width: 16.66%; }
        .stats .label { color: #64748b; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .stats .value { color: #111827; font-size: 15px; font-weight: bold; margin-top: 2px; }
        .section { background: #111827; border-left: 4px solid #00df66; color: #fff; font-size: 8px; font-weight: bold; padding: 5px 7px; }
        .data { margin-top: 0; table-layout: fixed; }
        .data thead { display: table-header-group; }
        .data th, .data td { border: .5px solid #d9e2dc; padding: 4px; vertical-align: top; }
        .data th { background: #f1f5f3; font-size: 7.5px; text-align: left; }
        .data td { font-size: 7.5px; line-height: 1.25; }
        .center { text-align: center; }
        .footer { border-top: 1px solid #d9e2dc; color: #64748b; font-size: 7px; margin-top: 9px; padding-top: 5px; }
    </style>
</head>
<body>
    @php($summary = $report['summary'] ?? [])
    <div class="header">
        <p class="eyebrow">Ruang GQ · Griya Qur'an Tunas Ilmu</p>
        <h1>Rekap Presensi Saya</h1>
        <p class="subtitle">Data kehadiran tersinkron baca-saja dari GeoPresensi.</p>
    </div>
    <table class="meta">
        <tr><td class="label">Guru</td><td>{{ $report['teacher']['nama'] ?? $teacher->name }}</td><td class="label">NIY</td><td>{{ $report['teacher']['id_guru'] ?? $teacher->niy ?: '-' }}</td></tr>
        <tr><td class="label">Periode</td><td>{{ $report['period']['label'] ?? '-' }}</td><td class="label">Tersinkron</td><td>{{ $report['synced_at_label'] ?? '-' }}</td></tr>
    </table>
    <table class="stats"><tr>
        @foreach([['Hari kerja', $summary['total_hari'] ?? 0], ['Hadir', $summary['hadir'] ?? 0], ['Izin', $summary['izin'] ?? 0], ['Sakit', $summary['sakit'] ?? 0], ['Alfa', $summary['alfa'] ?? 0], ['Kehadiran', ($summary['persentase'] ?? 0).'%']] as [$label, $value])
            <td><p class="label">{{ $label }}</p><p class="value">{{ $value }}</p></td>
        @endforeach
    </tr></table>
    <div class="section">RINCIAN PRESENSI HARIAN</div>
    <table class="data">
        <thead><tr><th style="width:22%">Tanggal</th><th style="width:14%">Jam Masuk</th><th style="width:14%">Jam Pulang</th><th style="width:20%">Status</th><th style="width:30%">Keterangan</th></tr></thead>
        <tbody>
            @forelse($report['rows'] ?? [] as $row)
                <tr><td>{{ $row['tanggal'] ?: '-' }}</td><td class="center">{{ $row['jam_masuk'] ?: '-' }}</td><td class="center">{{ $row['jam_pulang'] ?: '-' }}</td><td>{{ $row['status_label'] }}</td><td>{{ $row['keterangan'] ?: '-' }}</td></tr>
            @empty
                <tr><td colspan="5" class="center">Tidak ada data presensi pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="footer">Laporan ini dihasilkan oleh Edu dari data GeoPresensi. Perubahan presensi dilakukan melalui GeoPresensi.</p>
</body>
</html>
