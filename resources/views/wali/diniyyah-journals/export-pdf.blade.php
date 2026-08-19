<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Jurnal Diniyyah</title>
    <style>
        @page { margin: 10mm 9mm; }
        body { color: #111512; font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; }
        .header { border-bottom: 3px solid #111512; margin-bottom: 10px; padding-bottom: 7px; }
        .header h1 { font-size: 16px; margin: 0; text-transform: uppercase; }
        .header p { color: #0b6e37; font-size: 8px; font-weight: bold; letter-spacing: 1px; margin: 4px 0 0; text-transform: uppercase; }
        .meta, .summary, .data-table { border-collapse: collapse; width: 100%; }
        .meta { margin-bottom: 10px; }
        .meta td { padding: 2px 5px 2px 0; }
        .label { color: #58625c; font-weight: bold; }
        .summary { margin-bottom: 10px; }
        .summary td { border: 1px solid #c8d0ca; background: #f3f7f2; padding: 5px; }
        .summary strong { display: block; color: #111512; font-size: 12px; margin-top: 2px; }
        .data-table { table-layout: fixed; }
        .data-table th, .data-table td { border: 1px solid #c8d0ca; padding: 5px; text-align: left; vertical-align: top; }
        .data-table th { background: #f0f4f0; font-size: 8px; }
        .data-table td { line-height: 1.25; }
        .status-terisi { color: #087a3c; font-weight: bold; }
        .status-libur { color: #58625c; font-weight: bold; }
        .status-excused { background: #fff8e1; color: #8a4b08; font-weight: bold; }
        .status-agenda { background: #eff6ff; color: #075985; font-weight: bold; }
        .status-kosong { background: #fff0ee; color: #9b1d13; font-weight: bold; }
        .empty-note { color: #9b1d13; font-weight: bold; }
        .footer { border-top: 1px solid #c8d0ca; color: #58625c; font-size: 7px; margin-top: 9px; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REKAPITULASI JURNAL MENGAJAR DINIYYAH</h1>
        <p>Bulan {{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }} {{ $year }}</p>
    </div>

    <table class="meta">
        <tr><td class="label" width="100">Wali Kelas</td><td width="10">:</td><td>{{ $teacher->name }}</td><td class="label" width="90">Dicetak</td><td width="10">:</td><td>{{ now()->translatedFormat('d F Y, H:i') }}</td></tr>
    </table>

    <table class="summary">
        <tr>
            <td>Total slot<strong>{{ $summary['total_slots'] ?? count($monitoringRows ?? []) }}</strong></td>
            <td>Sudah terisi<strong>{{ $summary['filled_slots'] ?? 0 }}</strong></td>
            <td>Jurnal kosong<strong>{{ $summary['empty_slots'] ?? 0 }}</strong></td>
            <td>Hari libur<strong>{{ $summary['holiday_slots'] ?? 0 }}</strong></td>
            <td>Dibebaskan<strong>{{ $summary['excused_slots'] ?? 0 }}</strong></td>
            <td>Agenda tanpa KBM<strong>{{ $summary['agenda_slots'] ?? 0 }}</strong></td>
        </tr>
    </table>

    <table class="data-table">
        <thead><tr><th style="width:11%">Tanggal</th><th style="width:10%">Jam / Sesi</th><th style="width:19%">Kelas &amp; Mapel</th><th style="width:18%">Guru</th><th style="width:10%">Status</th><th style="width:32%">Jurnal &amp; Kehadiran</th></tr></thead>
        <tbody>
            @forelse($monitoringRows ?? [] as $row)
                @php($isEmpty = $row['status'] === 'KOSONG')
                <tr class="{{ $isEmpty ? 'status-kosong' : ($row['status'] === 'AGENDA' ? 'status-agenda' : (in_array($row['status'], ['IZIN', 'SAKIT'], true) ? 'status-excused' : '')) }}">
                    <td><strong>{{ $row['date']->translatedFormat('D, d/m/Y') }}</strong>@if($row['is_holiday'])<br><span class="status-libur">{{ $row['holiday_name'] ?? 'Hari Libur' }}</span>@endif</td>
                    <td>Jam {{ $row['session_name'] }}@if($row['session_time'])<br>{{ substr((string) $row['session_time']['starts_at'], 0, 5) }} - {{ substr((string) $row['session_time']['ends_at'], 0, 5) }}@endif</td>
                    <td><strong>{{ $row['classroom_name'] }}</strong><br>{{ $row['subject_name'] }}</td>
                    <td>{{ $row['teacher_name'] }}@if($row['substitute_teacher_name'])<br><span class="label">Diisi pengganti: {{ $row['substitute_teacher_name'] }}</span>@endif</td>
                    <td>@if($row['status'] === 'TERISI')<span class="status-terisi">Terisi</span>@elseif($row['status'] === 'TERISI_TIDAK_TERJADWAL')<span class="status-terisi">Terisi (Ekstra)</span>@elseif($row['status'] === 'LIBUR')<span class="status-libur">Libur</span>@elseif($row['status'] === 'AGENDA')<span class="status-agenda">AGENDA · Tanpa KBM</span>@elseif(in_array($row['status'], ['IZIN', 'SAKIT'], true))<span class="status-excused">{{ $row['status'] }} · Dibebaskan</span>@else<span class="empty-note">KOSONG</span>@endif</td>
                    <td>@if($row['journal'])<strong>Materi:</strong> {{ $row['journal']->material ?: '-' }}<br><strong>Absensi:</strong> @if($row['journal']->absences->isEmpty())Hadir semua @else{{ $row['journal']->absences->map(fn ($abs) => ($abs->classEnrollment->student->name ?? '-').' ('.$abs->status.')')->implode(', ') }}@endif @elseif($row['status'] === 'AGENDA')<span class="status-agenda">{{ $row['agenda_reason'] ?? 'Libur Mengajar - Agenda' }}</span>@elseif(in_array($row['status'], ['IZIN', 'SAKIT'], true))<span class="status-excused">Dibebaskan oleh presensi: {{ strtolower($row['status']) }}.</span>@else<span class="empty-note">Belum ada data jurnal.</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:18px;">Tidak ada data jadwal untuk periode dan filter yang dipilih.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="footer">Laporan mengikuti filter pada halaman Monitoring Jurnal Kelas. Jurnal kosong ditandai dengan warna merah.</p>
</body>
</html>
