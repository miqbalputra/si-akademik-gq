@php /** @var array $card */ /** @var string $key */ @endphp
<div wire:key="{{ $key }}" data-student-id="{{ $card['id'] }}" class="placement-card"
     style="background:var(--ui-surface);border:1px solid var(--ui-line);border-radius:10px;padding:8px 10px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
        <span style="font-size:14px;font-weight:500;color:var(--ui-heading);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:20px;">{{ $card['name'] }}</span>
        @if (($card['gender'] ?? null) === 'male')
            <span style="font-size:12px;background:var(--ui-info-soft-strong);color:var(--ui-info-ink);border-radius:999px;padding:1px 7px;flex:0 0 auto;line-height:18px;font-weight:500;">L</span>
        @elseif (($card['gender'] ?? null) === 'female')
            <span style="font-size:12px;background:var(--ui-brand-soft-strong);color:var(--ui-brand-ink);border-radius:999px;padding:1px 7px;flex:0 0 auto;line-height:18px;font-weight:500;">P</span>
        @endif
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:4px;">
        <span style="font-family:var(--font-outfit);font-size:12px;color:var(--ui-text);line-height:18px;font-weight:400;">NIS: {{ $card['nis'] }}</span>
        <span style="font-size:12px;color:var(--ui-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:18px;font-weight:400;" title="{{ $card['classroom'] }}">{{ $card['classroom'] }}</span>
    </div>
</div>