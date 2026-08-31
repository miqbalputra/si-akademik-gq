<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #172033; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 14px; margin: 0 0 8px; }
        .meta { margin: 10px 0; }
        .note { margin: 8px 0 12px; color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        th { background: #0f172a; color: white; font-size: 8px; }
        .right { text-align: right; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>REKAP JP TEREALISASI GURU PER KELAS</h1>
    <div class="meta">
        <strong>Kelas:</strong> {{ $classroomTerm->name }} &nbsp; | &nbsp;
        <strong>Periode:</strong> {{ $periodStart->translatedFormat('F Y') }} &nbsp; | &nbsp;
        <strong>Wali Kelas:</strong> {{ $teacher->name }}
    </div>
    <p class="note">JP terealisasi dihitung dari jurnal yang benar-benar terisi dan dapat digunakan sebagai dasar data penggajian.</p>

    <table>
        <thead>
            <tr><th>No</th><th>Guru</th><th>Mapel / Tugas</th><th>JP Asli</th><th>JP Pengganti</th><th>JP Tafsir</th><th>JP Terealisasi</th><th>Verifikasi</th></tr>
        </thead>
        <tbody>
            @forelse($recap['teachers'] as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['name'] }}<br><small>{{ $row['niy'] ?: '-' }}</small></td>
                    <td>{{ collect($row['subjects'])->implode(', ') ?: '-' }}@if($row['pengganti_dari'])<br><small>Pengganti: {{ collect($row['pengganti_dari'])->implode(', ') }}</small>@endif</td>
                    <td class="right">{{ $row['sesi_asli'] }}</td>
                    <td class="right">{{ $row['sesi_pengganti'] }}</td>
                    <td class="right">{{ $row['sesi_tafsir'] }}</td>
                    <td class="right"><strong>{{ $row['jp_terealisasi'] }}</strong></td>
                    <td>{{ $row['confirmation']['label'] }}@if(! empty($row['confirmation']['reason']))<br>{{ $row['confirmation']['reason'] }}@endif</td>
                </tr>
            @empty
                <tr><td colspan="8">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p>
        <strong>Total JP Terealisasi:</strong> {{ $recap['stats']['jp_terealisasi'] }} &nbsp; | &nbsp;
        <strong>Jurnal Kosong:</strong> {{ $recap['stats']['missing_slots'] }} &nbsp; | &nbsp;
        Dicetak: {{ now()->translatedFormat('d F Y H:i') }}
    </p>

    @if(collect($recap['missing_journal_rows'] ?? [])->isNotEmpty())
        <div class="page-break"></div>
        <h2>DAFTAR JURNAL KOSONG</h2>
        <div class="meta"><strong>Kelas:</strong> {{ $classroomTerm->name }} &nbsp; | &nbsp; <strong>Periode:</strong> {{ $periodStart->translatedFormat('F Y') }}</div>
        <table>
            <thead>
                <tr><th>No</th><th>Guru</th><th>Kelas</th><th>Mapel</th><th>Tanggal</th><th>Sesi</th><th>Jam</th></tr>
            </thead>
            <tbody>
                @foreach($recap['missing_journal_rows'] as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row['teacher_name'] }}<br><small>{{ $row['niy'] ?: '-' }}</small></td>
                        <td>{{ $row['classroom_name'] }}</td>
                        <td>{{ $row['subject_name'] }}</td>
                        <td>{{ $row['date_label'] }}</td>
                        <td>{{ $row['session_name'] }}</td>
                        <td>{{ $row['session_time'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
