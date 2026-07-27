<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="application/vnd.ms-excel; charset=UTF-8">
    <title>Rekap Jurnal Diniyyah Lengkap</title>
</head>
<body>

    <table>
        <tr>
            <th colspan="15" style="font-size: 16px; font-weight: bold; text-align: center;">REKAP JURNAL KBM DINIYYAH ( LENGKAP )</th>
        </tr>
        <tr>
            <td colspan="15" style="font-size: 11px; text-align: center;">Dicetak: {{ now()->translatedFormat('d F Y, H:i') }}</td>
        </tr>
        @if($filters['date_from'] || $filters['date_until'])
        <tr>
            <td colspan="15" style="font-size: 11px; text-align: center;">
                Periode: {{ $filters['date_from'] ?? 'awal' }} s/d {{ $filters['date_until'] ?? 'akhir' }}
            </td>
        </tr>
        @endif
        <tr>
            <td colspan="15"></td>
        </tr>
    </table>

    <table border="1">
        <thead>
            <tr style="background-color: #f0f0f0; font-weight: bold; text-align: center;">
                <th>No</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Kelas</th>
                <th>Mapel</th>
                <th>Guru Asli</th>
                <th>Pengganti</th>
                <th>Guru Mengajar (untuk gaji)</th>
                <th>Materi</th>
                <th>JP</th>
                <th>Hadir</th>
                <th>Sakit</th>
                <th>Izin</th>
                <th>Alpa</th>
                <th>Bolos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                @php
                    $j = $row['journal'];
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $i + 1 }}</td>
                    <td style="white-space: nowrap;">{{ $j->date?->format('Y-m-d') }}</td>
                    <td style="text-align: center;">{{ $j->session_hour }}</td>
                    <td>{{ $row['kelas'] }}</td>
                    <td>{{ $row['mapel'] }}</td>
                    <td>{{ $row['guru_asli'] }}</td>
                    <td>{{ $row['pengganti'] ?? '-' }}</td>
                    <td style="font-weight: bold;">{{ $row['guru_mengajar'] }}</td>
                    <td>{{ $j->material }}</td>
                    <td style="text-align: center;">{{ $j->jp_count }}</td>
                    <td style="text-align: center;">{{ $row['hadir'] }}</td>
                    <td style="text-align: center;">{{ $row['sakit'] }}</td>
                    <td style="text-align: center;">{{ $row['izin'] }}</td>
                    <td style="text-align: center;">{{ $row['alpa'] }}</td>
                    <td style="text-align: center;">{{ $row['bolos'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" style="text-align: center; padding: 20px;">Tidak ada data jurnal untuk filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table>
        <tr><td colspan="15"></td></tr>
        <tr>
            <td colspan="15" style="font-size: 10px; color: #555;">
                Catatan: kolom <strong>"Guru Mengajar (untuk gaji)"</strong> otomatis = Guru Pengganti jika jurnal diisi sebagai pengganti; jika tidak, = Guru Asli. Gunakan kolom ini untuk penghitungan gaji guru.
            </td>
        </tr>
    </table>

</body>
</html>