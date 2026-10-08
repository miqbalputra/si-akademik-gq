<x-layouts.portal title="Presensi {{ $classroomTerm->name }}" portalLabel="Portal Guru" breadcrumb="Presensi Kelas">
    <x-slot name="navLinks">
        <a href="{{ route('attendance.index', ['month' => $selectedMonth]) }}" class="btn btn-outline btn-sm text-theme-sm font-medium">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Daftar Kelas</span>
        </a>
    </x-slot>

    @push('styles')
    <style>

        :root { --attendance-sticky-top:4.6rem; --attendance-student-column-width:clamp(16rem, 22vw, 20rem); }
        .attendance-grid-card { overflow:visible; }
        .attendance-grid-table { width:var(--attendance-grid-width); min-width:var(--attendance-grid-width); table-layout:fixed; border-collapse:separate; border-spacing:0; }
        .attendance-sticky-head {
            position:sticky;
            top:var(--attendance-sticky-top);
            z-index:30;
            overflow:hidden;
            border-bottom:1px solid var(--ui-line);
            border-radius:2rem 2rem 0 0;
            background:var(--ui-surface-subtle);
            box-shadow:0 6px 12px -10px rgb(15 23 42 / .45);
            backdrop-filter:blur(12px);
            -webkit-backdrop-filter:blur(12px);
        }
        .attendance-sticky-head-content { width:var(--attendance-grid-width); will-change:transform; }
        .attendance-sticky-head-content thead th:first-child { position:static !important; left:auto !important; }
        .attendance-today-heading { background:var(--ui-warning-soft-strong); box-shadow:inset 0 -3px 0 var(--color-warning-600); }
        .attendance-today-heading .attendance-day-number { color:var(--ui-warning-ink); }
        .attendance-today-label { display:block; margin-top:.15rem; color:var(--ui-warning-ink); font-size:12px; line-height:18px; font-weight:500; }
        .attendance-today-cell { background:var(--ui-warning-soft); box-shadow:inset 1px 0 var(--ui-warning-line), inset -1px 0 var(--ui-warning-line); }
        .attendance-sticky-name {
            position:absolute;
            inset:0 auto 0 0;
            z-index:2;
            display:flex;
            width:var(--attendance-student-column-width);
            align-items:center;
            justify-content:space-between;
            gap:.75rem;
            border-right:1px solid var(--ui-line);
            background:var(--ui-surface-subtle);
            padding:.75rem 1.5rem;
            color:var(--ui-muted);
            letter-spacing:normal;
            text-transform:none; font-size:12px; line-height:18px; font-weight:400; }
        .attendance-sticky-month { color:var(--ui-soft); letter-spacing:normal; text-transform:none; font-size:12px; line-height:18px; font-weight:400; }
        @media (max-width: 767px) {
            .attendance-day-picker {
                position:sticky;
                top:var(--attendance-sticky-top);
                z-index:30;
                border-radius:2rem 2rem 0 0;
                background:color-mix(in srgb, var(--ui-surface) 98%, transparent);
                box-shadow:0 6px 12px -10px rgb(15 23 42 / .45);
                backdrop-filter:blur(12px);
                -webkit-backdrop-filter:blur(12px);
            }
        }
    </style>
    @endpush

    <!-- Main Content -->
    <div class="mx-auto max-w-7xl">
        
        <!-- Header -->
        <header class="mb-6 rounded-2xl glass-card p-6 sm:p-8 animate-fade-in-up">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-warning-soft-strong px-2.5 py-0.5 text-warning-ink mb-2 text-theme-xs font-medium">
                        Presensi {{ $selectedMonthLabel }}
                    </span>
                    <h1 class="text-heading ui-page-title">{{ $classroomTerm->name }}</h1>
                    <p class="mt-2 text-theme-sm font-normal text-muted">
                        {{ $classroomTerm->academicTerm?->academicYear?->name }} &middot; {{ $classroomTerm->academicTerm?->name }}
                    </p>
                    <p class="mt-2 text-theme-xs text-soft font-normal">
                        * Hari Sabtu dan Minggu otomatis libur (tidak dihitung).
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center text-theme-xs sm:min-w-[320px] font-normal">
                    <div class="rounded-2xl bg-danger-soft/80 border border-danger-line p-3 text-danger-ink">
                        <p class="text-theme-xs font-normal uppercase">Sakit</p>
                        <p class="mt-1 ui-metric-value" id="header-sick">{{ $classTotals['sick'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-info-soft/80 border border-info-line p-3 text-info-ink">
                        <p class="text-theme-xs font-normal uppercase">Izin</p>
                        <p class="mt-1 ui-metric-value" id="header-permission">{{ $classTotals['permission'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-surface-muted/80 border border-line p-3 text-heading">
                        <p class="text-theme-xs font-normal uppercase">Alpa</p>
                        <p class="mt-1 ui-metric-value" id="header-absent">{{ $classTotals['absent'] }}</p>
                    </div>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded-2xl border border-success-line bg-success-soft p-4 text-theme-sm font-medium text-success-ink shadow-sm animate-fade-in-up">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-danger-line bg-danger-soft p-4 text-theme-sm font-medium text-danger-ink shadow-sm animate-fade-in-up">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Filter and Legends -->
        <section class="mb-6 grid gap-4 rounded-2xl glass-card p-5 lg:grid-cols-[1fr_auto] items-end animate-fade-in-up" style="animation-delay:50ms;">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-muted mb-1.5 ui-form-label">Bulan</label>
                    <select name="month" class="min-w-[200px] rounded-xl border-2 border-line bg-surface/50 px-3 py-2 outline-none focus:border-brand-500 focus:bg-surface focus:ring-4 focus:ring-brand-500/10 text-theme-sm font-normal">
                        @foreach ($availableMonths as $month)
                            <option value="{{ $month['value'] }}" @selected($month['value'] === $selectedMonth)>{{ $month['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-xl bg-warning-600 px-4 py-2.5 text-white hover:bg-warning-700 transition-colors shadow-md text-theme-sm font-medium">
                    Ganti Bulan
                </button>
            </form>
            <div class="flex flex-wrap gap-2 text-theme-xs font-normal uppercase">
                <span class="rounded-lg bg-surface px-2 py-1.5 border border-line text-body">H Hadir</span>
                <span class="rounded-lg bg-warning-soft-strong px-2 py-1.5 border border-warning-line text-warning-ink">S Sakit</span>
                <span class="rounded-lg bg-success-soft-strong px-2 py-1.5 border border-success-line text-success-ink">I Izin</span>
                <span class="rounded-lg bg-danger-soft-strong px-2 py-1.5 border border-danger-line text-danger-ink">A Alpa</span>
                <span class="rounded-lg bg-info-soft-strong px-2 py-1.5 border border-info-line text-info-ink">L Libur</span>
                @if ($days->contains(fn ($day) => $day->toDateString() === $todayWib))
                    <span class="rounded-lg border border-warning-line bg-warning-soft px-2 py-1.5 text-warning-ink">Hari ini: {{ \Carbon\CarbonImmutable::parse($todayWib)->locale('id')->translatedFormat('l, d F Y') }} WIB</span>
                @endif
            </div>
        </section>

        <!-- Holiday list -->
        @if ($schoolHolidays->isNotEmpty())
            <section class="mb-6 rounded-2xl border border-warning-line bg-warning-soft/50 p-6 shadow-sm animate-fade-in-up" style="animation-delay:100ms;">
                <h3 class="text-warning-ink mb-1 ui-form-title">Libur sekolah di {{ $selectedMonthLabel }}</h3>
                <p class="text-theme-xs font-normal text-warning-ink mb-4">Daftar libur resmi yang terdaftar, tidak masuk dalam hitungan absensi.</p>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($schoolHolidays as $holiday)
                        <div class="rounded-2xl bg-surface border border-warning-line p-4">
                            <p class="text-theme-xs font-normal text-soft">
                                {{ $holiday->holiday_date->locale('id')->translatedFormat('l, d F Y') }}
                            </p>
                            <p class="text-theme-sm font-medium text-heading mt-1">{{ $holiday->title }}</p>
                            @if ($holiday->description)
                                <p class="mt-1 text-theme-xs text-muted font-normal">{{ $holiday->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Student Search -->
        <div class="mb-6 rounded-2xl glass-card p-4 animate-fade-in-up" style="animation-delay:150ms;">
            <label for="student-filter" class="text-muted ui-form-label">Cari santri</label>
            <input
                id="student-filter"
                type="search"
                placeholder="Ketik nama atau NIS santri..."
                class="mt-2 w-full rounded-2xl border-2 border-line bg-surface/50 px-4 py-2.5 shadow-sm outline-none transition-all placeholder:text-soft focus:border-brand-500 focus:bg-surface focus:ring-4 focus:ring-brand-500/10 text-theme-sm font-normal"
            >
            @csrf
        </div>

        <!-- Attendance Grid -->
        @php
            $initialAttendances = [];
            foreach ($enrollments as $enrollment) {
                foreach ($days as $day) {
                    $attendance = $attendances->get($enrollment->id.'-'.$day->toDateString());
                    $initialAttendances[$enrollment->id.'_'.$day->toDateString()] = old(
                        'attendance.'.$enrollment->id.'.'.$day->toDateString(),
                        \App\Models\StudentAttendance::codeFromStatus($attendance?->status)
                    );
                }
            }
        @endphp
        <div x-data="attendanceManager('{{ route('attendance.update-single', $classroomTerm) }}')" x-init="selectedDay = @js($defaultSelectedDay)" class="attendance-grid-card rounded-2xl glass-card shadow-sm animate-fade-in-up relative" style="--attendance-grid-width: calc(var(--attendance-student-column-width) + {{ $days->count() * 70 + 192 }}px); animation-delay:200ms;">

            {{-- ===== Desktop matrix (hidden on mobile) ===== --}}
            <div class="hidden md:block">
            <div class="attendance-sticky-head" data-attendance-sticky-header aria-hidden="true">
                <div class="attendance-sticky-head-content" data-attendance-sticky-header-content>
                    <table class="attendance-grid-table text-left text-theme-sm whitespace-nowrap">
                        <colgroup>
                            <col style="width:var(--attendance-student-column-width)">
                            @foreach ($days as $day)
                                <col style="width:70px">
                            @endforeach
                            <col style="width:64px">
                            <col style="width:64px">
                            <col style="width:64px">
                        </colgroup>
                        <thead>
                            <tr class="bg-surface-subtle border-b border-line text-theme-xs font-normal uppercase text-muted">
                                <th class="px-6 py-4 text-theme-xs font-medium">Santri</th>
                                @foreach ($days as $day)
                                    <th @class(['px-2 py-2 text-center', 'attendance-today-heading' => $day->toDateString() === $todayWib]) @if ($day->toDateString() === $todayWib) data-attendance-today-header @endif>
                                        <span class="attendance-day-number block font-medium text-body text-theme-sm">{{ $day->format('d') }}</span>
                                        <span class="block text-theme-xs font-normal mt-0.5 text-soft">{{ $day->locale('id')->translatedFormat('l') }}</span>
                                        @if ($day->toDateString() === $todayWib)<span class="attendance-today-label">Hari ini</span>@endif
                                    </th>
                                @endforeach
                                <th class="px-4 py-2 text-center text-theme-xs font-medium">S</th>
                                <th class="px-4 py-2 text-center text-theme-xs font-medium">I</th>
                                <th class="px-4 py-2 text-center text-theme-xs font-medium">A</th>
                            </tr>
                        </thead>
                    </table>
                </div>
                <div class="attendance-sticky-name"><span>Santri</span><span class="attendance-sticky-month">{{ $selectedMonthLabel }}</span></div>
            </div>
            <div class="overflow-x-auto pb-20" data-attendance-scroll-area>
                <table class="attendance-grid-table text-left text-theme-sm whitespace-nowrap" data-attendance-table aria-label="Presensi santri {{ $selectedMonthLabel }}">
                    <colgroup>
                        <col style="width:var(--attendance-student-column-width)">
                        @foreach ($days as $day)
                            <col style="width:70px">
                        @endforeach
                        <col style="width:64px">
                        <col style="width:64px">
                        <col style="width:64px">
                    </colgroup>
                    <tbody id="attendance-rows" class="divide-y divide-line">
                        @foreach ($enrollments as $enrollment)
                            @php
                                $totals = $studentTotals[$enrollment->id] ?? ['sick' => 0, 'permission' => 0, 'absent' => 0];
                            @endphp
                            <tr class="hover:bg-surface-subtle/50 transition-colors" data-student="{{ \Illuminate\Support\Str::lower($enrollment->student?->name.' '.$enrollment->student?->nis) }}">
                                <td class="sticky left-0 z-10 bg-surface px-6 py-4 font-medium border-r border-line">
                                    <div class="text-heading text-theme-sm font-medium">{{ $enrollment->student?->name }}</div>
                                    <div class="text-theme-xs font-normal text-soft mt-0.5">NIS {{ $enrollment->student?->nis }}</div>
                                </td>
                                @foreach ($days as $day)
                                    @php
                                        $attendance = $attendances->get($enrollment->id.'-'.$day->toDateString());
                                        $code = old('attendance.'.$enrollment->id.'.'.$day->toDateString(), \App\Models\StudentAttendance::codeFromStatus($attendance?->status));
                                    @endphp
                                    <td @class(['px-2 py-3 text-center', 'attendance-today-cell' => $day->toDateString() === $todayWib])>
                                        <select
                                            aria-label="Presensi {{ $enrollment->student?->name }} pada {{ $day->locale('id')->translatedFormat('d F Y') }}"
                                            x-model="attendances['{{ $enrollment->id }}_{{ $day->toDateString() }}']"
                                            x-init="attendances['{{ $enrollment->id }}_{{ $day->toDateString() }}'] = '{{ $code }}'"
                                            @change="updateAttendance('{{ $enrollment->id }}', '{{ $day->toDateString() }}')"
                                            class="h-10 w-14 rounded-xl border-2 text-center outline-none transition-all focus:ring-4 cursor-pointer text-theme-sm font-normal"
                                            :class="{ 'bg-surface border-line text-body focus:border-line-strong focus:ring-line': attendances['{{ $enrollment->id }}_{{ $day->toDateString() }}'] === 'H', 'bg-warning-soft-strong border-warning-line text-warning-ink focus:border-warning-400 focus:ring-warning-line': attendances['{{ $enrollment->id }}_{{ $day->toDateString() }}'] === 'S', 'bg-success-soft-strong border-success-line text-success-ink focus:border-success-400 focus:ring-success-line': attendances['{{ $enrollment->id }}_{{ $day->toDateString() }}'] === 'I', 'bg-danger-soft-strong border-danger-line text-danger-ink focus:border-danger-400 focus:ring-danger-line': attendances['{{ $enrollment->id }}_{{ $day->toDateString() }}'] === 'A', 'bg-info-soft-strong border-info-line text-info-ink focus:border-info-400 focus:ring-info-line': attendances['{{ $enrollment->id }}_{{ $day->toDateString() }}'] === 'L', } text-theme-sm font-normal"
                                            @disabled(! $canUpdate)
                                        >
                                            @foreach (\App\Models\StudentAttendance::codeOptions() as $optionCode => $label)
                                                <option value="{{ $optionCode }}">{{ $optionCode }} — {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-center font-medium text-danger-ink text-theme-sm" x-text="studentTotals['{{ $enrollment->id }}']?.sick ?? {{ $totals['sick'] }}"></td>
                                <td class="px-4 py-3 text-center font-medium text-info-ink text-theme-sm" x-text="studentTotals['{{ $enrollment->id }}']?.permission ?? {{ $totals['permission'] }}"></td>
                                <td class="px-4 py-3 text-center font-medium text-body text-theme-sm" x-text="studentTotals['{{ $enrollment->id }}']?.absent ?? {{ $totals['absent'] }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>{{-- end desktop matrix --}}

            {{-- ===== Mobile view: Per Hari ===== --}}
            <div class="md:hidden pb-20">
                {{-- Day picker strip --}}
                <div class="attendance-day-picker border-b border-line px-4 pt-4 pb-3">
                    <div class="flex items-center gap-2 overflow-x-auto">
                        <span class="shrink-0 text-theme-xs font-normal uppercase text-soft mr-1">Tanggal · {{ $selectedMonthLabel }}</span>
                        @foreach ($days as $day)
                            <button
                                type="button"
                                id="day-{{ $day->toDateString() }}"
                                @click="selectedDay = '{{ $day->toDateString() }}'"
                                :class="selectedDay === '{{ $day->toDateString() }}' ? 'bg-warning-600 text-white border-warning-600 shadow-md' : 'bg-surface text-body border-line hover:border-warning-line'"
                                class="shrink-0 min-w-[3.25rem] rounded-2xl border-2 px-3 py-2 text-center transition-all"
                            >
                                <span class="block text-theme-sm font-medium">{{ $day->format('d') }}</span>
                                <span class="block text-theme-xs font-normal mt-0.5 opacity-80">{{ $day->locale('id')->translatedFormat('D') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-b border-line bg-surface-subtle/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-theme-xs font-normal text-heading">Input cepat</p><p class="mt-0.5 text-theme-xs font-normal text-muted">Tandai semua santri pada tanggal yang dipilih.</p></div>
                    <button type="button" @click="markSelectedDay('H')" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-success-600 px-4 text-white shadow-sm transition hover:bg-success-700 text-theme-sm font-medium" @disabled(! $canUpdate)>Tandai semua hadir</button>
                </div>

                {{-- Student cards for selected day --}}
                <div id="attendance-rows-mobile" class="divide-y divide-line">
                    @foreach ($enrollments as $enrollment)
                        @php
                            $totals = $studentTotals[$enrollment->id] ?? ['sick' => 0, 'permission' => 0, 'absent' => 0];
                        @endphp
                        <div class="p-4" data-student="{{ \Illuminate\Support\Str::lower($enrollment->student?->name.' '.$enrollment->student?->nis) }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-heading text-theme-sm font-medium truncate">{{ $enrollment->student?->name }}</div>
                                    <div class="text-theme-xs font-normal text-soft mt-0.5">NIS {{ $enrollment->student?->nis }}</div>
                                </div>
                                <div class="shrink-0 flex gap-1 text-theme-xs font-normal text-center">
                                    <span class="rounded-md bg-warning-soft text-warning-ink px-1.5 py-0.5">S <span x-text="studentTotals['{{ $enrollment->id }}']?.sick ?? {{ $totals['sick'] }}"></span></span>
                                    <span class="rounded-md bg-success-soft text-success-ink px-1.5 py-0.5">I <span x-text="studentTotals['{{ $enrollment->id }}']?.permission ?? {{ $totals['permission'] }}"></span></span>
                                    <span class="rounded-md bg-surface-muted text-body px-1.5 py-0.5">A <span x-text="studentTotals['{{ $enrollment->id }}']?.absent ?? {{ $totals['absent'] }}"></span></span>
                                </div>
                            </div>

                            {{-- H/S/I/A segmented buttons bound to selectedDay --}}
                            <div class="mt-3 grid grid-cols-5 gap-2">
                                @php
                                    $codes = [
                                        'H' => ['Hadir', 'bg-surface border-line text-body'],
                                        'S' => ['Sakit', 'bg-warning-500 border-warning-500 text-white'],
                                        'I' => ['Izin', 'bg-success-500 border-success-500 text-white'],
                                        'A' => ['Alpa', 'bg-danger-500 border-danger-500 text-white'],
                                        'L' => ['Libur', 'bg-info-500 border-info-500 text-white'],
                                    ];
                                @endphp
                                @foreach ($codes as $code => [$label, $activeClass])
                                    <button
                                        type="button"
                                        @click="attendances['{{ $enrollment->id }}_' + selectedDay] = '{{ $code }}'; updateAttendance('{{ $enrollment->id }}', selectedDay)"
                                        :class="attendances['{{ $enrollment->id }}_' + selectedDay] === '{{ $code }}' ? '{{ $activeClass }}' : 'bg-surface border-line text-muted'"
                                        class="rounded-xl border-2 py-2.5 transition-all text-theme-sm font-medium"
                                        @disabled(! $canUpdate)
                                    >{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>{{-- end mobile view --}}

            <!-- Footer Action Block -->
            <div class="sticky-action-bar flex flex-col items-stretch justify-between gap-3 border-t border-line/60 p-4 backdrop-blur-md sm:flex-row sm:items-center sm:p-5">
                <p class="text-theme-xs font-normal text-muted">Rekap ketidakhadiran (S/I/A) akan terakumulasi otomatis ke cetakan rapor santri.</p>
                <div class="flex items-center gap-2">
                    <span x-show="saveError" x-text="saveError" class="inline-feedback inline-feedback-error"></span>
                    <button x-show="saveError" type="button" @click="retryLastSave()" class="inline-flex min-h-11 items-center rounded-xl border border-danger-line bg-surface px-3 text-danger-ink text-theme-sm font-medium">Coba lagi</button>
                    <span x-show="isSaving" x-transition class="flex items-center gap-1.5 text-theme-xs font-normal text-warning-ink">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Menyimpan...
                    </span>
                    <span x-show="!isSaving && lastSaved" x-transition class="flex items-center gap-1.5 text-theme-xs font-normal text-success-ink bg-success-soft px-3 py-1.5 rounded-lg border border-success-line">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Tersimpan otomatis
                    </span>
                    @if(!$canUpdate)
                    <span class="rounded-xl px-4 py-2 text-theme-xs font-normal text-white bg-slate-350">
                        Mode Baca Saja
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </main>

        @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('attendanceManager', (endpointUrl) => ({
                    attendances: @js($initialAttendances),
                    enrollmentIds: @js($enrollments->pluck('id')->values()->all()),
                    selectedDay: '',
                    studentTotals: {},
                    pendingAttendanceSaves: {},
                    savingAttendanceKeys: {},
                    failedAttendanceSaves: {},
                    isSaving: false,
                    lastSaved: null,
                    saveError: null,

                    init() {
                        // Initialize totals on mount
                        setTimeout(() => this.recalculateTotals(), 100);
                        this.initializeStickyAttendanceHeader();
                        this.updateAttendanceStickyOffset();
                        window.addEventListener('resize', () => this.updateAttendanceStickyOffset(), { passive: true });
                        // Scroll the selected day chip into view (mobile strip)
                        this.$nextTick(() => {
                            this.focusTodayColumn();
                            document.getElementById('day-' + this.selectedDay)
                                ?.scrollIntoView({ inline: 'center', block: 'nearest' });
                        });
                    },

                    initializeStickyAttendanceHeader() {
                        const headerContent = this.$root.querySelector('[data-attendance-sticky-header-content]');
                        const scrollArea = this.$root.querySelector('[data-attendance-scroll-area]');

                        if (!headerContent || !scrollArea) return;

                        const synchronizeHorizontalScroll = () => {
                            headerContent.style.transform = `translateX(-${scrollArea.scrollLeft}px)`;
                        };

                        scrollArea.addEventListener('scroll', synchronizeHorizontalScroll, { passive: true });
                        synchronizeHorizontalScroll();
                    },

                    focusTodayColumn() {
                        const scrollArea = this.$root.querySelector('[data-attendance-scroll-area]');
                        const todayHeader = this.$root.querySelector('[data-attendance-today-header]');
                        const table = todayHeader?.closest('table');
                        const nameWidth = this.$root.querySelector('.attendance-sticky-name')?.offsetWidth ?? 0;

                        if (!scrollArea?.clientWidth || !table || !todayHeader) return;

                        const todayCenter = todayHeader.getBoundingClientRect().left
                            - table.getBoundingClientRect().left + todayHeader.offsetWidth / 2;
                        const visibleDateWidth = scrollArea.clientWidth - nameWidth;
                        scrollArea.scrollLeft = Math.max(0, todayCenter - nameWidth - visibleDateWidth / 2);
                    },

                    updateAttendanceStickyOffset() {
                        const portalHeader = document.querySelector('.school-header');
                        const headerHeight = portalHeader?.getBoundingClientRect().height;

                        if (headerHeight) {
                            document.documentElement.style.setProperty('--attendance-sticky-top', `${headerHeight}px`);
                        }
                    },

                    async updateAttendance(enrollmentId, date) {
                        const key = `${enrollmentId}_${date}`;
                        this.pendingAttendanceSaves[key] = this.attendances[key];
                        delete this.failedAttendanceSaves[key];

                        // Collapse rapid changes to one cell into a serialized save of the latest value.
                        if (this.savingAttendanceKeys[key]) return;

                        this.savingAttendanceKeys[key] = true;
                        this.isSaving = true;
                        if (Object.keys(this.failedAttendanceSaves).length === 0) this.saveError = null;

                        try {
                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                                          || document.querySelector('input[name="_token"]')?.value;

                            while (Object.prototype.hasOwnProperty.call(this.pendingAttendanceSaves, key)) {
                                const code = this.pendingAttendanceSaves[key];
                                delete this.pendingAttendanceSaves[key];

                                const response = await fetch(endpointUrl, {
                                    method: 'PUT',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': token,
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        class_enrollment_id: enrollmentId,
                                        date,
                                        code,
                                    })
                                });

                                if (!response.ok) throw new Error(`Gagal menyimpan (${response.status})`);

                                this.lastSaved = new Date();
                                this.recalculateTotals();
                            }

                            delete this.failedAttendanceSaves[key];
                            if (Object.keys(this.failedAttendanceSaves).length === 0) this.saveError = null;
                        } catch (error) {
                            console.error('Error saving attendance:', error);
                            this.pendingAttendanceSaves[key] = this.attendances[key];
                            this.failedAttendanceSaves[key] = { enrollmentId, date };
                            this.saveError = 'Gagal menyimpan. Periksa koneksi lalu coba lagi.';
                        } finally {
                            delete this.savingAttendanceKeys[key];
                            this.isSaving = Object.keys(this.savingAttendanceKeys).length > 0;
                        }
                    },

                    async retryLastSave() {
                        const failed = Object.values(this.failedAttendanceSaves);
                        this.failedAttendanceSaves = {};
                        this.saveError = null;
                        await Promise.all(failed.map(({ enrollmentId, date }) => this.updateAttendance(enrollmentId, date)));
                    },

                    async markSelectedDay(code) {
                        if (!this.selectedDay) return;
                        for (const enrollmentId of this.enrollmentIds) {
                            this.attendances[`${enrollmentId}_${this.selectedDay}`] = code;
                            await this.updateAttendance(enrollmentId, this.selectedDay);
                        }
                    },

                    recalculateTotals() {
                        let newTotals = {};
                        let classTotals = { sick: 0, permission: 0, absent: 0 };
                        
                        // Group by enrollment
                        Object.entries(this.attendances).forEach(([key, code]) => {
                            const enrollmentId = key.split('_')[0];
                            if (!newTotals[enrollmentId]) {
                                newTotals[enrollmentId] = { sick: 0, permission: 0, absent: 0 };
                            }
                            
                            if (code === 'S') { newTotals[enrollmentId].sick++; classTotals.sick++; }
                            if (code === 'I') { newTotals[enrollmentId].permission++; classTotals.permission++; }
                            if (code === 'A') { newTotals[enrollmentId].absent++; classTotals.absent++; }
                        });
                        
                        this.studentTotals = newTotals;
                        
                        // Dispatch event for class totals header (we'll listen to this globally if needed, 
                        // or just update DOM directly for simplicity since it's outside Alpine component)
                        document.getElementById('header-sick').textContent = classTotals.sick;
                        document.getElementById('header-permission').textContent = classTotals.permission;
                        document.getElementById('header-absent').textContent = classTotals.absent;
                    }
                }));
            });

            const filter = document.getElementById('student-filter');
            const rows = Array.from(document.querySelectorAll('#attendance-rows tr, #attendance-rows-mobile [data-student]'));

            filter?.addEventListener('input', () => {
                const value = filter.value.trim().toLowerCase();

                rows.forEach((row) => {
                    row.hidden = value.length > 0 && ! row.dataset.student.includes(value);
                });
            });
        </script>
        @endpush
    </div>
</x-layouts.portal>
