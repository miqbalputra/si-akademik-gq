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
    <section class="grid gap-3 md:grid-cols-3 animate-fade-in-up mb-6" style="animation-delay: 50ms;">
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

    <!-- MOBILE VIEW: Vertical Timeline (Hidden on MD and larger) -->
    <section class="block md:hidden space-y-4 mb-8 animate-fade-in-up" style="animation-delay: 100ms;">
        @php
            $hasAnyMobileEvents = false;
        @endphp
        
        @foreach ($calendarWeeks as $week)
            @foreach ($week as $day)
                @if(($showHolidays && $day['holiday']) || ($showEvents && count($day['events']) > 0))
                    @php $hasAnyMobileEvents = true; @endphp
                    <div class="flex gap-4">
                        <!-- Date Column -->
                        <div class="flex flex-col items-center w-14 shrink-0 pt-1">
                            <span class="text-[10px] font-bold uppercase text-slate-400">{{ substr($day['day_name'], 0, 3) }}</span>
                            <span class="text-xl font-black text-slate-800 leading-none">{{ $day['day_number'] }}</span>
                            <div class="w-px h-full bg-slate-200 mt-2 rounded-full"></div>
                        </div>
                        
                        <!-- Content Column -->
                        <div class="flex-grow pb-6 space-y-2">
                            @if ($showHolidays && $day['holiday'])
                                <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-3.5 shadow-sm">
                                    <p class="text-[9px] font-bold uppercase tracking-wider text-amber-700 mb-1">Libur Sekolah</p>
                                    <p class="text-sm font-black text-slate-800 leading-tight">{{ $day['holiday']['title'] }}</p>
                                    @if ($day['holiday']['description'])
                                        <p class="mt-1.5 text-xs font-medium text-slate-600">{{ $day['holiday']['description'] }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($showEvents)
                                @foreach ($day['events'] as $event)
                                    <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-blue-50 p-3.5 shadow-sm">
                                        <p class="text-[9px] font-bold uppercase tracking-wider text-indigo-700 mb-1">{{ $event['type_label'] }}</p>
                                        <p class="text-sm font-black text-slate-800 leading-tight">{{ $event['title'] }}</p>
                                        @if ($event['location'])
                                            <p class="mt-1.5 text-xs font-semibold text-slate-600 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                {{ $event['location'] }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        @endforeach
        
        @if(!$hasAnyMobileEvents)
            <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-8 text-center">
                <p class="text-sm font-bold text-slate-500">Tidak ada agenda atau hari libur di bulan ini.</p>
            </div>
        @endif
    </section>

    <!-- DESKTOP VIEW: Calendar Grid (Hidden on Mobile) -->
    <section class="hidden md:block overflow-hidden rounded-[2rem] glass-card shadow-sm animate-fade-in-up mb-8 border border-white/50" style="animation-delay: 100ms;">
        
        <!-- Day Labels Header -->
        <div class="grid grid-cols-7 border-b border-slate-100 bg-white/60 backdrop-blur-xl text-center text-[10px] font-black uppercase tracking-wider text-slate-500 py-4">
            @foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $dayLabel)
                <div>{{ $dayLabel }}</div>
            @endforeach
        </div>

        <!-- Days Grid -->
        <div class="grid gap-px bg-slate-100/80">
            @foreach ($calendarWeeks as $week)
                <div class="grid grid-cols-7 gap-px">
                    @foreach ($week as $day)
                        @php
                            $panelClass = match (true) {
                                ! $day['is_current_month'] => 'bg-white/40 opacity-50',
                                $day['holiday'] => 'bg-amber-50/80 hover:bg-amber-100/80',
                                $day['is_weekend'] => 'bg-slate-50/80 hover:bg-slate-100/80',
                                default => 'bg-emerald-50/40 hover:bg-emerald-50/80',
                            };
                        @endphp
                        <article class="min-h-[150px] p-3 transition-all duration-300 {{ $panelClass }} group cursor-default">
                            <div class="flex items-start justify-between">
                                <div class="flex items-baseline gap-1.5">
                                    <p class="text-lg font-black text-slate-800 transition-transform group-hover:scale-110 group-hover:text-indigo-600 origin-left">{{ $day['day_number'] }}</p>
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">{{ substr($day['day_name'], 0, 3) }}</p>
                                </div>
                            </div>

                            <!-- Day Items -->
                            <div class="mt-3 space-y-2">
                                @if ($showHolidays && $day['holiday'])
                                    <div class="rounded-xl border border-amber-200/60 bg-white/90 p-2.5 shadow-sm transition-transform hover:-translate-y-0.5 hover:shadow-md">
                                        <p class="text-[8px] font-black uppercase tracking-wider text-amber-600">Libur Sekolah</p>
                                        <p class="mt-0.5 text-xs font-black text-slate-800 leading-tight">{{ $day['holiday']['title'] }}</p>
                                    </div>
                                @endif

                                @if ($showEvents)
                                    @foreach ($day['events'] as $event)
                                        <div class="rounded-xl border border-indigo-200/60 bg-white/90 p-2.5 shadow-sm transition-transform hover:-translate-y-0.5 hover:shadow-md">
                                            <p class="text-[8px] font-black uppercase tracking-wider text-indigo-600">{{ $event['type_label'] }}</p>
                                            <p class="mt-0.5 text-xs font-black text-slate-800 leading-tight">{{ $event['title'] }}</p>
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

    <!-- Detailed List Section (Desktop only to avoid duplicate info on Mobile) -->
    <section class="hidden md:grid gap-6 lg:grid-cols-2 animate-fade-in-up" style="animation-delay: 150ms;">
        @if ($showHolidays)
            <div class="rounded-3xl glass-card p-6 border border-white/50">
                <div class="flex items-center gap-3 mb-5">
                    <div class="p-2.5 rounded-xl bg-amber-100 text-amber-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                    </div>
                    <h3 class="font-black text-slate-900 text-lg">Libur Sekolah Bulan Ini</h3>
                </div>
                
                @if ($holidayList === [])
                    <p class="text-xs font-semibold text-slate-500 bg-slate-50 p-4 rounded-2xl text-center">Belum ada libur sekolah yang terdaftar di {{ $selectedMonthLabel }}.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($holidayList as $holiday)
                            <article class="rounded-2xl border border-amber-200/80 bg-gradient-to-r from-amber-50 to-orange-50/50 p-4 transition-colors hover:border-amber-300">
                                <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">{{ $holiday['date_label'] }}</p>
                                <p class="mt-1 font-black text-slate-800 leading-snug">{{ $holiday['title'] }}</p>
                                @if ($holiday['description'])
                                    <p class="mt-1.5 text-xs font-medium text-slate-600 leading-relaxed">{{ $holiday['description'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if ($showEvents)
            <div class="rounded-3xl glass-card p-6 border border-white/50">
                <div class="flex items-center gap-3 mb-5">
                    <div class="p-2.5 rounded-xl bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                    <h3 class="font-black text-slate-900 text-lg">Agenda Sekolah Bulan Ini</h3>
                </div>

                @if ($eventList === [])
                    <p class="text-xs font-semibold text-slate-500 bg-slate-50 p-4 rounded-2xl text-center">Belum ada event sekolah di {{ $selectedMonthLabel }}.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($eventList as $event)
                            <article class="rounded-2xl border border-indigo-200/80 bg-gradient-to-r from-indigo-50 to-blue-50/50 p-4 transition-colors hover:border-indigo-300">
                                <p class="text-[10px] font-black uppercase tracking-wider text-indigo-700">{{ $event['type_label'] }}</p>
                                <p class="mt-1 font-black text-slate-800 leading-snug text-base">{{ $event['title'] }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ $event['date_label'] }}</p>
                                
                                <div class="mt-3 pt-3 border-t border-indigo-100/60 flex flex-wrap gap-x-4 gap-y-2">
                                    @if ($event['location'])
                                        <p class="text-[11px] font-semibold text-slate-600 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            {{ $event['location'] }}
                                        </p>
                                    @endif
                                    <p class="text-[11px] font-semibold text-slate-600 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        Target: {{ $event['target_label'] }}
                                    </p>
                                </div>
                                
                                @if ($event['description'])
                                    <p class="mt-2.5 text-xs font-medium text-slate-500 leading-relaxed bg-white/50 p-2.5 rounded-xl border border-white">{{ $event['description'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </section>
@endif
