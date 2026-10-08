@push('head')
    <title>Rekap Hafalan Tahfidz — Ruang GQ</title>
    <style>
        .bg-grid { background-size:40px 40px; background-image:linear-gradient(to right, rgb(16 24 40 / .025) 1px, transparent 1px), linear-gradient(to bottom, rgb(16 24 40 / .025) 1px, transparent 1px); }
        .student-tab { display:flex; flex-direction:column; align-items:center; border:1px solid var(--ui-line); border-radius:.875rem; background:var(--ui-surface); padding:1rem; text-decoration:none; transition:border-color .16s ease, background-color .16s ease, box-shadow .16s ease; }
        .student-tab:hover { border-color:var(--color-brand-300); box-shadow:0 8px 24px -18px rgb(16 24 40 / .28); }
        .student-tab.active { border-color:var(--color-brand-300); background:var(--color-brand-25); }
        .student-tab .name { color:var(--ui-heading); font-size:.875rem; font-weight:600; }
        .student-tab .nis { margin-top:.125rem; color:var(--ui-muted); font-size:.7rem; font-weight:500; }
        .section-title { display:flex; align-items:center; gap:.75rem; margin-bottom:1rem; }
        .section-title h2 { margin:0; color:var(--ui-heading); font-size:1rem; font-weight:650; white-space:nowrap; }
        .section-divider { height:1px; flex:1; background:var(--ui-line); }
        .stat-mini { border:1px solid var(--ui-line); border-radius:.875rem; background:var(--ui-surface); padding:1rem; text-align:center; }
        .stat-mini .label { margin:0 0 .25rem; color:var(--ui-muted); font-size:.68rem; font-weight:600; letter-spacing:.02em; text-transform:none; }
        .stat-mini .value { margin:0; color:var(--ui-heading); font-size:1.5rem; font-weight:700; }
        .stat-mini .sub { margin-top:.125rem; color:var(--color-brand-600); font-size:.7rem; font-weight:600; }
        .stat-mini-warning { border-color:var(--color-warning-200); background:var(--color-warning-50); }
        .chart-wrap { position:relative; min-width:0; border:1px solid var(--ui-line); border-radius:.875rem; background:var(--ui-surface); padding:1rem; }
        .chart-wrap canvas { display:block; max-width:100%; }
        .chart-wrap .chart-label { margin:0 0 .75rem; color:var(--ui-text); font-size:.8rem; font-weight:600; }
        .score-table { width:100%; border-collapse:collapse; font-size:.8125rem; }
        .score-table th { border-bottom:1px solid var(--ui-line); background:var(--ui-surface-subtle); padding:.75rem 1rem; color:var(--ui-muted); font-size:.68rem; font-weight:600; letter-spacing:.04em; text-align:left; text-transform:uppercase; }
        .score-table td { border-top:1px solid var(--ui-surface-muted); padding:.75rem 1rem; vertical-align:middle; }
        .score-table tr:hover td { background:var(--ui-surface-subtle); }
        .score-badge { display:inline-block; border-radius:999px; background:var(--color-brand-50); padding:.125rem .625rem; color:var(--color-brand-700); font-size:.75rem; font-weight:600; }
        @keyframes fadeInUp { 0%{opacity:0;transform:translateY(14px)} 100%{opacity:1;transform:translateY(0)} }
        .fade-up { animation:fadeInUp .5s cubic-bezier(.16,1,.3,1) forwards; opacity:0; }
        .delay-1 { animation-delay:.1s; }
        .delay-2 { animation-delay:.2s; }
    </style>
    @endpush

<x-layouts.portal title="Rekap Hafalan Tahfidz" portalLabel="Portal Wali Santri" breadcrumb="Tahfidz">
    <div class="fixed inset-0 z-[-1] bg-grid opacity-50"></div>

    <div class="mx-auto max-w-5xl">

        {{-- Header --}}
        <header class="portal-page-header fade-up">
            <div>
                <p class="eyebrow">Rekap Tahfidz</p>
                <h1>Hafalan Al-Qur'an</h1>
                <p class="mt-2 text-sm font-normal text-muted dark:text-muted">Pantau progres hafalan, nilai pekanan, manzil, dan UAS anak Anda.</p>
            </div>
        </header>

        {{-- Student Selector --}}
        @if ($students->count() > 1)
            <div class="mb-7 grid grid-cols-[repeat(auto-fill,minmax(min(100%,160px),1fr))] gap-3 fade-up">
                @foreach ($students as $student)
                    <a href="?student={{ $student->id }}" class="student-tab {{ $selectedStudent?->id === $student->id ? 'active' : '' }}">
                        <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft-strong text-base font-bold text-brand-ink dark:bg-brand-900/40 dark:text-brand-300">
                            {{ substr($student->name, 0, 1) }}
                        </div>
                        <span class="name">{{ $student->name }}</span>
                        <span class="nis">NIS {{ $student->nis }}</span>
                    </a>
                @endforeach
            </div>
        @elseif ($students->count() === 1)
            <div class="card fade-up mb-7 flex items-center gap-3 p-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft-strong text-lg font-bold text-brand-ink dark:bg-brand-900/40 dark:text-brand-300">
                    {{ substr($selectedStudent->name, 0, 1) }}
                </div>
                <div>
                    <div class="text-sm font-semibold text-heading dark:text-white">{{ $selectedStudent->name }}</div>
                    <div class="text-xs text-muted dark:text-muted">NIS: {{ $selectedStudent->nis }}</div>
                </div>
            </div>
        @else
            <div class="empty-state fade-up">
                <p class="text-sm font-medium text-muted dark:text-muted">Belum ada data anak yang terhubung dengan akun Anda.</p>
            </div>
        @endif

        @if ($selectedStudent && $students->isNotEmpty())

            {{-- Rekap Pekanan --}}
            <section style="margin-bottom:28px;" class="fade-up delay-1">
                <div class="section-title">
                    <h2>Rekap Pekanan</h2>
                    <div class="section-divider"></div>
                </div>

                @if ($weeklyScores->isEmpty())
                    <div class="card p-8 text-center">
                        <p class="text-sm font-medium text-muted dark:text-muted">Belum ada data pekanan untuk periode ini.</p>
                    </div>
                @else
                    <div class="mb-5 grid gap-4 md:grid-cols-2">
                        <div class="chart-wrap">
                            <p class="chart-label">📈 Nilai per Pekan</p>
                            <canvas id="chartWeekly" height="180"></canvas>
                        </div>
                        <div class="chart-wrap">
                            <p class="chart-label">📊 Total Baris Hafalan per Bulan</p>
                            <canvas id="chartBaris" height="180"></canvas>
                        </div>
                    </div>
                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                        <table class="score-table min-w-[44rem]">
                            <thead>
                                <tr>
                                    <th>Pekan</th>
                                    <th>Surat / Ayat</th>
                                    <th>Jml Baris</th>
                                    <th style="text-align:center;">Nilai</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($weeklyScores as $score)
                                    <tr>
                                        <td class="font-semibold text-heading dark:text-white">{{ $score->week?->date_label ?? 'Pekan '.$score->week?->week_number }}</td>
                                        <td class="text-body dark:text-body">{{ $score->surah_ayat ?? '—' }}</td>
                                        <td class="text-body dark:text-body">{{ $score->sabaq_amount ?? '—' }}</td>
                                        <td style="text-align:center;">
                                            @if ($score->score !== null)
                                                <span class="score-badge">{{ $score->score }}</span>
                                            @else
                                                <span class="font-semibold text-soft dark:text-soft">—</span>
                                            @endif
                                        </td>
                                        <td class="text-xs text-muted dark:text-muted">{{ $score->notes ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Rekap Bulanan --}}
            @if ($monthlyRecaps->isNotEmpty())
                <section style="margin-bottom:28px;" class="fade-up delay-1">
                    <div class="section-title">
                        <h2>Rekap Bulanan</h2>
                        <div class="section-divider"></div>
                    </div>
                    <div class="mb-5 grid gap-4 md:grid-cols-2">
                        <div class="chart-wrap">
                            <p class="chart-label">📈 Tren Nilai Rata-rata</p>
                            <canvas id="chartMonthlyAvg" height="180"></canvas>
                        </div>
                        <div class="chart-wrap">
                            <p class="chart-label">📊 Nilai Manzil per Bulan</p>
                            <canvas id="chartManzil" height="180"></canvas>
                        </div>
                    </div>
                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                        <table class="score-table min-w-[52rem]">
                            <thead>
                                <tr>
                                    <th>Bulan</th>
                                    <th>Sabaq Sebulan</th>
                                    <th style="text-align:center;">Rata-rata</th>
                                    <th>Total Hafalan</th>
                                    <th>Manzil</th>
                                    <th style="text-align:center;">Nilai Manzil</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($monthlyRecaps as $recap)
                                    <tr>
                                        <td class="font-semibold text-heading dark:text-white">{{ $recap->month_label ?? 'Bulan '.$recap->month_number }}</td>
                                        <td class="text-body dark:text-body">{{ $recap->sabaq_monthly ?? '—' }}</td>
                                        <td style="text-align:center;">
                                            @if ($recap->average_score !== null)
                                                <span class="score-badge">{{ $recap->average_score }}</span>
                                            @else
                                                <span class="text-soft dark:text-soft">—</span>
                                            @endif
                                        </td>
                                        <td class="text-body dark:text-body">{{ $recap->total_hafalan ?? '—' }}</td>
                                        <td class="text-body dark:text-body">{{ $recap->manzil_submitted ?? '—' }}</td>
                                        <td style="text-align:center;">{{ $recap->manzil_score ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Rekap Semester --}}
            @if ($semesterRecap || $uasResult)
                <section style="margin-bottom:28px;" class="fade-up delay-2">
                    <div class="section-title">
                        <h2>Rekap Semester</h2>
                        <div class="section-divider"></div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%, 160px),1fr));gap:14px;">
                        @if ($semesterRecap)
                            <div class="stat-mini">
                                <p class="label">Nilai Sabaq</p>
                                <p class="value">{{ $semesterRecap->sabaq_semester_score ?? '—' }}</p>
                                <p class="sub">{{ $semesterRecap->sabaq_category ?? '' }}</p>
                            </div>
                            <div class="stat-mini">
                                <p class="label">Nilai Manzil</p>
                                <p class="value">{{ $semesterRecap->manzil_average_score ?? '—' }}</p>
                                <p class="sub">{{ $semesterRecap->manzil_category ?? '' }}</p>
                            </div>
                        @endif
                        @if ($uasResult)
                            <div class="stat-mini stat-mini-warning">
                                <p class="label text-warning-ink dark:text-warning-300">UAS Tahfidz</p>
                                <p class="value text-warning-ink dark:text-warning-300">{{ $uasResult->final_score ?? '—' }}</p>
                                <p class="sub">{{ $uasResult->predicate ?? '' }}</p>
                            </div>
                            <div class="stat-mini">
                                <p class="label">Juz Ujian</p>
                                <p class="value" style="font-size:18px;">{{ $uasResult->juz_tested ?? '—' }}</p>
                            </div>
                        @endif
                    </div>

                    @if ($semesterRecap?->semester_notes)
                        <div class="mt-4 rounded-xl border border-warning-line bg-warning-soft p-4 dark:border-warning-800 dark:bg-warning-900/20">
                            <p class="mb-1.5 text-xs font-semibold text-warning-ink dark:text-warning-300">Catatan Guru Tahfidz</p>
                            <p class="m-0 text-sm leading-6 text-warning-ink dark:text-warning-200">{{ $semesterRecap->semester_notes }}</p>
                        </div>
                    @endif
                </section>
            @endif
        @endif
    </div>

    @if ($selectedStudent && $chartData)
    <script>
        (() => {
        const initialiseCharts = () => {
        if (typeof window.Chart !== 'function') return;
        const chartData = @json($chartData);
        const chartTheme = (name, alpha = 1) => {
            const hex = getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}-500`).trim() || '#465fff';
            const channels = hex.match(/[0-9a-f]{2}/gi)?.map((value) => parseInt(value, 16)) ?? [70, 95, 255];
            return `rgba(${channels[0]}, ${channels[1]}, ${channels[2]}, ${alpha})`;
        };
        const chartDefaults = { responsive: true, plugins: { legend: { display: false } } };
        const lineStyle = { borderWidth: 2.5, pointRadius: 4, tension: 0.4 };

        const ctxW  = document.getElementById('chartWeekly');
        const ctxB  = document.getElementById('chartBaris');
        const ctxMA = document.getElementById('chartMonthlyAvg');
        const ctxMN = document.getElementById('chartManzil');

        if (ctxW && chartData.weekly.length > 0) {
            new Chart(ctxW, { type: 'line', data: {
                labels: chartData.weekly.map(d => d.label),
                datasets: [{ ...lineStyle, label: 'Nilai', data: chartData.weekly.map(d => d.value), borderColor: chartTheme('warning'), backgroundColor: chartTheme('warning', 0.08), fill: true }]
            }, options: { ...chartDefaults, scales: { y: { min: 0, max: 100, ticks: { font: { family: 'Outfit', size: 11 } } }, x: { ticks: { font: { family: 'Outfit', size: 10 } } } } } });
        }
        if (ctxB && chartData.baris.length > 0) {
            new Chart(ctxB, { type: 'bar', data: {
                labels: chartData.baris.map(d => d.label),
                datasets: [{ label: 'Baris', data: chartData.baris.map(d => d.value), backgroundColor: chartTheme('info', 0.75), borderRadius: 6 }]
            }, options: { ...chartDefaults, scales: { x: { ticks: { font: { family: 'Outfit', size: 10 } } } } } });
        }
        if (ctxMA && chartData.monthly_avg.length > 0) {
            new Chart(ctxMA, { type: 'line', data: {
                labels: chartData.monthly_avg.map(d => d.label),
                datasets: [{ ...lineStyle, label: 'Rata-rata', data: chartData.monthly_avg.map(d => d.value), borderColor: chartTheme('success'), backgroundColor: chartTheme('success', 0.08), fill: true }]
            }, options: { ...chartDefaults, scales: { y: { min: 0, max: 100 }, x: { ticks: { font: { family: 'Outfit', size: 10 } } } } } });
        }
        if (ctxMN && chartData.manzil.length > 0) {
            new Chart(ctxMN, { type: 'bar', data: {
                labels: chartData.manzil.map(d => d.label),
                datasets: [{ label: 'Manzil', data: chartData.manzil.map(d => d.value), backgroundColor: chartTheme('primary', 0.75), borderRadius: 6 }]
            }, options: { ...chartDefaults, scales: { y: { min: 0, max: 100 }, x: { ticks: { font: { family: 'Outfit', size: 10 } } } } } });
        }
        };
        if (document.readyState === 'complete') initialiseCharts();
        else window.addEventListener('load', initialiseCharts, { once: true });
        })();
    </script>
    @endif
</x-layouts.portal>
