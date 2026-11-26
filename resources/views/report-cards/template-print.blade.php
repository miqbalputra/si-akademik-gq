<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rapor {{ $pages[0]['student_name'] }}</title>
    <style>
        @page { size: {{ $pages[0]['paper']['width_mm'] }}mm {{ $pages[0]['paper']['height_mm'] }}mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; font-family: Arial, sans-serif; }
        .toolbar { padding: 16px; background: white; }
        .toolbar button { padding: 10px 20px; cursor: pointer; }
        .page { width: {{ $pages[0]['paper']['width_mm'] }}mm; height: {{ $pages[0]['paper']['height_mm'] }}mm; padding: {{ $pages[0]['paper']['top_mm'] }}mm {{ $pages[0]['paper']['margin_mm'] }}mm {{ $pages[0]['paper']['bottom_mm'] }}mm; background: white; margin: 16px auto; break-after: page; overflow: hidden; }
        .page:last-child { break-after: auto; }
        .canvas-frame { position: relative; margin: 0 auto; }
        .canvas { position: relative; transform-origin: top left; }
        .cell { position: absolute; display: flex; padding: 0 2px; line-height: 1.12; overflow: visible; }
        .cell.advice { line-height: 16px; }
        .cell.issue-date { padding-right: 0; }
        .cell > span { max-width: 100%; overflow-wrap: anywhere; }
        .picture { position: absolute; }
        @media print { body { background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; } .toolbar { display: none; } .page { margin: 0; } }
    </style>
</head>
<body>
    @unless($isPdf ?? false)
        <div class="toolbar">
            @php
                $missing = collect($pages)->flatMap(fn ($page) => $page['missing'])->unique();
            @endphp
            @if($missing->isNotEmpty())<p role="alert">Belum siap cetak: {{ $missing->join(', ') }}. Lengkapi di halaman rapor.</p>@endif
            <button onclick="window.print()" @disabled($missing->isNotEmpty())>Cetak / Simpan PDF</button>
            <span>F4 / Folio 8,5 × 13 inci (216 × 330 mm) · skala 100% · tanpa header/footer browser</span>
        </div>
    @endunless
    @foreach($pages as $page)
    <section class="page" aria-label="{{ $page['sheet'] }} — {{ $page['student_name'] }}">
        <div class="canvas-frame" style="width:{{ $page['width'] * $page['scale'] }}px;height:{{ $page['height'] * $page['scale'] }}px">
        <div class="canvas" style="width:{{ $page['width'] }}px;height:{{ $page['height'] }}px;transform:scale({{ $page['scale'] }})">
            @foreach($page['cells'] as $cell)
                @php
                    $justify = match($cell['align']) { 'center' => 'center', 'right' => 'flex-end', default => 'flex-start' };
                    $align = match($cell['vertical']) { 'center' => 'center', 'top' => 'flex-start', default => 'flex-end' };
                @endphp
                <div @class(['cell', 'advice' => $cell['is_advice'] ?? false, 'issue-date' => $cell['is_issue_date'] ?? false]) style="left:{{ $cell['x'] }}px;top:{{ $cell['y'] }}px;width:{{ $cell['width'] }}px;height:{{ $cell['height'] }}px;font-family:'{{ $cell['font'] }}',Carlito,Arial,'Noto Sans Arabic',sans-serif;font-size:{{ $cell['size'] }}pt;font-weight:{{ $cell['bold'] ? 700 : 400 }};font-style:{{ $cell['italic'] ? 'italic' : 'normal' }};color:#{{ $cell['color'] }};justify-content:{{ $justify }};align-items:{{ $align }};text-align:{{ $cell['align'] === 'general' ? 'left' : $cell['align'] }};white-space:{{ $cell['wrap'] ? 'pre-line' : 'nowrap' }};{{ $cell['fill'] ? 'background:#'.$cell['fill'].';' : '' }}@foreach($cell['borders'] as $side => $thickness){{ $thickness ? 'border-'.$side.':'.$thickness.'px '.($thickness === 3 ? 'double' : 'solid').' #000;' : '' }}@endforeach"><span>{{ $cell['text'] }}</span></div>
            @endforeach
            @foreach($page['images'] as $img)
                <img class="picture" alt="" src="{{ $img['src'] }}" style="left:{{ $img['x'] }}px;top:{{ $img['y'] }}px;width:{{ $img['width'] }}px;height:{{ $img['height'] }}px;object-fit:contain">
            @endforeach
        </div>
        </div>
    </section>
    @endforeach
</body>
</html>
