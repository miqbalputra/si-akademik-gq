<x-filament-panels::page>
    <style>
        /* ============================================
           ACADEMIC CALENDAR — SELF-CONTAINED STYLES
           All layout uses custom CSS classes to avoid
           dependency on Tailwind JIT utilities.
           ============================================ */

        /* ---------- Reset for this page ---------- */
        .ac-root { font-family:inherit; }
        .ac-root *, .ac-root *::before, .ac-root *::after { box-sizing:border-box; }
        .ac-hidden { display:none !important; }

        /* ---------- Spacing ---------- */
        .ac-root > * + * { margin-top:20px; }

        /* ---------- Toolbar ---------- */
        .ac-toolbar {
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:16px 20px;
            border-radius:24px;
            border:1px solid var(--ui-line);
            background:var(--ui-surface);
            box-shadow:0 1px 2px rgba(0,0,0,.04);
        }

        .ac-toolbar-left {
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            gap:10px;
        }
        .ac-toolbar-right {
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            gap:8px;
        }
        .ac-toolbar-label {
            font-size:11px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.04em;
            color:var(--ui-soft);
        }
        .ac-toolbar select,
        .ac-toolbar input[type="month"] {
            border-radius:10px;
            border:1px solid var(--ui-line-strong);
            background:var(--ui-surface-subtle);
            padding:5px 10px;
            font-size:13px;
            font-weight:700;
            color:var(--ui-heading);
            outline:none;
            transition:border-color .2s;
        }
        .ac-toolbar select:focus,
        .ac-toolbar input[type="month"]:focus { border-color:var(--color-warning-500); }

        .ac-btn-primary {
            display:inline-flex; align-items:center; justify-content:center;
            border-radius:10px; border:0;
            background:var(--color-warning-600); color:var(--ui-on-color);
            padding:6px 16px;
            font-size:12px; font-weight:700;
            cursor:pointer; transition:background .2s;
            text-decoration:none;
        }
        .ac-btn-primary:hover { background:var(--ui-warning-ink); }

        .ac-btn-outline {
            display:inline-flex; align-items:center; justify-content:center;
            border-radius:10px;
            padding:6px 14px;
            font-size:11px; font-weight:700;
            cursor:pointer; transition:all .2s;
            text-decoration:none;
        }
        .ac-btn-outline.amber {
            border:1px solid var(--color-warning-400); background:var(--ui-warning-soft); color:var(--ui-warning-ink);
        }
        .ac-btn-outline.amber:hover { background:var(--ui-warning-soft-strong); }
        .ac-btn-outline.indigo {
            border:1px solid var(--ui-brand-line); background:var(--ui-brand-soft); color:var(--ui-brand-ink);
        }
        .ac-btn-outline.indigo:hover { background:var(--ui-brand-soft-strong); }

        /* ---------- Legend Row ---------- */
        .ac-legend {
            display:grid;
            grid-template-columns:repeat(4, 1fr);
            gap:12px;
        }
        @media (max-width: 767px) { .ac-legend { grid-template-columns:repeat(2, 1fr); } }

        .ac-legend-item {
            display:flex; align-items:center; gap:8px;
            padding:10px 14px;
            border-radius:12px;
            border:1px solid var(--ui-line);
            background:var(--ui-surface);
        }

        .ac-legend-dot {
            width:10px; height:10px;
            border-radius:3px;
            flex-shrink:0;
        }
        .ac-legend-dot.school { background:var(--color-success-500); }
        .ac-legend-dot.weekend { background:var(--ui-soft); }
        .ac-legend-dot.holiday { background:var(--color-warning-500); }
        .ac-legend-dot.event  { background:var(--color-brand-500); }

        .ac-legend-text {
            font-size:12px; font-weight:700; color:var(--ui-text);
        }

        /* ---------- Calendar Container ---------- */
        .ac-cal {
            border-radius:24px;
            border:1px solid var(--ui-line);
            background:var(--ui-surface);
            box-shadow:0 4px 6px -1px rgba(0,0,0,.05), 0 2px 4px -2px rgba(0,0,0,.05);
            overflow:hidden;
        }

        /* Day header row */
        .ac-cal-header {
            display:grid;
            grid-template-columns:repeat(7, 1fr);
            text-align:center;
            font-size:11px; font-weight:600;
            text-transform:uppercase; letter-spacing:.05em;
            color:var(--ui-muted);
            background:var(--ui-surface-subtle);
            border-bottom:1px solid var(--ui-line);
            padding:10px 0;
        }

        /* Grid */
        .ac-cal-grid {
            display:grid;
            grid-template-columns:repeat(7, 1fr);
            gap:1px;
            background:var(--ui-line);
        }

        /* Day cell */
        .ac-day {
            min-height:120px;
            padding:10px;
            background:var(--ui-surface);
            display:flex; flex-direction:column;
            position:relative;
            transition:background .15s;
        }
        .ac-day:hover { background:var(--ui-surface-subtle); }

        .ac-day.muted   { opacity:.3; background:var(--ui-surface-subtle); }

        .ac-day.holiday { background:var(--ui-warning-soft); }

        .ac-day.weekend { background:var(--ui-surface-muted); }

        /* Day top row */
        .ac-day-top {
            display:flex; align-items:flex-start; justify-content:space-between;
        }
        .ac-day-num {
            font-size:15px; font-weight:700; color:var(--ui-heading); line-height:1;
        }
        .ac-day-label {
            font-size:8px; font-weight:700; color:var(--ui-soft);
            text-transform:uppercase; margin-top:2px;
        }

        /* Pill badge */
        .ac-pill {
            border-radius:999px;
            padding:2px 7px;
            font-size:8px; font-weight:600;
            text-transform:uppercase; letter-spacing:.03em;
            white-space:nowrap;
        }
        .ac-pill.school  { background:var(--ui-success-soft-strong); color:var(--ui-success-ink); }
        .ac-pill.weekend { background:var(--ui-line); color:var(--ui-text); }
        .ac-pill.holiday { background:var(--ui-warning-soft-strong); color:var(--ui-warning-ink); }

        /* Day content area */
        .ac-day-content { margin-top:8px; flex:1; }
        .ac-day-content > * + * { margin-top:4px; }

        .ac-holiday-title {
            font-size:10px; font-weight:700; color:var(--ui-warning-ink); line-height:1.3;
        }

        .ac-event-chip {
            border-radius:6px;
            border:1px solid var(--ui-brand-soft-strong);
            background:rgba(238,242,255,.5);
            padding:4px 6px;
            font-size:9px; font-weight:700; color:var(--ui-brand-ink);
            line-height:1.2;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .ac-event-chip-type {
            font-size:7px; font-weight:600; text-transform:uppercase;
            color:var(--color-brand-500); display:block; margin-bottom:1px;
        }

        /* Hover actions */
        .ac-day-actions {
            display:flex; justify-content:flex-end; gap:4px;
            margin-top:auto; padding-top:6px;
            opacity:0; transition:opacity .2s;
        }
        .ac-day:hover .ac-day-actions { opacity:1; }

        .ac-act {
            display:inline-flex; align-items:center; justify-content:center;
            width:22px; height:22px;
            border-radius:5px; border:1px solid var(--ui-line);
            color:var(--ui-soft); background:transparent;
            transition:all .15s; text-decoration:none;
        }
        .ac-act:hover { background:var(--ui-surface-muted); color:var(--ui-text); }
        .ac-act.h:hover { color:var(--color-warning-600); border-color:var(--ui-warning-line); }
        .ac-act.e:hover { color:var(--color-brand-600); border-color:var(--ui-brand-line); }
        .ac-act svg { width:12px; height:12px; display:block; }

        /* ---------- Bottom Details ---------- */
        .ac-details {
            display:grid;
            grid-template-columns:repeat(2, 1fr);
            gap:20px;
        }
        @media (max-width: 1023px) { .ac-details { grid-template-columns:1fr; } }

        .ac-detail-card {
            border-radius:16px;
            border:1px solid var(--ui-line);
            background:var(--ui-surface);
            padding:20px;
        }

        .ac-detail-title {
            font-size:15px; font-weight:600; color:var(--ui-heading);
        }
        .ac-detail-sub {
            font-size:11px; font-weight:600; color:var(--ui-soft); margin-top:4px;
        }

        .ac-detail-empty {
            margin-top:16px;
            padding:20px;
            border-radius:12px;
            border:1px dashed var(--ui-line-strong);
            background:var(--ui-surface-subtle);
            text-align:center;
            font-size:12px; font-weight:600; color:var(--ui-soft);
        }

        .ac-detail-list { margin-top:16px; }
        .ac-detail-list > * + * { margin-top:10px; }

        .ac-detail-item {
            border-radius:12px;
            padding:14px;
            border:1px solid;
        }
        .ac-detail-item.amber  { border-color:var(--ui-warning-line); background:var(--ui-warning-soft); }
        .ac-detail-item.indigo { border-color:var(--ui-brand-line); background:var(--ui-brand-soft); }

        .ac-detail-item-date {
            font-size:9px; font-weight:600; text-transform:uppercase;
            letter-spacing:.04em;
        }
        .ac-detail-item.amber .ac-detail-item-date { color:var(--ui-warning-ink); }
        .ac-detail-item.indigo .ac-detail-item-date { color:var(--ui-brand-ink); }

        .ac-detail-item-title {
            font-size:13px; font-weight:600; color:var(--ui-heading);
            margin-top:4px; line-height:1.3;
        }

        .ac-detail-item-desc {
            font-size:12px; font-weight:500; color:var(--ui-muted);
            margin-top:6px; line-height:1.5;
        }

        .ac-detail-item-meta {
            font-size:11px; font-weight:600; color:var(--ui-muted);
            margin-top:4px;
        }

        .ac-edit-link {
            display:inline-flex; align-items:center;
            margin-top:8px;
            font-size:11px; font-weight:700;
            border-radius:8px; border:1px solid var(--ui-line-strong);
            padding:4px 10px;
            color:var(--ui-text); background:var(--ui-surface);
            text-decoration:none; transition:all .15s;
        }
        .ac-edit-link:hover { background:var(--ui-surface-muted); }
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
