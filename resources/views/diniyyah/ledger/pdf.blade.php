<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><style>
@page { margin: 20px; } body { font-family: DejaVu Sans,sans-serif; font-size: 8px; color:#1e293b; } h1 { font-size: 16px; margin:0 0 5px; } p { margin:5px 0 12px; } table { width:100%; border-collapse:collapse; table-layout:fixed; } th,td { border:1px solid #cbd5e1; padding:5px 3px; text-align:center; overflow-wrap:anywhere; } th { background:#dcfce7; } thead { display:table-header-group; } tr { page-break-inside:avoid; } .name { width:130px; text-align:left; } .small { width:25px; } .nis { width:70px; } .warning { color:#9a3412; }
</style></head><body>
@php($combined = ($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1')
<h1>LEGER NILAI {{ $combined ? 'DINIYYAH & TAHFIDZ' : 'DINIYYAH' }}</h1>
<p>{{ $snapshot->classroomTerm?->name }} · {{ $snapshot->academicTerm?->academicYear?->name }} {{ $snapshot->academicTerm?->name }} · Status: {{ \App\Support\UiLabel::statusLabel($snapshot->status) }} · {{ $snapshot->generated_at?->format('d-m-Y H:i') }}</p>
@if(!in_array($snapshot->status,['locked','published'],true))<p class="warning">DRAF — data belum menjadi leger final.</p>@endif
<table><thead><tr><th class="small">No</th><th class="name">Nama</th><th class="nis">NIS</th>@foreach($columns as $column)<th>{{ $column['label'] }}</th>@endforeach<th>Total</th><th>Rata-rata</th><th>Peringkat</th></tr></thead><tbody>
@foreach($snapshot->rows->sortBy('row_number') as $row)
@php($cells = $row->cells->keyBy('column_key'))
<tr><td>{{ $row->row_number }}</td><td class="name">{{ $row->student_name }}</td><td>{{ $row->student_nis }}</td>@foreach($columns as $column)@php($cell = $cells->get($column['key']))<td>{{ ($column['numeric'] ?? true) ? ($cell?->value_numeric ?? '—') : ($cell?->value_text ?? '—') }}</td>@endforeach<td>{{ $combined ? ($cells->get('combined_total')?->value_numeric ?? '—') : ($row->total_diniyyah_score ?? '—') }}</td><td>{{ $combined ? ($cells->get('combined_average')?->value_numeric ?? '—') : ($row->average_diniyyah_score ?? '—') }}</td><td>{{ $combined ? ($cells->get('combined_rank')?->value_numeric ?? '—') : ($row->rank_in_class ?? '—') }}</td></tr>
@endforeach</tbody></table>
<p>Juz dan rekap presensi tidak masuk penjumlahan nilai. Nilai yang belum lengkap ditampilkan sebagai —.</p>
</body></html>
