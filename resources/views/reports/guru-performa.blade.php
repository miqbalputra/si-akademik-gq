<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Performa Mengajar - {{ $teacher->name }}</title>
    <style>
        @page { size: A3 landscape; margin: 11mm 10mm 13mm; }
        * { box-sizing: border-box; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 8px; margin: 0; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 2px solid #12304a; margin-bottom: 10px; padding-bottom: 8px; }
        .eyebrow { color: #1f6b8f; font-size: 8px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
        h1 { color: #12304a; font-size: 18px; margin-top: 3px; }
        .subtitle { color: #64748b; font-size: 8px; margin-top: 3px; }
        .meta { border-collapse: collapse; margin: 7px 0 9px; width: 100%; }
        .meta td { padding: 2px 5px 2px 0; vertical-align: top; }
        .meta .label { color: #64748b; font-weight: bold; width: 54px; }
        .stats { border-collapse: separate; border-spacing: 4px; margin: 0 -4px 9px; width: calc(100% + 8px); }
        .stat { background: #f1f6f8; border: 1px solid #d6e0e8; padding: 5px 7px; }
        .stat .label { color: #64748b; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .stat .value { color: #12304a; font-size: 14px; font-weight: bold; margin-top: 2px; }
        .section-title { background: #1f6b8f; color: #fff; font-size: 8px; font-weight: bold; padding: 5px 7px; }
        table.data { border-collapse: collapse; table-layout: fixed; width: 100%; }
        table.data thead { display: table-header-group; }
        table.data th, table.data td { border: 0.5px solid #cbd5e1; padding: 3px 3px; vertical-align: top; }
        table.data th { background: #eaf3f7; color: #12304a; font-size: 6.8px; font-weight: bold; text-align: left; }
        table.data td { font-size: 7px; line-height: 1.2; }
        .center { text-align: center; }
        .regular { color: #166534; font-weight: bold; }
        .substitute { color: #3730a3; font-weight: bold; }
        .empty { color: #64748b; padding: 15px !important; text-align: center; }
        .page-break { page-break-before: always; }
        .footer { border-top: 1px solid #d6e0e8; color: #64748b; font-size: 7px; margin-top: 8px; padding-top: 5px; }
    </style>
</head>
<body>
    @php
        $stats = $performa['stats'] ?? [];
        $journalRows = collect($performa['journal_rows'] ?? []);
        $slots = collect($performa['empty_slots'] ?? []);
    @endphp

    <div class="header">
        <p class="eyebrow">SIAKAD Griya Qur'an Tunas Ilmu</p>
        <h1>Laporan Performa Mengajar - Detail Lengkap</h1>
        <p class="subtitle">Seluruh data jurnal, status pengisian, materi, JP, guru pengganti, dan ringkasan kehadiran periode {{ $performa['month_label'] ?? '-' }}.</p>
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
            <td class="stat"><div class="label">Total slot</div><div class="value">{{ $stats['total'] ?? 0 }}</div></td>
            <td class="stat"><div class="label">Data jurnal</div><div class="value">{{ $stats['total_jurnal'] ?? 0 }}</div></td>
        </tr>
    </table>

    <div class="section-title">DETAIL SEMUA DATA JURNAL</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 2.5%;">No</th>
                <th style="width: 8%;">Tanggal</th>
                <th style="width: 6%;">Sesi</th>
                <th style="width: 5%;">Jam</th>
                <th style="width: 9%;">Kelas</th>
                <th style="width: 8%;">Mapel</th>
                <th style="width: 17%;">Materi</th>
                <th style="width: 3%;">JP</th>
                <th style="width: 8%;">Guru asli</th>
                <th style="width: 8%;">Pengganti</th>
                <th style="width: 8%;">Guru mengajar</th>
                <th style="width: 7%;">Status</th>
                <th style="width: 2.5%;">H</th>
                <th style="width: 2.5%;">S</th>
                <th style="width: 2.5%;">I</th>
                <th style="width: 2.5%;">A</th>
                <th style="width: 2.5%;">B</th>
            </tr>
        </thead>
        <tbody>
            @forelse($journalRows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['date_label'] ?? ($row['date'] ?? '-') }}</td>
                    <td>{{ $row['session_label'] ?? '-' }}</td>
                    <td>{{ $row['session_time'] ?? '-' }}</td>
                    <td>{{ $row['kelas'] ?? '-' }}</td>
                    <td>{{ $row['mapel'] ?? '-' }}</td>
                    <td>{{ $row['material'] ?? '-' }}</td>
                    <td class="center">{{ $row['jp'] ?? 0 }}</td>
                    <td>{{ $row['guru_asli'] ?? '-' }}</td>
                    <td>{{ $row['pengganti'] ?? '-' }}</td>
                    <td>{{ $row['guru_mengajar'] ?? '-' }}</td>
                    <td class="{{ ($row['type'] ?? null) === 'substitute' ? 'substitute' : 'regular' }}">{{ $row['type_label'] ?? '-' }}</td>
                    <td class="center">{{ $row['hadir'] ?? 0 }}</td>
                    <td class="center">{{ $row['sakit'] ?? 0 }}</td>
                    <td class="center">{{ $row['izin'] ?? 0 }}</td>
                    <td class="center">{{ $row['alpa'] ?? 0 }}</td>
                    <td class="center">{{ $row['bolos'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td class="empty" colspan="17">Tidak ada data jurnal pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($slots->isNotEmpty())
        <div class="page-break"></div>
        <div class="section-title">SLOT JURNAL YANG PERLU DILENGKAPI</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 17%;">Tanggal</th>
                    <th style="width: 16%;">Sesi</th>
                    <th style="width: 12%;">Jam</th>
                    <th style="width: 20%;">Mapel</th>
                    <th style="width: 31%;">Kelas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($slots as $index => $slot)
                    @php
                        $time = collect([$slot['starts_at'] ?? null, $slot['ends_at'] ?? null])
                            ->filter()
                            ->map(fn ($value) => substr((string) $value, 0, 5))
                            ->implode(' - ');
                    @endphp
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td>{{ $slot['date_label'] ?? ($slot['date'] ?? '-') }}</td>
                        <td>{{ $slot['session_label'] ?? '-' }}</td>
                        <td>{{ $time ?: '-' }}</td>
                        <td>{{ $slot['subject_name'] ?? '-' }}</td>
                        <td>{{ $slot['classroom_names'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">H = Hadir, S = Sakit, I = Izin, A = Alpa, B = Bolos. Data jurnal mencakup jurnal reguler dan jurnal yang diisi guru pengganti. Laporan ini tidak mengubah data jurnal.</p>
</body>
</html>
