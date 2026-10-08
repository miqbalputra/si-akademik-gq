@if (($schoolEvents ?? collect())->isNotEmpty())
    @php
        $eventStyles = [
            'high' => [
                'panel' => 'border-danger-line',
                'type' => 'text-danger-ink',
                'date' => 'bg-danger-soft-strong text-danger-ink',
                'priority' => 'bg-danger-soft-strong text-danger-ink',
            ],
            'medium' => [
                'panel' => 'border-warning-line',
                'type' => 'text-warning-ink',
                'date' => 'bg-warning-soft-strong text-warning-ink',
                'priority' => 'bg-warning-soft-strong text-warning-ink',
            ],
            'normal' => [
                'panel' => 'border-brand-line',
                'type' => 'text-brand-ink',
                'date' => 'bg-brand-soft-strong text-brand-ink',
                'priority' => 'bg-surface-muted text-body',
            ],
        ];
    @endphp
    <section class="mb-5 rounded-lg border border-brand-line bg-brand-soft p-4 shadow-sm dark:border-brand-900 dark:bg-brand-950/30">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-theme-sm font-normal text-brand-ink dark:text-brand-200">{{ $heading ?? 'Agenda Sekolah' }}</p>
                <p class="mt-1 text-theme-xs text-brand-ink dark:text-brand-300 font-normal">{{ $subheading ?? 'Agenda yang ditentukan admin sekolah.' }}</p>
            </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach ($schoolEvents as $event)
                @php($style = $eventStyles[$event->priorityKey()] ?? $eventStyles['normal'])
                @php($guardianResponse = ($guardianEventResponses ?? collect())->get($event->id))
                <article class="rounded-lg border bg-surface p-4 {{ $style['panel'] }} dark:bg-surface">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-theme-xs font-normal uppercase {{ $event->is_no_kbm ? 'text-info-ink' : $style['type'] }} dark:text-current">{{ $event->is_no_kbm ? 'Agenda Tanpa KBM' : $event->typeLabel() }}</p>
                                <span class="rounded-full px-2.5 py-1 {{ $style['priority'] }} text-theme-xs font-medium">
                                    {{ $event->priorityLabel() }}
                                </span>
                            </div>
                            <h3 class="mt-1 text-heading dark:text-white ui-card-title">{{ $event->title }}</h3>
                        </div>
                        <span class="rounded-full px-2.5 py-1 {{ $style['date'] }} dark:text-current text-theme-xs font-medium">
                            {{ $event->starts_on->equalTo($event->ends_on) ? $event->starts_on->locale('id')->translatedFormat('d M') : $event->starts_on->locale('id')->translatedFormat('d M').' - '.$event->ends_on->locale('id')->translatedFormat('d M') }}
                        </span>
                    </div>
                    <p class="mt-2 text-theme-sm text-body dark:text-body">
                        {{ $event->starts_on->locale('id')->translatedFormat('l, d F Y') }}
                        @if (! $event->starts_on->equalTo($event->ends_on))
                            s.d. {{ $event->ends_on->locale('id')->translatedFormat('l, d F Y') }}
                        @endif
                    </p>
                    @if ($event->location)
                        <p class="mt-1 text-theme-sm text-body dark:text-body">Lokasi: {{ $event->location }}</p>
                    @endif
                    <p class="mt-1 text-theme-sm text-body dark:text-body">Target: {{ $event->targetSummary() }}</p>
                    @if ($event->description)
                        <p class="mt-2 text-theme-sm text-body dark:text-heading">{{ $event->description }}</p>
                    @endif
                    @if (isset($guardianEventResponses))
                        <div class="mt-3 rounded-lg border border-line bg-surface-subtle p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-theme-xs font-normal uppercase text-muted">Konfirmasi Kehadiran Wali</p>
                                <span class="rounded-full px-2.5 py-1 {{ match ($guardianResponse?->attendance_status) { 'attending' => 'bg-success-soft-strong text-success-ink', 'permission' => 'bg-warning-soft-strong text-warning-ink', 'not_attending' => 'bg-danger-soft-strong text-danger-ink', default => 'bg-surface-muted text-body', } }} text-theme-xs font-medium">
                                    {{ $guardianResponse?->statusLabel() ?? 'Belum Konfirmasi' }}
                                </span>
                            </div>
                            @if ($guardianResponse?->responded_at)
                                <p class="mt-2 text-theme-xs text-muted font-normal">Direspon {{ $guardianResponse->responded_at->locale('id')->translatedFormat('d F Y H:i') }}</p>
                            @endif
                            @if ($guardianResponse?->notes)
                                <p class="mt-2 text-theme-sm text-body">{{ $guardianResponse->notes }}</p>
                            @endif
                            <form method="POST" action="{{ route('wali.events.response', $event) }}" class="mt-3 space-y-2">
                                @csrf
                                <label class="block ui-form-label">
                                    <span class="text-theme-xs font-normal uppercase text-muted">Catatan Opsional</span>
                                    <input
                                        type="text"
                                        name="notes"
                                        value="{{ old('notes', $guardianResponse?->notes) }}"
                                        class="mt-1 w-full rounded-lg border border-line-strong px-3 py-2 text-theme-sm font-normal"
                                        placeholder="Misalnya: diwakili ibu, datang terlambat, atau izin"
                                    >
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="submit" name="attendance_status" value="attending" class="rounded-lg bg-success-600 px-3 py-2 text-white text-theme-sm font-medium">
                                        Hadir
                                    </button>
                                    <button type="submit" name="attendance_status" value="permission" class="rounded-lg bg-warning-500 px-3 py-2 text-white text-theme-sm font-medium">
                                        Izin
                                    </button>
                                    <button type="submit" name="attendance_status" value="not_attending" class="rounded-lg bg-danger-600 px-3 py-2 text-white text-theme-sm font-medium">
                                        Tidak Hadir
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
