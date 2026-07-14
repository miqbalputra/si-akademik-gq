<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Jurnal Diniyyah</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { font-size: 16px; margin: 0; text-transform: uppercase; }
        .header p { font-size: 12px; margin: 5px 0 0; }
        
        .info { margin-bottom: 15px; }
        .info table { width: 50%; }
        .info td { padding: 2px; }
        
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.data-table th, table.data-table td { border: 1px solid #333; padding: 6px; text-align: left; vertical-align: top; }
        table.data-table th { background-color: #f0f0f0; font-weight: bold; }
        
        .status-terisi { color: #166534; }
        .status-libur { color: #475569; }
        .status-kosong { color: #991b1b; font-weight: bold; }
        
        .signature-area { width: 100%; margin-top: 50px; }
        .signature-box { float: right; width: 250px; text-align: center; }
        .signature-box .name { margin-top: 70px; font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>

    <div class="header">
        <h1>REKAPITULASI JURNAL MENGAJAR DINIYYAH</h1>
        <p>Bulan {{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }} {{ $year }}</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td width="100"><strong>Wali Kelas</strong></td>
                <td width="10">:</td>
                <td>{{ $teacher->name }}</td>
            </tr>
            <tr>
                <td><strong>Dicetak Pada</strong></td>
                <td>:</td>
                <td>{{ now()->translatedFormat('d F Y, H:i') }}</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="12%">Hari/Tanggal</th>
                <th width="8%">Jam</th>
                <th width="20%">Kelas & Mapel</th>
                <th width="20%">Guru</th>
                <th width="10%">Status</th>
                <th width="30%">Materi & Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @forelse($monitoringData as $day)
                @foreach($day['items'] as $index => $item)
                    <tr>
                        @if($index === 0)
                            <td rowspan="{{ count($day['items']) }}">
                                <strong>{{ $day['date']->translatedFormat('l') }}</strong><br>
                                {{ $day['date']->translatedFormat('d/m/Y') }}
                                @if($day['is_holiday'])
                                    <br><span class="status-libur">({{ $day['holiday_name'] }})</span>
                                @endif
                            </td>
                        @endif
                        
                        <td>Jam {{ $item['schedule']->classSession->session_name ?? '?' }}</td>
                        
                        <td>
                            <strong>{{ $item['schedule']->teacherAssignment->classSubject->classroomTerm->name ?? '-' }}</strong><br>
                            {{ $item['schedule']->teacherAssignment->classSubject->subject->name ?? '-' }}
                        </td>
                        
                        <td>{{ $item['schedule']->teacherAssignment->teacher->name ?? '-' }}</td>
                        
                        <td>
                            @if($item['status'] === 'TERISI')
                                <span class="status-terisi">Terisi</span>
                            @elseif($item['status'] === 'TERISI_TIDAK_TERJADWAL')
                                <span class="status-terisi">Terisi (Ekstra)</span>
                            @elseif($item['status'] === 'LIBUR')
                                <span class="status-libur">Libur</span>
                            @else
                                <span class="status-kosong">Kosong</span>
                            @endif
                        </td>
                        
                        <td>
                            @if(in_array($item['status'], ['TERISI', 'TERISI_TIDAK_TERJADWAL']) && $item['journal'])
                                <strong>Materi:</strong> {{ $item['journal']->material }}<br>
                                <strong>Absensi:</strong>
                                @if($item['journal']->absences->isEmpty())
                                    Hadir Semua
                                @else
                                    @foreach($item['journal']->absences as $abs)
                                        {{ $abs->classEnrollment->student->name }} ({{ $abs->status }}){{ !$loop->last ? ', ' : '' }}
                                    @endforeach
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data jadwal untuk periode ini berdasarkan filter yang dipilih.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature-area">
        <div class="signature-box">
            <p>Mengetahui,<br>Wali Kelas</p>
            <div class="name">{{ $teacher->name }}</div>
        </div>
    </div>

</body>
</html>
