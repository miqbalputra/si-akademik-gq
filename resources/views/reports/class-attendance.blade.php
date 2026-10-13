<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Presensi Kelas</title>
    <style>
        @page { size:A4 landscape; margin:10mm 10mm 13mm; }
        body { margin:0; font-family:DejaVu Sans,sans-serif; color:#172033; font-size:9.5pt; line-height:1.35; }
        h1,h2,p { margin:0; }
        h1 { font-size:18pt; line-height:1.3; }
        h2 { font-size:13pt; margin-bottom:5pt; }
        .eyebrow { color:#2563eb; font-size:9pt; font-weight:bold; margin-bottom:4pt; }
        .subtitle,.muted { color:#596579; }
        .subtitle { margin-top:4pt; font-size:9pt; }
        .header { padding-bottom:9pt; border-bottom:2pt solid #334155; margin-bottom:9pt; }
        .page-break { page-break-before:always; }
        .meta { width:100%; border-collapse:collapse; margin-bottom:8pt; }
        .meta td { padding:3pt 6pt 3pt 0; vertical-align:top; }
        .meta .label { width:65pt; font-weight:bold; color:#596579; }
        .calendar { margin-bottom:10pt; padding:7pt 9pt; background:#f1f5f9; border:.5pt solid #cbd5e1; }
        .calendar p { font-size:9pt; margin-bottom:2pt; }
        .data { width:100%; border-collapse:collapse; table-layout:auto; }
        .data thead { display:table-header-group; }
        .data tr { page-break-inside:avoid; }
        .data th,.data td { border:.5pt solid #cbd5e1; padding:3pt 4pt; vertical-align:middle; word-wrap:break-word; }
        .data th { background:#e8eef7; font-size:8.5pt; font-weight:bold; text-align:center; }
        .data td { font-size:9pt; line-height:1.2; }
        .data .caption { background:#1e293b; color:#fff; text-align:left; font-size:9.5pt; padding:7pt; }
        .center { text-align:center; }
        .holiday { background:#f1f5f9; color:#64748b; }
        .assumed { background:#eff6ff; }
        .absent { background:#fee2e2; }
        .status { color:#64748b; font-size:8pt; }
        .notes { margin-top:7pt; margin-bottom:8pt; font-size:8.5pt; line-height:1.25; color:#475569; }
        .notes p { margin-bottom:4pt; }
    </style>
</head>
<body>
    @foreach($report['classes'] as $classIndex => $class)
        @if($includeSummary ?? true)
        <section @class(['page-break' => $classIndex > 0])>
            <div class="header">
                <p class="eyebrow">{{ $class['school'] }} · Ruang GQ</p>
                <h1>Laporan Presensi Kelas</h1>
                <p class="subtitle">{{ $class['name'] }} · {{ $report['period']['label'] }} · Diterbitkan {{ $report['generated_at'] }}</p>
            </div>
            <table class="meta">
                <tr><td class="label">Kelas</td><td>{{ $class['name'] }}</td><td class="label">Semester</td><td>{{ $class['academic_year'] }} · {{ $class['semester'] }}</td></tr>
                <tr><td class="label">Wali kelas</td><td>{{ $class['homeroom'] }}</td><td class="label">Periode</td><td>{{ $report['period']['start'] }} s.d. {{ $report['period']['end'] }}</td></tr>
            </table>
            <div class="calendar">
                <p><strong>Hari efektif dalam pilihan: {{ $class['effective_days'] }}</strong> · Sudah berlangsung: {{ $class['elapsed_days'] }} · Batas perhitungan: {{ $report['period']['as_of'] }} WIB.</p>
                @foreach($class['months'] as $month)
                    <p>{{ $month['label'] }}: {{ $month['full_effective_days'] }} hari efektif sebulan; {{ $month['selected_effective_days'] }} dalam pilihan; {{ $month['elapsed_effective_days'] }} sudah berlangsung.</p>
                @endforeach
                <p>Hari efektif: Senin–Jumat dalam semester, dikurangi Libur Sekolah. Agenda Tanpa KBM tetap dihitung.</p>
            </div>
            @if($showReportNotes ?? true)
                <div class="notes">
                    <p>{{ $report['formula'] }}</p>
                    <p>{{ $report['legend'] }}</p>
                    <p>Jumlah L pada rekap adalah libur khusus yang tersimpan pada hari efektif. Akhir pekan dan Libur Sekolah sudah dikeluarkan melalui kalender.</p>
                    <p>{{ $report['assumption'] }}</p>
                </div>
            @endif
            <table class="data">
                <colgroup>@foreach([3,8,23,10,8,6,5,5,5,5,6,7,9] as $width)<col style="width:{{ $width }}%;">@endforeach</colgroup>
                <thead>
                    <tr><th colspan="13" class="caption">Rekap santri · {{ $class['name'] }} · {{ $report['period']['label'] }}</th></tr>
                    <tr>
                        @foreach(['No.','NIS','Nama Santri','Status','Tanggal Keluar','Hari Dihitung','Hadir H','Sakit S','Izin I','Alpa A','Libur Khusus L','Hadir Bawaan H*','Kehadiran'] as $index => $label)
                            <th style="width:{{ [3,8,23,10,8,6,5,5,5,5,6,7,9][$index] }}%;">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($class['students'] as $student)
                        <tr>
                            <td class="center">{{ $student['number'] }}</td><td>{{ $student['nis'] ?: '—' }}</td><td>{{ $student['name'] }}</td>
                            <td class="status">{{ $student['status_label'] }}</td><td class="center">{{ $student['departure_date'] ? \Carbon\CarbonImmutable::parse($student['departure_date'])->format('d/m/Y') : '—' }}</td>
                            <td class="center">{{ $student['eligible_days'] }}</td>
                            @foreach(['H','S','I','A','L'] as $code)<td class="center">{{ $student['counts'][$code] }}</td>@endforeach
                            <td class="center">{{ $student['assumed_present'] }}</td><td class="center">{{ $student['percentage_label'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="center">Tidak ada santri pada kelas/periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @endif
        @if($includeDetails ?? true)
        @php
            $detailRendered = 0;
        @endphp
        @foreach($class['months'] as $month)
            @continue(isset($onlyMonth) && $onlyMonth !== $month['key'])
            @php
                $selectedDays = $onlyDays ?? array_values(array_filter($month['days'], fn ($day) => $day['selected']));
                $chunkSize = $selectedDays ? (int) ceil(count($selectedDays) / ceil(count($selectedDays) / 15)) : 15;
            @endphp
            @foreach(array_chunk($selectedDays, $chunkSize) as $days)
                <section @class(['page-break' => ($includeSummary ?? true) || $detailRendered > 0])>
                    @php($detailRendered++)
                    <div class="header">
                        <p class="eyebrow">{{ $class['school'] }} · Ruang GQ</p>
                        <h2>Rincian Harian · {{ $class['name'] }}</h2>
                        <p class="subtitle">{{ $month['label'] }} · {{ $days[0]['date'] }} s.d. {{ $days[count($days)-1]['date'] }} · {{ $class['academic_year'] }} · {{ $class['semester'] }}</p>
                    </div>
                    <table class="data">
                        <colgroup><col style="width:4%;"><col style="width:12%;"><col style="width:34%;">@foreach($days as $day)<col style="width:{{ 50/count($days) }}%;">@endforeach</colgroup>
                        <thead>
                            <tr><th colspan="{{ count($days)+3 }}" class="caption">{{ $class['name'] }} · {{ $month['label'] }} · Periode laporan {{ $report['period']['label'] }}</th></tr>
                            <tr><th style="width:4%;">No.</th><th style="width:12%;">NIS</th><th style="width:34%;">Nama Santri</th>@foreach($days as $day)<th style="width:{{ 50/count($days) }}%;" @class(['holiday' => !$day['effective']])>{{ $day['number'] }}<br>{{ $day['weekday'] }}</th>@endforeach</tr>
                        </thead>
                        <tbody>
                            @forelse($class['students'] as $student)
                                <tr><td class="center">{{ $student['number'] }}</td><td>{{ $student['nis'] ?: '—' }}</td><td>{{ $student['name'] }}@if($student['archived'])<br><span class="status">Arsip / tidak aktif</span>@endif</td>
                                    @foreach($days as $day)
                                        @php($code = $student['daily'][$day['date']])
                                        <td @class(['center', 'holiday' => $code === 'L' || $code === '—', 'assumed' => $code === 'H*', 'absent' => $code === 'A'])>{{ $code }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($days)+3 }}" class="center">Tidak ada santri pada kelas/periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="notes"><p>{{ $report['legend'] }}</p><p>{{ $report['formula'] }}</p></div>
                </section>
            @endforeach
        @endforeach
        @endif
    @endforeach
</body>
</html>
