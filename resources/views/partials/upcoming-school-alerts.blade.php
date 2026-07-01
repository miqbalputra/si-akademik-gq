@if (($upcomingAlerts ?? collect())->isNotEmpty())
    @php
        $priorityStyles = [
            'high' => [
                'panel' => 'border-rose-200 bg-rose-50',
                'priority' => 'bg-rose-100 text-rose-800',
                'kind' => 'bg-rose-100 text-rose-800',
            ],
            'medium' => [
                'panel' => 'border-amber-200 bg-amber-50',
                'priority' => 'bg-amber-100 text-amber-800',
                'kind' => 'bg-amber-100 text-amber-800',
            ],
            'normal' => [
                'panel' => 'border-slate-200 bg-slate-50',
                'priority' => 'bg-slate-100 text-slate-700',
                'kind' => 'bg-indigo-100 text-indigo-800',
            ],
        ];
    @endphp
    <section class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-amber-800">{{ $heading ?? 'Info 7 Hari ke Depan' }}</p>
                <p class="mt-1 text-xs text-amber-700">{{ $subheading ?? 'Ringkasan libur sekolah dan event terdekat.' }}</p>
            </div>
        </div>

        <div class="mt-4 space-y-3">
            @foreach ($upcomingAlerts as $alert)
                @php($style = $priorityStyles[$alert['priority_key'] ?? 'normal'] ?? $priorityStyles['normal'])
                <article class="rounded-lg border bg-white p-3 {{ $style['panel'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style['kind'] }}">
                                    {{ $alert['kind_label'] }}
                                </span>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style['priority'] }}">
                                    {{ $alert['priority_label'] }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                    {{ $alert['countdown_label'] }}
                                </span>
                            </div>
                            <h3 class="mt-2 font-semibold text-slate-900">{{ $alert['title'] }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $alert['date_label'] }}</p>
                            @if ($alert['meta'])
                                <p class="mt-1 text-sm text-slate-600">{{ $alert['meta'] }}</p>
                            @endif
                            @if ($alert['description'])
                                <p class="mt-2 text-sm text-slate-700">{{ $alert['description'] }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
