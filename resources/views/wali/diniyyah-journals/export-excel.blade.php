<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rekap Jurnal Diniyyah</title>
</head>
<body>

    <table>
        <tr>
            <th colspan="6" style="font-size: 16px; font-weight: bold; text-align: center;">REKAPITULASI JURNAL MENGAJAR DINIYYAH</th>
        </tr>
        <tr>
            <th colspan="6" style="font-size: 12px; text-align: center;">Bulan {{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }} {{ $year }}</th>
        </tr>
        <tr>
            <td colspan="6"></td>
        </tr>
        <tr>
            <td colspan="2"><strong>Wali Kelas:</strong></td>
            <td colspan="4">{{ $teacher->name }}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Dicetak Pada:</strong></td>
            <td colspan="4">{{ now()->translatedFormat('d F Y, H:i') }}</td>
        </tr>
        <tr>
            <td colspan="6"></td>
        </tr>
    </table>

    <table border="1">
        <thead>
            <tr>
                <th style="background-color: #f0f0f0; font-weight: bold; text-align: center;">Hari/Tanggal</th>
                <th style="background-color: #f0f0f0; font-weight: bold; text-align: center;">Jam Sesi</th>
                <th style="background-color: #f0f0f0; font-weight: bold; text-align: center;">Kelas & Mapel</th>
                <th style="background-color: #f0f0f0; font-weight: bold; text-align: center;">Guru Pengajar</th>
                <th style="background-color: #f0f0f0; font-weight: bold; text-align: center;">Status</th>
                <th style="background-color: #f0f0f0; font-weight: bold; text-align: center;">Materi & Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @forelse($monitoringData as $day)
                @foreach($day['items'] as $index => $item)
                    <tr>
                        @if($index === 0)
                            <td rowspan="{{ count($day['items']) }}" style="vertical-align: top;">
                                <strong>{{ $day['date']->translatedFormat('l') }}</strong><br>
                                {{ $day['date']->translatedFormat('d/m/Y') }}
                                @if($day['is_holiday'])
                                    <br>({{ $day['holiday_name'] }})
                                @endif
                            </td>
                        @endif
                        
                        <td style="vertical-align: top;">Jam {{ $item['schedule']->classSession->session_name ?? '?' }}</td>
                        
                        <td style="vertical-align: top;">
                            <strong>{{ $item['schedule']->teacherAssignment->classSubject->classroomTerm->name ?? '-' }}</strong><br>
                            {{ $item['schedule']->teacherAssignment->classSubject->subject->name ?? '-' }}
                        </td>
                        
                        <td style="vertical-align: top;">{{ $item['schedule']->teacherAssignment->teacher->name ?? '-' }}</td>
                        
                        <td style="vertical-align: top;">
                            @if($item['status'] === 'TERISI')
                                Terisi
                            @elseif($item['status'] === 'TERISI_TIDAK_TERJADWAL')
                                Terisi (Ekstra)
                            @elseif($item['status'] === 'LIBUR')
                                Libur
                            @else
                                Kosong
                            @endif
                        </td>
                        
                        <td style="vertical-align: top;">
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

    <table>
        <tr>
            <td colspan="6"></td>
        </tr>
        <tr>
            <td colspan="6"></td>
        </tr>
        <tr>
            <td colspan="4"></td>
            <td colspan="2" style="text-align: center;">Mengetahui,</td>
        </tr>
        <tr>
            <td colspan="4"></td>
            <td colspan="2" style="text-align: center;">Wali Kelas</td>
        </tr>
        <tr>
            <td colspan="6"></td>
        </tr>
        <tr>
            <td colspan="6"></td>
        </tr>
        <tr>
            <td colspan="6"></td>
        </tr>
        <tr>
            <td colspan="4"></td>
            <td colspan="2" style="text-align: center; font-weight: bold; text-decoration: underline;">{{ $teacher->name }}</td>
        </tr>
    </table>

</body>
</html>
