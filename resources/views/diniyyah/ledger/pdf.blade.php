<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><style>
    @page { margin:22px 24px 30px; }
    body { font-family:DejaVu Sans,sans-serif; font-size:9px; line-height:1.3; color:#1e293b; }
    h1 { font-size:17px; margin:0 0 5px; color:#14532d; } p { margin:3px 0; }
    .meta { color:#475569; font-size:9px; } .state { margin:9px 0 12px; font-size:9px; }
    .section { page-break-before:always; } .section.first { page-break-before:auto; }
    h2 { font-size:11px; margin:12px 0 7px; } table { width:100%; border-collapse:collapse; table-layout:fixed; }
    th,td { border:1px solid #d5dfda; padding:5px 4px; text-align:center; vertical-align:middle; overflow-wrap:break-word; }
    th { font-weight:bold; } .label { background:#f8faf9; font-size:8.5px; } .kkm { background:#f8faf9; color:#64748b; font-size:8px; padding:3px; }
    thead { display:table-header-group; } tbody tr { page-break-inside:avoid; } tbody tr:nth-child(even) { background:#f7faf8; }
    .name { width:22%; text-align:left; } .no { width:3%; } .nis { width:9%; font-size:8px; } .missing { color:#a16207; background:#fffbeb; } .below { color:#9a3412; background:#fff3e5; font-weight:bold; }
    .result { font-weight:bold; background:#edf6f0; } .material { display:block; margin-top:3px; font-size:8px; font-weight:normal; color:#64748b; }
    .note { margin-top:9px; color:#64748b; font-size:8px; } .footer { position:fixed; bottom:-17px; left:0; right:0; color:#64748b; font-size:8px; }
    .page:after { content:counter(page); }
</style></head>
<body>
<div class="footer">{{ $snapshot->classroomTerm?->name }} · {{ $snapshot->academicTerm?->academicYear?->name }} {{ $snapshot->academicTerm?->name }} <span style="float:right">Halaman <span class="page"></span></span></div>
@foreach($ledger['panels'] as $panel)
    @php
        $panelColumns = collect($panel)->flatMap(fn($group) => $group['columns'])->values();
    @endphp
    <section class="section {{ $loop->first ? 'first' : '' }}">
        <h1>LEGER NILAI {{ $ledger['combined'] ? 'DINIYYAH & TAHFIDZ' : 'DINIYYAH' }}</h1>
        <p class="meta">{{ $snapshot->classroomTerm?->name }} · {{ $snapshot->academicTerm?->academicYear?->name }} {{ $snapshot->academicTerm?->name }}</p>
        <p class="state"><strong>{{ in_array($snapshot->status,['locked','published'],true) ? 'FINAL' : 'DRAF — belum menjadi leger final' }}</strong> · Data per {{ $snapshot->generated_at?->format('d-m-Y H:i') ?? '—' }} · Bagian {{ $loop->iteration }}/{{ $ledger['panels']->count() }}</p>
        <h2>{{ collect($panel)->pluck('label')->implode(' / ') }}</h2>
        <table>
            <thead>
                <tr><th rowspan="3" class="no label">No</th><th rowspan="3" class="name label">Nama santri</th><th rowspan="3" class="nis label">NIS</th>
                    @foreach($panel as $group)<th colspan="{{ count($group['columns']) }}" style="background:#{{ $group['background'] }};color:#{{ $group['color'] }}">{{ $group['label'] }}</th>@endforeach
                </tr>
                <tr>@foreach($panelColumns as $column)<th class="label">{{ $column['display_label'] }}@if($column['tested_material'] && $column['tested_material'] !== '-')<span class="material">{{ $column['tested_material'] }}</span>@endif</th>@endforeach</tr>
                <tr>@foreach($panelColumns as $column)<th class="kkm">@if($column['kkm'] !== null)KKM {{ (float)$column['kkm'] }}@elseif(($column['source_type'] ?? '') === 'diniyyah_assessment_set')Menunggu ACC@else—@endif</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse($ledger['rows'] as $row)
                    <tr><td>{{ $row['number'] }}</td><td class="name">{{ $row['name'] }}</td><td class="nis">{{ $row['nis'] }}</td>
                        @foreach($panelColumns as $column)
                            @php
                                $value = $row['values'][$column['key']];
                            @endphp
                            <td class="{{ $column['group'] === 'summary' ? 'result' : '' }} {{ $value['missing'] ? 'missing' : ($value['below'] ? 'below' : '') }}">{{ $value['display'] }}</td>
                        @endforeach
                    </tr>
                @empty<tr><td colspan="{{ $panelColumns->count()+3 }}">Belum ada data santri.</td></tr>@endforelse
            </tbody>
        </table>
        <p class="note">— = nilai belum ada. Latar jingga = nilai di bawah KKM. Identitas santri diulang di setiap bagian agar mudah dicocokkan. Juz dan presensi tidak masuk total nilai.</p>
    </section>
@endforeach
</body></html>
