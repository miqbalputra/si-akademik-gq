<!doctype html><html lang="id"><head><meta charset="utf-8"><style>
    @page { margin: 30px 32px 36px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.35; color: #1e293b; }
    h1 { font-size: 18px; color: #14532d; margin: 0 0 4px; } h2 { font-size: 14px; margin: 0 0 6px; }
    p { margin: 4px 0; } .meta { color: #64748b; font-size: 9px; } .class-page { page-break-before: always; } .first { page-break-before: auto; }
    .badge { font-weight: bold; color: #92400e; } .final { color: #166534; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 12px; }
    th, td { border: 1px solid #d7e3dc; padding: 6px; vertical-align: middle; word-wrap: break-word; }
    th { background: #14532d; color: white; text-align: center; } thead { display: table-header-group; }
    tr { page-break-inside: avoid; } tbody tr:nth-child(even) { background: #f3f8f5; }
    .numeric { text-align: right; } .rank { width: 7%; text-align: center; } .name { width: 23%; } .nis { width: 12%; } .state { width: 13%; }
    tbody tr.incomplete td { background: #fff7df; } .repeat-heading { background: #edf5ef; color: #14532d; text-align: left; font-size: 10px; }
    .total { font-weight: bold; } .footer { position: fixed; bottom: -21px; left: 0; right: 0; font-size: 8px; color: #64748b; } .page:after { content: counter(page); }
</style></head><body>
@php
    $number = fn ($value) => $value === null ? '—' : number_format((float)$value, 2, ',', '.');
@endphp
<div class="footer">Ruang GQ · {{ $report['term']->academicYear?->name }} · {{ $report['term']->name }}<span style="float:right">Halaman <span class="page"></span></span></div>
@foreach($report['classes'] as $class)
<section class="class-page {{ $loop->first ? 'first' : '' }}">
    <h1>PERINGKAT KELAS</h1><h2>{{ $class['name'] }}</h2>
    <p>{{ $report['term']->academicYear?->name }} · {{ $report['term']->name }} · {{ $class['scope_label'] }}</p>
    <p class="badge {{ in_array($class['status'], ['locked','published'],true) ? 'final' : '' }}">{{ !$class['snapshot_id'] ? 'LEGER BELUM DIBUAT' : (in_array($class['status'], ['locked','published'],true) ? 'FINAL' : 'DRAF — BELUM FINAL') }}</p>
    <p class="meta">Data leger per {{ $class['generated_at'] ?? '—' }} · {{ $class['rows']->count() }} santri · {{ $class['complete'] }} lengkap · {{ $class['incomplete'] }} belum lengkap</p>
    <table><colgroup><col style="width:7%"><col style="width:23%"><col style="width:12%"><col style="width:11%"><col style="width:11%"><col style="width:11%"><col style="width:11%"><col style="width:14%"></colgroup><thead><tr><th colspan="8" class="repeat-heading">{{ $class['name'] }} · {{ $report['term']->academicYear?->name }} · {{ $report['term']->name }} · {{ !$class['snapshot_id'] ? 'Leger belum dibuat' : (in_array($class['status'], ['locked','published'],true) ? 'FINAL' : 'DRAF') }}</th></tr><tr><th class="rank">Peringkat</th><th class="name">Nama santri</th><th class="nis">NIS</th><th>Total Diniyyah</th><th>Total Tahfidz</th><th>Total akhir</th><th>Rata-rata</th><th class="state">Kelengkapan</th></tr></thead><tbody>
        @forelse($class['rows'] as $row)<tr class="{{ !$row['complete'] ? 'incomplete' : '' }}"><td class="rank">{{ $row['rank'] ?? '—' }}</td><td>{{ $row['name'] }}{{ $row['archived'] ? ' (Arsip)' : '' }}</td><td>{{ $row['nis'] }}</td><td class="numeric">{{ $number($row['diniyyah_total']) }}</td><td class="numeric">{{ $number($row['tahfidz_total']) }}</td><td class="numeric total">{{ $number($row['total']) }}</td><td class="numeric">{{ $number($row['average']) }}</td><td>{{ $row['complete'] ? 'Lengkap' : 'Belum lengkap' }}</td></tr>
        @empty<tr><td colspan="8">{{ $class['snapshot_id'] ? 'Belum ada santri pada leger ini sesuai filter.' : 'Leger belum dibuat oleh wali kelas.' }}</td></tr>@endforelse
    </tbody></table>
    <p class="meta">Peringkat dan total akhir mengikuti leger tersimpan, tidak dihitung ulang. — = belum tersedia. Total Tahfidz mencakup UAS, Sabaq, Sabqi, dan Manzil; juz dan presensi tidak dijumlahkan.</p>
    <p class="meta">{{ $report['include_archived'] ? 'Termasuk santri arsip.' : 'Santri aktif saja; nomor peringkat tersimpan dapat memiliki jeda.' }} Dicetak {{ now()->format('d/m/Y H:i') }}.</p>
</section>
@endforeach
</body></html>
