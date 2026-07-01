@if ($calendarWeeks === [])
    <section class="rounded-3xl border-2 border-dashed border-slate-200 bg-white/50 p-10 text-center text-slate-500">
        <p class="text-sm font-bold">Belum ada periode ajaran yang bisa ditampilkan pada kalender.</p>
    </section>
@else
    @php
        $showHolidays = in_array($selectedCategory ?? 'all', ['all', 'holiday'], true);
        $showEvents = ($selectedCategory ?? 'all') !== 'holiday';
    @endphp
    
    <!-- Legend Grid -->
    <section class="grid gap-3 md:grid-cols-3 animate-fade-in-up" style="animation-delay: 50ms;">
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 backdrop-blur-md p-4 transition-transform hover:scale-[1.02]">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Hari Sekolah</p>
            <p class="mt-1 text-sm font-semibold text-slate-700">Hari aktif KBM / belajar-mengajar.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 backdrop-blur-md p-4 transition-transform hover:scale-[1.02]">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Sabtu / Minggu</p>
            <p class="mt-1 text-sm font-semibold text-slate-700">Waktu libur rutin santri.</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/50 backdrop-blur-md p-4 transition-transform hover:scale-[1.02]">
            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Libur / Event</p>
            <p class="mt-1 text-sm font-semibold text-slate-700">Libur sekolah atau agenda khusus sekolah.</p>
        </div>
    </section>

    <!-- Calendar Month Sheet -->
    <section class="overflow-hidden rounded-[2rem] glass-card shadow-sm animate-fade-in-up" style="animation-delay: 100ms;">
        
        <!-- Day Labels Header -->
        <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 py-3">
            @foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $dayLabel)
                <div>{{ $dayLabel }}</div>
            @endforeach
        </div>

        <!-- Days Grid -->
        <div class="grid gap-px bg-slate-100">
            @foreach ($calendarWeeks as $week)
                <div class="grid grid-cols-7 gap-px">
                    @foreach ($week as $day)
                        @php
                            $panelClass = match (true) {
                                ! $day['is_current_month'] => 'bg-slate-50/30 opacity-40',
                                $day['holiday'] => 'bg-amber-50/60',
                                $day['is_weekend'] => 'bg-slate-100/50',
                                default => 'bg-emerald-50/30',
                            };
                        @endphp
                        <article class="min-h-[140px] p-3 transition-colors hover:bg-slate-50/30 {{ $panelClass }}">
                            <div class="flex items-start justify-between">
                                <div class="flex items-baseline gap-1">
                                    <p class="text-base font-black text-slate-800">{{ $day['day_number'] }}</p>
                                    <p class="text-[9px] font-semibold text-slate-400 uppercase">{{ substr($day['day_name'], 0, 3) }}</p>
                                </div>
                            </div>

                            <!-- Day Items -->
                            <div class="mt-3 space-y-1.5">
                                @if ($showHolidays && $day['holiday'])
                                    <div class="rounded-xl border border-amber-200 bg-white p-2.5 shadow-sm">
                                        <p class="text-[9px] font-bold uppercase tracking-wider text-amber-700">Libur Sekolah</p>
                                        <p class="mt-1 text-xs font-black text-slate-800 leading-tight">{{ $day['holiday']['title'] }}</p>
                                        @if ($day['holiday']['description'])
                                            <p class="mt-1 text-[10px] font-medium leading-relaxed text-slate-500">{{ $day['holiday']['description'] }}</p>
                                        @endif
                                    </div>
                                @endif

                                @if ($showEvents)
                                    @foreach ($day['events'] as $event)
                                        <div class="rounded-xl border border-indigo-200 bg-white p-2.5 shadow-sm">
                                            <p class="text-[9px] font-bold uppercase tracking-wider text-indigo-700">{{ $event['type_label'] }}</p>
                                            <p class="mt-1 text-xs font-black text-slate-800 leading-tight">{{ $event['title'] }}</p>
                                            @if ($event['location'])
                                                <p class="mt-1 text-[9px] font-semibold text-slate-500">📍 {{ $event['location'] }}</p>
                                            @endif
                                            <p class="mt-0.5 text-[9px] font-semibold text-slate-400">Target: {{ $event['target_label'] }}</p>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <!-- Detailed List Section -->
    <section class="grid gap-6 lg:grid-cols-2 animate-fade-in-up" style="animation-delay: 150ms;">
        @if ($showHolidays)
            <div class="rounded-3xl glass-card p-6">
                <h3 class="font-black text-slate-900 text-lg mb-4">Libur Sekolah Bulan Ini</h3>
                @if ($holidayList === [])
                    <p class="text-xs font-semibold text-slate-500">Belum ada libur sekolah yang terdaftar di {{ $selectedMonthLabel }}.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($holidayList as $holiday)
                            <article class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800">{{ $holiday['date_label'] }}</p>
                                <p class="mt-1 font-black text-slate-800 leading-snug">{{ $holiday['title'] }}</p>
                                @if ($holiday['description'])
                                    <p class="mt-1 text-xs font-medium text-slate-500 leading-relaxed">{{ $holiday['description'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if ($showEvents)
            <div class="rounded-3xl glass-card p-6">
                <h3 class="font-black text-slate-900 text-lg mb-4">Agenda Sekolah Bulan Ini</h3>
                @if ($eventList === [])
                    <p class="text-xs font-semibold text-slate-500">Belum ada event sekolah di {{ $selectedMonthLabel }}.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($eventList as $event)
                            <article class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">{{ $event['type_label'] }}</p>
                                <p class="mt-1 font-black text-slate-800 leading-snug">{{ $event['title'] }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-400">{{ $event['date_label'] }}</p>
                                @if ($event['location'])
                                    <p class="mt-1 text-xs font-semibold text-slate-500">📍 Lokasi: {{ $event['location'] }}</p>
                                @endif
                                <p class="mt-0.5 text-xs font-semibold text-slate-400">🎯 Target: {{ $event['target_label'] }}</p>
                                @if ($event['description'])
                                    <p class="mt-2 text-xs font-medium text-slate-500 leading-relaxed border-t border-slate-100 pt-2">{{ $event['description'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </section>
@endif
