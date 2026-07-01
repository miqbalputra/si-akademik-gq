@if (($schoolEvents ?? collect())->isNotEmpty())
    @php
        $eventStyles = [
            'high' => [
                'panel' => 'border-rose-200',
                'type' => 'text-rose-700',
                'date' => 'bg-rose-100 text-rose-800',
                'priority' => 'bg-rose-100 text-rose-800',
            ],
            'medium' => [
                'panel' => 'border-amber-200',
                'type' => 'text-amber-700',
                'date' => 'bg-amber-100 text-amber-800',
                'priority' => 'bg-amber-100 text-amber-800',
            ],
            'normal' => [
                'panel' => 'border-indigo-200',
                'type' => 'text-indigo-700',
                'date' => 'bg-indigo-100 text-indigo-800',
                'priority' => 'bg-slate-100 text-slate-700',
            ],
        ];
    @endphp
    <section class="mb-5 rounded-lg border border-indigo-200 bg-indigo-50 p-4 shadow-sm dark:border-indigo-900 dark:bg-indigo-950/30">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-indigo-800 dark:text-indigo-200">{{ $heading ?? 'Agenda Sekolah' }}</p>
                <p class="mt-1 text-xs text-indigo-700 dark:text-indigo-300">{{ $subheading ?? 'Agenda yang ditentukan admin sekolah.' }}</p>
            </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach ($schoolEvents as $event)
                @php($style = $eventStyles[$event->priorityKey()] ?? $eventStyles['normal'])
                @php($guardianResponse = ($guardianEventResponses ?? collect())->get($event->id))
                <article class="rounded-lg border bg-white p-4 {{ $style['panel'] }} dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xs font-semibold uppercase {{ $style['type'] }} dark:text-current">{{ $event->typeLabel() }}</p>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style['priority'] }}">
                                    {{ $event->priorityLabel() }}
                                </span>
                            </div>
                            <h3 class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $event->title }}</h3>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style['date'] }} dark:text-current">
                            {{ $event->starts_on->equalTo($event->ends_on) ? $event->starts_on->locale('id')->translatedFormat('d M') : $event->starts_on->locale('id')->translatedFormat('d M').' - '.$event->ends_on->locale('id')->translatedFormat('d M') }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ $event->starts_on->locale('id')->translatedFormat('l, d F Y') }}
                        @if (! $event->starts_on->equalTo($event->ends_on))
                            s.d. {{ $event->ends_on->locale('id')->translatedFormat('l, d F Y') }}
                        @endif
                    </p>
                    @if ($event->location)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Lokasi: {{ $event->location }}</p>
                    @endif
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Target: {{ $event->targetSummary() }}</p>
                    @if ($event->description)
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $event->description }}</p>
                    @endif
                    @if (isset($guardianEventResponses))
                        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase text-slate-500">Konfirmasi Kehadiran Wali</p>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ match ($guardianResponse?->attendance_status) {
                                    'attending' => 'bg-emerald-100 text-emerald-800',
                                    'permission' => 'bg-amber-100 text-amber-800',
                                    'not_attending' => 'bg-rose-100 text-rose-800',
                                    default => 'bg-slate-100 text-slate-700',
                                } }}">
                                    {{ $guardianResponse?->statusLabel() ?? 'Belum Konfirmasi' }}
                                </span>
                            </div>
                            @if ($guardianResponse?->responded_at)
                                <p class="mt-2 text-xs text-slate-500">Direspon {{ $guardianResponse->responded_at->locale('id')->translatedFormat('d F Y H:i') }}</p>
                            @endif
                            @if ($guardianResponse?->notes)
                                <p class="mt-2 text-sm text-slate-600">{{ $guardianResponse->notes }}</p>
                            @endif
                            <form method="POST" action="{{ route('wali.events.response', $event) }}" class="mt-3 space-y-2">
                                @csrf
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase text-slate-500">Catatan Opsional</span>
                                    <input
                                        type="text"
                                        name="notes"
                                        value="{{ old('notes', $guardianResponse?->notes) }}"
                                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        placeholder="Misalnya: diwakili ibu, datang terlambat, atau izin"
                                    >
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="submit" name="attendance_status" value="attending" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">
                                        Hadir
                                    </button>
                                    <button type="submit" name="attendance_status" value="permission" class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white">
                                        Izin
                                    </button>
                                    <button type="submit" name="attendance_status" value="not_attending" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white">
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
