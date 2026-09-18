<!doctype html>
<html lang="id"><head><meta charset="utf-8"><style>
@page{margin:12mm}body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:9px}h1{font-size:17px;margin:0;color:#065f46}h2{font-size:12px;margin:0 0 5px}.meta{color:#475569;margin:6px 0 12px}.summary{background:#fef2f2;border:1px solid #fecaca;padding:9px;margin:10px 0;font-size:10px}.warning{background:#fffbeb;border:1px solid #fde68a;padding:8px;margin:8px 0;color:#92400e}table{width:100%;border-collapse:collapse;margin-top:7px}th,td{border:1px solid #cbd5e1;padding:5px;text-align:left;vertical-align:top}th{background:#0f766e;color:white;font-size:8px}.teacher{page-break-inside:avoid;margin:14px 0}.count{color:#b91c1c;font-weight:bold}.empty{padding:14px;border:1px solid #a7f3d0;background:#ecfdf5;color:#065f46;font-weight:bold}.footer{margin-top:12px;color:#64748b;font-size:8px}
</style></head><body>
<h1>PENGINGAT PENGISIAN JURNAL KBM</h1>
<p class="meta"><strong>Periode ajaran:</strong> {{ $report['term']->academicYear?->name }} - {{ $report['term']->name }}<br><strong>Rentang:</strong> {{ $report['start']->translatedFormat('d F Y') }} s.d. {{ $report['end']->translatedFormat('d F Y') }}<br><strong>Dibuat:</strong> {{ $report['generated_at']->translatedFormat('d F Y, H:i') }} WIB</p>
<div class="summary"><strong>{{ $report['stats']['teachers_to_remind'] }}</strong> guru perlu diingatkan · <strong>{{ $report['stats']['total_missing'] }}</strong> jurnal KBM belum diisi.</div>
@if ($report['stats']['attendance_unverified_teachers'] > 0)<div class="warning">Pengecualian izin/sakit belum dapat diverifikasi untuk {{ $report['stats']['attendance_unverified_teachers'] }} guru. Pastikan statusnya sebelum mengirim pengingat.</div>@endif
@forelse ($report['teachers'] as $teacher)
<section class="teacher"><h2>{{ $teacher['teacher_name'] }} <span class="count">· {{ $teacher['missing_count'] }} jurnal kosong</span></h2><div class="meta">NIY: {{ $teacher['niy'] ?: '-' }}</div><table><thead><tr><th>Tanggal</th><th>Sesi / Jam</th><th>Kelas</th><th>Mapel</th></tr></thead><tbody>@foreach ($teacher['rows'] as $row)<tr><td>{{ $row['date_label'] }}</td><td>{{ $row['session'] }}<br>{{ $row['session_time'] }}</td><td>{{ implode(', ', $row['classes']) }}</td><td>{{ implode(', ', $row['subjects']) }}</td></tr>@endforeach</tbody></table></section>
@empty
<div class="empty">Semua jurnal pada rentang ini sudah lengkap.</div>
@endforelse
<p class="footer">Laporan ini bersumber dari jadwal mengajar. Libur, agenda tanpa KBM, serta izin dan sakit yang terverifikasi tidak dihitung sebagai jurnal kosong.</p>
</body></html>
