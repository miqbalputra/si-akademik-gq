<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Rapor {{ $reportCard->student?->name }}</title>
    <style>
        @page {
            size: A4;
            margin: 14mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e5e7eb;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.35;
        }

        .toolbar {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 16px;
        }

        .toolbar a,
        .toolbar button {
            border: 0;
            border-radius: 8px;
            background: #d97706;
            color: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            padding: 10px 14px;
            text-decoration: none;
        }

        .toolbar a {
            background: #475569;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 18px;
            background: white;
            padding: 14mm 12mm;
            box-shadow: 0 10px 30px rgb(15 23 42 / 0.18);
        }

        .school-header {
            border-bottom: 2px solid #111827;
            padding-bottom: 12px;
            text-align: center;
        }

        .school-header .eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .school-header h1 {
            font-size: 22px;
            margin: 5px 0 2px;
        }

        .school-header h2 {
            font-size: 16px;
            margin: 0;
        }

        .meta-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: 1fr 1fr;
            margin-top: 16px;
        }

        dl {
            display: grid;
            grid-template-columns: 94px 1fr;
            margin: 0;
            row-gap: 5px;
        }

        dt {
            color: #4b5563;
        }

        dd {
            font-weight: 700;
            margin: 0;
        }

        table {
            border-collapse: collapse;
            margin-top: 16px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #111827;
            padding: 7px 8px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-size: 11px;
            text-align: center;
            text-transform: uppercase;
        }

        .center {
            text-align: center;
        }

        .subject {
            font-weight: 700;
        }

        .material {
            color: #4b5563;
            font-size: 11px;
            margin-top: 2px;
        }

        .summary-grid,
        .notes-grid,
        .signature-grid {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }

        .summary-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .notes-grid {
            grid-template-columns: 0.85fr 1.15fr;
        }

        .box {
            border: 1px solid #111827;
            padding: 10px;
        }

        .box-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .box-value {
            font-size: 18px;
            font-weight: 800;
            margin-top: 4px;
            text-align: center;
        }

        .attendance {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            margin-top: 8px;
            text-align: center;
        }

        .attendance div {
            border-left: 1px solid #d1d5db;
        }

        .attendance div:first-child {
            border-left: 0;
        }

        .note {
            min-height: 58px;
            margin-top: 8px;
        }

        .signature-grid {
            grid-template-columns: repeat(3, 1fr);
            text-align: center;
        }

        .signature-space {
            height: 72px;
        }

        .signature-name {
            border-top: 1px solid #111827;
            display: inline-block;
            font-weight: 700;
            min-width: 130px;
            padding-top: 4px;
        }

        .footer-note {
            color: #6b7280;
            font-size: 10px;
            margin-top: 18px;
            text-align: center;
        }

        @media print {
            body {
                background: white;
            }

            .toolbar {
                display: none;
            }

            .page {
                box-shadow: none;
                margin: 0;
                min-height: auto;
                padding: 0;
                width: auto;
            }

            a {
                color: inherit;
                text-decoration: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('report-cards.show', $reportCard) }}">Kembali</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="page">
        <header class="school-header">
            <div class="eyebrow">Rapor Hasil Belajar Diniyyah</div>
            <h1>Griya Quran</h1>
            <h2>{{ strtoupper($reportCard->academicTerm?->name ?? '-') }} - {{ $reportCard->academicTerm?->academicYear?->name ?? '-' }}</h2>
        </header>

        <section class="meta-grid">
            <dl>
                <dt>Nama</dt>
                <dd>{{ $reportCard->student?->name }}</dd>
                <dt>NIS</dt>
                <dd>{{ $reportCard->student?->nis }}</dd>
                <dt>Kelas</dt>
                <dd>{{ $reportCard->classroomTerm?->name }}</dd>
            </dl>
            <dl>
                <dt>Jenis Rapor</dt>
                <dd>{{ strtoupper($reportCard->report_type) }}</dd>
                <dt>Status</dt>
                <dd>{{ strtoupper($reportCard->status) }}</dd>
                <dt>Tanggal</dt>
                <dd>{{ $reportCard->issue_date?->format('d M Y') ?? $reportCard->published_at?->format('d M Y') ?? '-' }}</dd>
            </dl>
        </section>

        <table>
            <thead>
                <tr>
                    <th style="width: 34px;">No</th>
                    <th>Mata Pelajaran</th>
                    <th style="width: 54px;">KKM</th>
                    <th style="width: 58px;">Nilai</th>
                    <th style="width: 150px;">Terbilang</th>
                    <th style="width: 86px;">Predikat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reportCard->lines->sortBy('sort_order') as $line)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>
                            <div class="subject">{{ $line->subject_name }}</div>
                            @if ($line->tested_material)
                                <div class="material">{{ $line->tested_material }}</div>
                            @endif
                        </td>
                        <td class="center">{{ $line->kkm ?? '-' }}</td>
                        <td class="center"><strong>{{ $line->score_numeric ?? '-' }}</strong></td>
                        <td>{{ $line->score_words ?? '-' }}</td>
                        <td class="center">{{ $line->score_letter ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="summary-grid">
            <div class="box">
                <div class="box-title">Total Nilai</div>
                <div class="box-value">{{ $reportCard->total_score ?? '-' }}</div>
            </div>
            <div class="box">
                <div class="box-title">Rata-rata</div>
                <div class="box-value">{{ $reportCard->average_score ?? '-' }}</div>
            </div>
            <div class="box">
                <div class="box-title">Peringkat</div>
                <div class="box-value">{{ $reportCard->rank_in_class ?? '-' }}</div>
            </div>
        </section>

        <section class="notes-grid">
            <div class="box">
                <div class="box-title">Ketidakhadiran</div>
                <div class="attendance">
                    <div>
                        <strong>{{ $reportCard->attendance?->sick_count ?? 0 }}</strong>
                        <br>Sakit
                    </div>
                    <div>
                        <strong>{{ $reportCard->attendance?->permission_count ?? 0 }}</strong>
                        <br>Izin
                    </div>
                    <div>
                        <strong>{{ $reportCard->attendance?->absent_count ?? 0 }}</strong>
                        <br>Alpa
                    </div>
                </div>
            </div>
            <div class="box">
                <div class="box-title">Catatan Wali Kelas</div>
                <div class="note">{{ $reportCard->homeroom_note ?: '-' }}</div>
            </div>
        </section>

        <section class="signature-grid">
            @forelse ($reportCard->signatures->sortBy('sort_order') as $signature)
                <div>
                    <p>{{ $signature->role_label }}</p>
                    <div class="signature-space"></div>
                    <span class="signature-name">{{ $signature->person_name ?? $signature->teacher?->name ?? '-' }}</span>
                </div>
            @empty
                <div>
                    <p>Wali Kelas</p>
                    <div class="signature-space"></div>
                    <span class="signature-name">-</span>
                </div>
                <div>
                    <p>Kepala Bagian Diniyyah</p>
                    <div class="signature-space"></div>
                    <span class="signature-name">-</span>
                </div>
                <div>
                    <p>Kepala Sekolah</p>
                    <div class="signature-space"></div>
                    <span class="signature-name">-</span>
                </div>
            @endforelse
        </section>

        <p class="footer-note">Dokumen ini dicetak dari Sistem Nilai Sekolah.</p>
    </main>
</body>
</html>
