@if ($calendarWeeks === [])
    <section class="rounded-2xl border-2 border-dashed border-line bg-surface/50 p-10 text-center text-muted">
        <p class="text-theme-sm font-normal">Belum ada periode ajaran yang bisa ditampilkan pada kalender.</p>
    </section>
@else
    @php
        $showHolidays = in_array($selectedCategory ?? 'all', ['all', 'holiday'], true);
        $showEvents = ($selectedCategory ?? 'all') !== 'holiday';
    @endphp
    
    <!-- Legend Grid -->
    <section class="grid gap-3 md:grid-cols-3 animate-fade-in-up mb-6" style="animation-delay:50ms;">
        <div class="rounded-2xl border border-success-line bg-success-soft/50 backdrop-blur-md p-4 transition-transform hover:scale-[1.02]">
            <p class="text-theme-xs font-normal uppercase text-success-ink">Hari Sekolah</p>
            <p class="mt-1 text-theme-sm font-normal text-body">Hari aktif KBM / belajar-mengajar.</p>
        </div>
        <div class="rounded-2xl border border-line bg-surface-subtle/80 backdrop-blur-md p-4 transition-transform hover:scale-[1.02]">
            <p class="text-theme-xs font-normal uppercase text-muted">Sabtu / Minggu</p>
            <p class="mt-1 text-theme-sm font-normal text-body">Waktu libur rutin santri.</p>
        </div>
        <div class="rounded-2xl border border-warning-line bg-warning-soft/50 backdrop-blur-md p-4 transition-transform hover:scale-[1.02]">
            <p class="text-theme-xs font-normal uppercase text-warning-ink">Libur / Event</p>
            <p class="mt-1 text-theme-sm font-normal text-body">Libur sekolah atau agenda khusus sekolah.</p>
        </div>
    </section>

    <!-- MOBILE VIEW: Vertical Timeline (Hidden on MD and larger) -->
    <section class="block md:hidden space-y-4 mb-8 animate-fade-in-up" style="animation-delay:100ms;">
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
                            <span class="text-theme-xs font-normal uppercase text-soft">{{ substr($day['day_name'], 0, 3) }}</span>
                            <span class="text-xl font-semibold text-heading leading-none">{{ $day['day_number'] }}</span>
                            <div class="w-px h-full bg-surface-muted mt-2 rounded-full"></div>
                        </div>
                        
                        <!-- Content Column -->
                        <div class="flex-grow pb-6 space-y-2">
                            @if ($showHolidays && $day['holiday'])
                                <div class="rounded-2xl border border-warning-line bg-gradient-to-br from-warning-soft to-warning-soft p-3.5 shadow-sm">
                                    <p class="text-theme-xs font-normal uppercase text-warning-ink mb-1">Libur Sekolah</p>
                                    <p class="text-theme-sm font-medium text-heading">{{ $day['holiday']['title'] }}</p>
                                    @if ($day['holiday']['description'])
                                        <p class="mt-1.5 text-theme-xs font-normal text-body">{{ $day['holiday']['description'] }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($showEvents)
                                @foreach ($day['events'] as $event)
                                    <div class="rounded-2xl border border-brand-line bg-gradient-to-br from-brand-soft to-info-soft p-3.5 shadow-sm">
                                        <p class="text-theme-xs font-normal uppercase text-brand-ink mb-1">{{ $event['type_label'] }}</p>
                                        <p class="text-theme-sm font-medium text-heading">{{ $event['title'] }}</p>
                                        @if ($event['location'])
                                            <p class="mt-1.5 text-theme-xs font-normal text-body flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
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
            <div class="rounded-2xl border-2 border-dashed border-line bg-surface-subtle/50 p-8 text-center">
                <p class="text-theme-sm font-normal text-muted">Tidak ada agenda atau hari libur di bulan ini.</p>
            </div>
        @endif
    </section>

    <!-- DESKTOP VIEW: Calendar Grid (Hidden on Mobile) -->
    <section class="hidden md:block overflow-hidden rounded-2xl glass-card shadow-sm animate-fade-in-up mb-8 border border-white/50" style="animation-delay:100ms;">
        
        <!-- Day Labels Header -->
        <div class="grid grid-cols-7 border-b border-line bg-surface/60 backdrop-blur-xl text-center text-theme-xs font-normal uppercase text-muted py-4">
            @foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $dayLabel)
                <div>{{ $dayLabel }}</div>
            @endforeach
        </div>

        <!-- Days Grid -->
        <div class="grid gap-px bg-surface-muted/80">
            @foreach ($calendarWeeks as $week)
                <div class="grid grid-cols-7 gap-px">
                    @foreach ($week as $day)
                        @php
                            $panelClass = match (true) {
                                ! $day['is_current_month'] => 'bg-surface/40 opacity-50',
                                $day['holiday'] => 'bg-warning-soft/80 hover:bg-warning-soft-strong/80',
                                $day['is_weekend'] => 'bg-surface-subtle/80 hover:bg-surface-muted/80',
                                default => 'bg-success-soft/40 hover:bg-success-soft/80',
                            };
                        @endphp
                        <article class="min-h-[150px] p-3 transition-all duration-300 {{ $panelClass }} group cursor-default">
                            <div class="flex items-start justify-between">
                                <div class="flex items-baseline gap-1.5">
                                    <p class="text-lg font-semibold text-heading transition-transform group-hover:scale-110 group-hover:text-brand-ink origin-left">{{ $day['day_number'] }}</p>
                                    <p class="text-theme-xs font-normal text-soft uppercase">{{ substr($day['day_name'], 0, 3) }}</p>
                                </div>
                            </div>

                            <!-- Day Items -->
                            <div class="mt-3 space-y-2">
                                @if ($showHolidays && $day['holiday'])
                                    <div class="rounded-xl border border-warning-line/60 bg-surface/90 p-2.5 shadow-sm transition-transform hover:-translate-y-0.5 hover:shadow-md">
                                        <p class="text-theme-xs font-normal uppercase text-warning-ink">Libur Sekolah</p>
                                        <p class="mt-0.5 text-theme-xs font-normal text-heading">{{ $day['holiday']['title'] }}</p>
                                    </div>
                                @endif

                                @if ($showEvents)
                                    @foreach ($day['events'] as $event)
                                        <div class="rounded-xl border border-brand-line/60 bg-surface/90 p-2.5 shadow-sm transition-transform hover:-translate-y-0.5 hover:shadow-md">
                                            <p class="text-theme-xs font-normal uppercase text-brand-ink">{{ $event['type_label'] }}</p>
                                            <p class="mt-0.5 text-theme-xs font-normal text-heading">{{ $event['title'] }}</p>
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
    <section class="hidden md:grid gap-6 lg:grid-cols-2 animate-fade-in-up" style="animation-delay:150ms;">
        @if ($showHolidays)
            <div class="rounded-2xl glass-card p-6 border border-white/50">
                <div class="flex items-center gap-3 mb-5">
                    <div class="p-2.5 rounded-xl bg-warning-soft-strong text-warning-ink">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                    </div>
                    <h3 class="text-heading ui-card-title">Libur Sekolah Bulan Ini</h3>
                </div>
                
                @if ($holidayList === [])
                    <p class="text-theme-xs font-normal text-muted bg-surface-subtle p-4 rounded-2xl text-center">Belum ada libur sekolah yang terdaftar di {{ $selectedMonthLabel }}.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($holidayList as $holiday)
                            <article class="rounded-2xl border border-warning-line/80 bg-gradient-to-r from-warning-soft to-warning-soft/50 p-4 transition-colors hover:border-warning-line">
                                <p class="text-theme-xs font-normal uppercase text-warning-ink">{{ $holiday['date_label'] }}</p>
                                <p class="mt-1 font-semibold text-heading leading-snug">{{ $holiday['title'] }}</p>
                                @if ($holiday['description'])
                                    <p class="mt-1.5 text-theme-xs font-normal text-body">{{ $holiday['description'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if ($showEvents)
            <div class="rounded-2xl glass-card p-6 border border-white/50">
                <div class="flex items-center gap-3 mb-5">
                    <div class="p-2.5 rounded-xl bg-brand-soft-strong text-brand-ink">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                    <h3 class="text-heading ui-card-title">Agenda Sekolah Bulan Ini</h3>
                </div>

                @if ($eventList === [])
                    <p class="text-theme-xs font-normal text-muted bg-surface-subtle p-4 rounded-2xl text-center">Belum ada event sekolah di {{ $selectedMonthLabel }}.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($eventList as $event)
                            <article class="rounded-2xl border border-brand-line/80 bg-gradient-to-r from-brand-soft to-info-soft/50 p-4 transition-colors hover:border-brand-line">
                                <p class="text-theme-xs font-normal uppercase text-brand-ink">{{ $event['type_label'] }}</p>
                                <p class="mt-1 font-semibold text-heading leading-snug text-base">{{ $event['title'] }}</p>
                                <p class="mt-1 text-theme-xs font-normal text-muted">{{ $event['date_label'] }}</p>
                                
                                <div class="mt-3 pt-3 border-t border-brand-line/60 flex flex-wrap gap-x-4 gap-y-2">
                                    @if ($event['location'])
                                        <p class="text-theme-xs font-normal text-body flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            {{ $event['location'] }}
                                        </p>
                                    @endif
                                    <p class="text-theme-xs font-normal text-body flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        Target: {{ $event['target_label'] }}
                                    </p>
                                </div>
                                
                                @if ($event['description'])
                                    <p class="mt-2.5 text-theme-xs font-normal text-muted bg-surface/50 p-2.5 rounded-xl border border-white">{{ $event['description'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </section>
@endif
