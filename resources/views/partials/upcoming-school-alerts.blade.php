@if (($upcomingAlerts ?? collect())->isNotEmpty())
    @php
        $priorityStyles = [
            'high' => [
                'panel' => 'border-danger-line bg-danger-soft',
                'priority' => 'bg-danger-soft-strong text-danger-ink',
                'kind' => 'bg-danger-soft-strong text-danger-ink',
            ],
            'medium' => [
                'panel' => 'border-warning-line bg-warning-soft',
                'priority' => 'bg-warning-soft-strong text-warning-ink',
                'kind' => 'bg-warning-soft-strong text-warning-ink',
            ],
            'normal' => [
                'panel' => 'border-line bg-surface-subtle',
                'priority' => 'bg-surface-muted text-body',
                'kind' => 'bg-brand-soft-strong text-brand-ink',
            ],
        ];
    @endphp
    <section class="mb-5 rounded-lg border border-warning-line bg-warning-soft p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-warning-ink">{{ $heading ?? 'Info 7 Hari ke Depan' }}</p>
                <p class="mt-1 text-xs text-warning-ink">{{ $subheading ?? 'Ringkasan libur sekolah dan event terdekat.' }}</p>
            </div>
        </div>

        <div class="mt-4 space-y-3">
            @foreach ($upcomingAlerts as $alert)
                @php($style = $priorityStyles[$alert['priority_key'] ?? 'normal'] ?? $priorityStyles['normal'])
                <article class="rounded-lg border bg-surface p-3 {{ $style['panel'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ ($alert['is_no_kbm'] ?? false) ? 'bg-info-soft-strong text-info-ink' : $style['kind'] }}">
                                    {{ $alert['kind_label'] }}
                                </span>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style['priority'] }}">
                                    {{ $alert['priority_label'] }}
                                </span>
                                <span class="rounded-full bg-surface-muted px-2.5 py-1 text-[11px] font-semibold text-body">
                                    {{ $alert['countdown_label'] }}
                                </span>
                            </div>
                            <h3 class="mt-2 font-semibold text-heading">{{ $alert['title'] }}</h3>
                            <p class="mt-1 text-sm text-body">{{ $alert['date_label'] }}</p>
                            @if ($alert['meta'])
                                <p class="mt-1 text-sm text-body">{{ $alert['meta'] }}</p>
                            @endif
                            @if ($alert['description'])
                                <p class="mt-2 text-sm text-body">{{ $alert['description'] }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
