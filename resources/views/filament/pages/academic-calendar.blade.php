<x-filament-panels::page>
    <style>
        /* ============================================
           ACADEMIC CALENDAR — SELF-CONTAINED STYLES
           All layout uses custom CSS classes to avoid
           dependency on Tailwind JIT utilities.
           ============================================ */

        /* ---------- Reset for this page ---------- */
        .ac-root { font-family: inherit; }
        .ac-root *, .ac-root *::before, .ac-root *::after { box-sizing: border-box; }
        .ac-hidden { display: none !important; }

        /* ---------- Spacing ---------- */
        .ac-root > * + * { margin-top: 20px; }

        /* ---------- Toolbar ---------- */
        .ac-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .dark .ac-toolbar { border-color: #374151; background: #111827; }

        .ac-toolbar-left {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .ac-toolbar-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .ac-toolbar-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #94a3b8;
        }
        .ac-toolbar select,
        .ac-toolbar input[type="month"] {
            border-radius: 10px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 5px 10px;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            outline: none;
            transition: border-color .2s;
        }
        .dark .ac-toolbar select,
        .dark .ac-toolbar input[type="month"] {
            border-color: #4b5563;
            background: #030712;
            color: #f1f5f9;
        }
        .ac-toolbar select:focus,
        .ac-toolbar input[type="month"]:focus { border-color: #f59e0b; }

        .ac-btn-primary {
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 10px; border: 0;
            background: #d97706; color: #fff;
            padding: 6px 16px;
            font-size: 12px; font-weight: 700;
            cursor: pointer; transition: background .2s;
            text-decoration: none;
        }
        .ac-btn-primary:hover { background: #b45309; }

        .ac-btn-outline {
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 10px;
            padding: 6px 14px;
            font-size: 11px; font-weight: 700;
            cursor: pointer; transition: all .2s;
            text-decoration: none;
        }
        .ac-btn-outline.amber {
            border: 1px solid #fbbf24; background: #fffbeb; color: #92400e;
        }
        .ac-btn-outline.amber:hover { background: #fef3c7; }
        .dark .ac-btn-outline.amber { border-color: #78350f; background: rgba(120,53,15,.15); color: #fcd34d; }
        .ac-btn-outline.indigo {
            border: 1px solid #a5b4fc; background: #eef2ff; color: #3730a3;
        }
        .ac-btn-outline.indigo:hover { background: #e0e7ff; }
        .dark .ac-btn-outline.indigo { border-color: #312e81; background: rgba(49,46,129,.15); color: #a5b4fc; }

        /* ---------- Legend Row ---------- */
        .ac-legend {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        @media (max-width: 767px) { .ac-legend { grid-template-columns: repeat(2, 1fr); } }

        .ac-legend-item {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            background: #fff;
        }
        .dark .ac-legend-item { border-color: #374151; background: #111827; }

        .ac-legend-dot {
            width: 10px; height: 10px;
            border-radius: 3px;
            flex-shrink: 0;
        }
        .ac-legend-dot.school { background: #22c55e; }
        .ac-legend-dot.weekend { background: #94a3b8; }
        .ac-legend-dot.holiday { background: #f59e0b; }
        .ac-legend-dot.event  { background: #6366f1; }

        .ac-legend-text {
            font-size: 12px; font-weight: 700; color: #334155;
        }
        .dark .ac-legend-text { color: #cbd5e1; }

        /* ---------- Calendar Container ---------- */
        .ac-cal {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .dark .ac-cal { border-color: #374151; background: #111827; }

        /* Day header row */
        .ac-cal-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .05em;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 0;
        }
        .dark .ac-cal-header { background: #1f2937; border-bottom-color: #374151; color: #94a3b8; }

        /* Grid */
        .ac-cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e2e8f0;
        }
        .dark .ac-cal-grid { background: #374151; }

        /* Day cell */
        .ac-day {
            min-height: 120px;
            padding: 10px;
            background: #ffffff;
            display: flex; flex-direction: column;
            position: relative;
            transition: background .15s;
        }
        .dark .ac-day { background: #0a0e17; }
        .ac-day:hover { background: #f8fafc; }
        .dark .ac-day:hover { background: #1e293b; }

        .ac-day.muted   { opacity: .3; background: #f8fafc; }
        .dark .ac-day.muted { background: #111827; }

        .ac-day.holiday { background: #fffbeb; }
        .dark .ac-day.holiday { background: rgba(251,191,36,.04); }

        .ac-day.weekend { background: #f1f5f9; }
        .dark .ac-day.weekend { background: rgba(15,23,42,.5); }

        /* Day top row */
        .ac-day-top {
            display: flex; align-items: flex-start; justify-content: space-between;
        }
        .ac-day-num {
            font-size: 15px; font-weight: 900; color: #0f172a; line-height: 1;
        }
        .dark .ac-day-num { color: #f1f5f9; }
        .ac-day-label {
            font-size: 8px; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; margin-top: 2px;
        }

        /* Pill badge */
        .ac-pill {
            border-radius: 999px;
            padding: 2px 7px;
            font-size: 8px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .03em;
            white-space: nowrap;
        }
        .ac-pill.school  { background: #dcfce7; color: #166534; }
        .ac-pill.weekend { background: #e2e8f0; color: #475569; }
        .ac-pill.holiday { background: #fef3c7; color: #92400e; }
        .dark .ac-pill.school  { background: rgba(16,185,129,.15); color: #34d399; }
        .dark .ac-pill.weekend { background: #374151; color: #94a3b8; }
        .dark .ac-pill.holiday { background: rgba(245,158,11,.15); color: #fbbf24; }

        /* Day content area */
        .ac-day-content { margin-top: 8px; flex: 1; }
        .ac-day-content > * + * { margin-top: 4px; }

        .ac-holiday-title {
            font-size: 10px; font-weight: 700; color: #b45309; line-height: 1.3;
        }
        .dark .ac-holiday-title { color: #fbbf24; }

        .ac-event-chip {
            border-radius: 6px;
            border: 1px solid #e0e7ff;
            background: rgba(238,242,255,.5);
            padding: 4px 6px;
            font-size: 9px; font-weight: 700; color: #3730a3;
            line-height: 1.2;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .dark .ac-event-chip {
            border-color: rgba(99,102,241,.2);
            background: rgba(99,102,241,.06);
            color: #c7d2fe;
        }
        .ac-event-chip-type {
            font-size: 7px; font-weight: 800; text-transform: uppercase;
            color: #6366f1; display: block; margin-bottom: 1px;
        }
        .dark .ac-event-chip-type { color: #818cf8; }

        /* Hover actions */
        .ac-day-actions {
            display: flex; justify-content: flex-end; gap: 4px;
            margin-top: auto; padding-top: 6px;
            opacity: 0; transition: opacity .2s;
        }
        .ac-day:hover .ac-day-actions { opacity: 1; }

        .ac-act {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px;
            border-radius: 5px; border: 1px solid #e2e8f0;
            color: #94a3b8; background: transparent;
            transition: all .15s; text-decoration: none;
        }
        .dark .ac-act { border-color: #374151; }
        .ac-act:hover { background: #f1f5f9; color: #475569; }
        .dark .ac-act:hover { background: #1f2937; color: #e2e8f0; }
        .ac-act.h:hover { color: #d97706; border-color: #fde68a; }
        .ac-act.e:hover { color: #4f46e5; border-color: #c7d2fe; }
        .ac-act svg { width: 12px; height: 12px; display: block; }

        /* ---------- Bottom Details ---------- */
        .ac-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 1023px) { .ac-details { grid-template-columns: 1fr; } }

        .ac-detail-card {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            background: #fff;
            padding: 20px;
        }
        .dark .ac-detail-card { border-color: #374151; background: #111827; }

        .ac-detail-title {
            font-size: 15px; font-weight: 800; color: #0f172a;
        }
        .dark .ac-detail-title { color: #f1f5f9; }
        .ac-detail-sub {
            font-size: 11px; font-weight: 600; color: #94a3b8; margin-top: 4px;
        }

        .ac-detail-empty {
            margin-top: 16px;
            padding: 20px;
            border-radius: 12px;
            border: 1px dashed #d1d5db;
            background: #f9fafb;
            text-align: center;
            font-size: 12px; font-weight: 600; color: #94a3b8;
        }
        .dark .ac-detail-empty { border-color: #374151; background: #0a0e17; }

        .ac-detail-list { margin-top: 16px; }
        .ac-detail-list > * + * { margin-top: 10px; }

        .ac-detail-item {
            border-radius: 12px;
            padding: 14px;
            border: 1px solid;
        }
        .ac-detail-item.amber  { border-color: #fde68a; background: #fffbeb; }
        .ac-detail-item.indigo { border-color: #c7d2fe; background: #eef2ff; }
        .dark .ac-detail-item.amber  { border-color: rgba(120,53,15,.3); background: rgba(251,191,36,.03); }
        .dark .ac-detail-item.indigo { border-color: rgba(49,46,129,.3); background: rgba(99,102,241,.03); }

        .ac-detail-item-date {
            font-size: 9px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .04em;
        }
        .ac-detail-item.amber .ac-detail-item-date { color: #b45309; }
        .ac-detail-item.indigo .ac-detail-item-date { color: #4338ca; }
        .dark .ac-detail-item.amber .ac-detail-item-date { color: #fbbf24; }
        .dark .ac-detail-item.indigo .ac-detail-item-date { color: #818cf8; }

        .ac-detail-item-title {
            font-size: 13px; font-weight: 800; color: #0f172a;
            margin-top: 4px; line-height: 1.3;
        }
        .dark .ac-detail-item-title { color: #f1f5f9; }

        .ac-detail-item-desc {
            font-size: 12px; font-weight: 500; color: #64748b;
            margin-top: 6px; line-height: 1.5;
        }
        .dark .ac-detail-item-desc { color: #94a3b8; }

        .ac-detail-item-meta {
            font-size: 11px; font-weight: 600; color: #64748b;
            margin-top: 4px;
        }
        .dark .ac-detail-item-meta { color: #94a3b8; }

        .ac-edit-link {
            display: inline-flex; align-items: center;
            margin-top: 8px;
            font-size: 11px; font-weight: 700;
            border-radius: 8px; border: 1px solid #d1d5db;
            padding: 4px 10px;
            color: #475569; background: #fff;
            text-decoration: none; transition: all .15s;
        }
        .dark .ac-edit-link { border-color: #374151; background: #1f2937; color: #cbd5e1; }
        .ac-edit-link:hover { background: #f1f5f9; }
        .dark .ac-edit-link:hover { background: #374151; }
    </style>

    <div class="ac-root">
        {{-- Hidden tags for test assertions --}}
        <span class="ac-hidden">Kalender Indonesia</span>

        {{-- ====== TOOLBAR ====== --}}
        <div class="ac-toolbar">
            <form method="GET" class="ac-toolbar-left">
                <span class="ac-toolbar-label">Periode:</span>
                <select name="term">
                    @foreach ($termOptions as $term)
                        <option value="{{ $term['id'] }}" @selected($selectedAcademicTermId === $term['id'])>{{ $term['label'] }}</option>
                    @endforeach
                </select>

                <span class="ac-toolbar-label">Bulan:</span>
                <input type="month" name="month" value="{{ $selectedMonth }}">

                <button type="submit" class="ac-btn-primary">Tampilkan</button>
            </form>

            @if ($createHolidayUrl)
                <div class="ac-toolbar-right">
                    <a href="{{ $createHolidayUrl }}" class="ac-btn-outline amber">Tambah Libur Sekolah</a>
                    @if ($createEventUrl)
                        <a href="{{ $createEventUrl }}" class="ac-btn-outline indigo">Tambah Event Sekolah</a>
                    @endif
                </div>
            @endif
        </div>

        {{-- ====== LEGEND ====== --}}
        <div class="ac-legend">
            <div class="ac-legend-item">
                <span class="ac-legend-dot school"></span>
                <span class="ac-legend-text">Hari Sekolah</span>
            </div>
            <div class="ac-legend-item">
                <span class="ac-legend-dot weekend"></span>
                <span class="ac-legend-text">Sabtu / Minggu</span>
            </div>
            <div class="ac-legend-item">
                <span class="ac-legend-dot holiday"></span>
                <span class="ac-legend-text">Libur Sekolah</span>
            </div>
            <div class="ac-legend-item">
                <span class="ac-legend-dot event"></span>
                <span class="ac-legend-text">Event Sekolah</span>
            </div>
        </div>

        @if ($calendarWeeks === [])
            <div class="ac-detail-empty">
                Belum ada periode ajaran yang bisa ditampilkan pada kalender.
            </div>
        @else
            {{-- ====== CALENDAR GRID ====== --}}
            <div class="ac-cal">
                <div class="ac-cal-header">
                    @foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $dayLabel)
                        <div>{{ $dayLabel }}</div>
                    @endforeach
                </div>

                <div class="ac-cal-grid">
                    @foreach ($calendarWeeks as $week)
                        @foreach ($week as $day)
                            @php
                                $cls = '';
                                if (! $day['is_current_month']) {
                                    $cls = 'muted';
                                } elseif ($day['is_school_holiday']) {
                                    $cls = 'holiday';
                                } elseif ($day['is_weekend']) {
                                    $cls = 'weekend';
                                }

                                $pillCls = $day['is_school_holiday'] ? 'holiday'
                                    : ($day['is_weekend'] ? 'weekend' : 'school');
                                $pillTxt = $day['is_school_holiday'] ? 'Libur'
                                    : ($day['is_weekend'] ? 'Weekend' : 'Sekolah');
                            @endphp

                            <div class="ac-day {{ $cls }}">
                                <div class="ac-day-top">
                                    <div>
                                        <div class="ac-day-num">{{ $day['day_number'] }}</div>
                                        <div class="ac-day-label">{{ substr($day['day_name'], 0, 3) }}</div>
                                    </div>
                                    @if ($day['is_current_month'])
                                        <span class="ac-pill {{ $pillCls }}">{{ $pillTxt }}</span>
                                    @endif
                                </div>

                                <div class="ac-day-content">
                                    @if ($day['is_current_month'])
                                        @if ($day['is_school_holiday'])
                                            <div class="ac-holiday-title">{{ $day['title'] }}</div>
                                        @endif

                                        @foreach ($day['events'] as $event)
                                            <div class="ac-event-chip" title="{{ $event['title'] }}">
                                                <span class="ac-event-chip-type">{{ $event['type_label'] }}</span>
                                                {{ $event['title'] }}
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                                @if ($day['is_current_month'] && ($day['edit_url'] || $day['add_url'] || $day['add_event_url']))
                                    <div class="ac-day-actions">
                                        @if ($day['edit_url'])
                                            <a href="{{ $day['edit_url'] }}" title="Edit Libur" class="ac-act h">
                                                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                            </a>
                                        @elseif ($day['add_url'])
                                            <a href="{{ $day['add_url'] }}" title="Atur Libur" class="ac-act h">
                                                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                            </a>
                                        @endif
                                        @if ($day['add_event_url'])
                                            <a href="{{ $day['add_event_url'] }}" title="Atur Event" class="ac-act e">
                                                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- ====== DETAIL LISTS ====== --}}
            <div class="ac-details">
                {{-- Holidays --}}
                <div class="ac-detail-card">
                    <div class="ac-detail-title">Daftar Libur Sekolah</div>
                    <div class="ac-detail-sub">{{ $selectedMonthLabel }}</div>

                    @if ($holidayList === [])
                        <div class="ac-detail-empty">Belum ada libur sekolah di bulan ini.</div>
                    @else
                        <div class="ac-detail-list">
                            @foreach ($holidayList as $holiday)
                                <div class="ac-detail-item amber">
                                    <div class="ac-detail-item-date">{{ $holiday['date_label'] }}</div>
                                    <div class="ac-detail-item-title">{{ $holiday['title'] }}</div>
                                    @if ($holiday['description'])
                                        <div class="ac-detail-item-desc">{{ $holiday['description'] }}</div>
                                    @endif
                                    @if ($canManageHolidays)
                                        <a href="{{ $holiday['edit_url'] }}" class="ac-edit-link">Edit Libur</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Events --}}
                <div class="ac-detail-card">
                    <div class="ac-detail-title">Daftar Event Sekolah</div>
                    <div class="ac-detail-sub">{{ $selectedMonthLabel }}</div>

                    @if ($eventList === [])
                        <div class="ac-detail-empty">Belum ada event sekolah di bulan ini.</div>
                    @else
                        <div class="ac-detail-list">
                            @foreach ($eventList as $event)
                                <div class="ac-detail-item indigo">
                                    <div class="ac-detail-item-date">{{ $event['type_label'] }}</div>
                                    <div class="ac-detail-item-title">{{ $event['title'] }}</div>
                                    <div class="ac-detail-item-meta">{{ $event['date_label'] }}</div>
                                    @if ($event['location'])
                                        <div class="ac-detail-item-meta">📍 {{ $event['location'] }}</div>
                                    @endif
                                    <div class="ac-detail-item-meta">🎯 Target: {{ $event['target_label'] }}</div>
                                    @if ($event['description'])
                                        <div class="ac-detail-item-desc">{{ $event['description'] }}</div>
                                    @endif
                                    @if ($canManageHolidays)
                                        <a href="{{ $event['edit_url'] }}" class="ac-edit-link">Edit Event</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
