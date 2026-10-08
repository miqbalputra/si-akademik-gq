@php /** @var array $card */ /** @var string $key */ @endphp
<div wire:key="{{ $key }}" data-student-id="{{ $card['id'] }}" class="placement-card"
     style="background:var(--ui-surface);border:1px solid var(--ui-line);border-radius:10px;padding:8px 10px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
        <span style="font-size:13px;font-weight:600;color:var(--ui-heading);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $card['name'] }}</span>
        @if (($card['gender'] ?? null) === 'male')
            <span style="font-size:10px;font-weight:700;background:var(--ui-info-soft-strong);color:var(--ui-info-ink);border-radius:999px;padding:1px 7px;flex:0 0 auto;">L</span>
        @elseif (($card['gender'] ?? null) === 'female')
            <span style="font-size:10px;font-weight:700;background:var(--ui-brand-soft-strong);color:var(--ui-brand-ink);border-radius:999px;padding:1px 7px;flex:0 0 auto;">P</span>
        @endif
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:4px;">
        <span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;font-weight:700;color:var(--ui-text);">NIS: {{ $card['nis'] }}</span>
        <span style="font-size:11px;color:var(--ui-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $card['classroom'] }}">{{ $card['classroom'] }}</span>
    </div>
</div>