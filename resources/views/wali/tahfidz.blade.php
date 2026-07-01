<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Hafalan Tahfidz — SIAKAD Griya Qur'an</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']},colors:{brand:{50:'#fffbeb',100:'#fef3c7',500:'#f59e0b',600:'#d97706',700:'#b45309'}}}}}</script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #1e293b; margin: 0; -webkit-font-smoothing: antialiased; }
        .bg-grid { background-size: 40px 40px; background-image: linear-gradient(to right, rgba(0,0,0,.025) 1px, transparent 1px), linear-gradient(to bottom, rgba(0,0,0,.025) 1px, transparent 1px); }
        .portal-nav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.95); backdrop-filter: blur(16px); border-bottom: 1px solid #f1f5f9; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .btn-ghost { background: transparent; border: 1.5px solid #e2e8f0; color: #475569; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all .2s; display: inline-flex; align-items: center; gap: 5px; font-family: 'Outfit', sans-serif; }
        .btn-ghost:hover { background: #f8fafc; border-color: #fde68a; color: #d97706; }
        .student-tab { display: flex; flex-direction: column; align-items: center; padding: 14px 16px; border-radius: 14px; border: 1.5px solid #e2e8f0; background: #fff; cursor: pointer; text-decoration: none; transition: all .2s; }
        .student-tab:hover { border-color: #fde68a; }
        .student-tab.active { border-color: #d97706; background: #fef3c7; }
        .student-tab .name { font-size: 14px; font-weight: 700; color: #0f172a; }
        .student-tab .nis  { font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 2px; }
        .student-tab.active .name { color: #92400e; }
        .section-title { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .section-title h2 { font-size: 17px; font-weight: 800; color: #0f172a; white-space: nowrap; margin: 0; }
        .section-divider { flex: 1; height: 1px; background: #f1f5f9; }
        .stat-mini { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; padding: 14px 16px; text-align: center; }
        .stat-mini .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin: 0 0 4px; }
        .stat-mini .value { font-size: 22px; font-weight: 900; color: #0f172a; margin: 0; }
        .stat-mini .sub   { font-size: 11px; font-weight: 700; color: #d97706; margin-top: 2px; }
        .chart-wrap { background: #fff; border: 1px solid #f1f5f9; border-radius: 14px; padding: 18px; }
        .chart-wrap .chart-label { font-size: 12px; font-weight: 700; color: #64748b; margin: 0 0 12px; }
        .score-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .score-table th { padding: 10px 16px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; background: #f8fafc; border-bottom: 2px solid #f1f5f9; }
        .score-table td { padding: 12px 16px; border-top: 1px solid #f8fafc; vertical-align: middle; }
        .score-table tr:hover td { background: #fafaf8; }
        .score-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #fef3c7; color: #92400e; }
        @keyframes fadeInUp { 0%{opacity:0;transform:translateY(14px)} 100%{opacity:1;transform:translateY(0)} }
        .fade-up { animation: fadeInUp .5s cubic-bezier(.16,1,.3,1) forwards; opacity: 0; }
        .delay-1 { animation-delay: .1s; }
        .delay-2 { animation-delay: .2s; }
    </style>
    @include('partials.pwa-head')
</head>
<body>
    <div class="fixed inset-0 z-[-1] bg-grid opacity-50"></div>

    {{-- Nav --}}
    <nav class="portal-nav">
        <div style="max-width:960px;margin:0 auto;padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;">
            <a href="{{ url('/') }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                <span style="width:36px;height:36px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;color:#fff;">GQ</span>
                <div>
                    <span style="display:block;font-size:14px;font-weight:800;color:#0f172a;line-height:1.2;">Griya Qur'an</span>
                    <span style="display:block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#d97706;">Portal Wali Santri</span>
                </div>
            </a>
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="{{ route('wali.dashboard') }}" class="btn-ghost">
                    <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                    Dashboard
                </a>
                <a href="{{ route('wali.calendar') }}" class="btn-ghost">Kalender</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Keluar</button>
                </form>
            </div>
        </div>
    </nav>

    <main style="max-width:960px;margin:0 auto;padding:28px 24px;">

        {{-- Header --}}
        <header class="fade-up" style="margin-bottom:28px;">
            <div style="display:inline-flex;align-items:center;gap:6px;background:#e0e7ff;border-radius:999px;padding:3px 12px;margin-bottom:10px;">
                <span style="font-size:11px;font-weight:700;color:#4338ca;text-transform:uppercase;letter-spacing:.05em;">Rekap Tahfidz</span>
            </div>
            <h1 style="font-size:26px;font-weight:900;color:#0f172a;margin:0 0 6px;letter-spacing:-.02em;">Hafalan Al-Qur'an</h1>
            <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">Pantau progres hafalan, nilai pekanan, manzil, dan UAS anak Anda.</p>
        </header>

        {{-- Student Selector --}}
        @if ($students->count() > 1)
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:28px;" class="fade-up">
                @foreach ($students as $student)
                    <a href="?student={{ $student->id }}" class="student-tab {{ $selectedStudent?->id === $student->id ? 'active' : '' }}">
                        <div style="width:40px;height:40px;background:{{ $selectedStudent?->id === $student->id ? '#fde68a' : '#f1f5f9' }};border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px;color:{{ $selectedStudent?->id === $student->id ? '#92400e' : '#64748b' }};margin-bottom:8px;">
                            {{ substr($student->name, 0, 1) }}
                        </div>
                        <span class="name">{{ $student->name }}</span>
                        <span class="nis">NIS {{ $student->nis }}</span>
                    </a>
                @endforeach
            </div>
        @elseif ($students->count() === 1)
            <div class="card fade-up" style="padding:16px 20px;margin-bottom:28px;display:flex;align-items:center;gap:14px;">
                <div style="width:44px;height:44px;background:#fef3c7;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:18px;color:#d97706;flex-shrink:0;">
                    {{ substr($selectedStudent->name, 0, 1) }}
                </div>
                <div>
                    <div style="font-size:15px;font-weight:800;color:#0f172a;">{{ $selectedStudent->name }}</div>
                    <div style="font-size:12px;color:#94a3b8;font-weight:500;">NIS: {{ $selectedStudent->nis }}</div>
                </div>
            </div>
        @else
            <div style="border:2px dashed #e2e8f0;border-radius:16px;padding:48px;text-align:center;" class="fade-up">
                <p style="color:#94a3b8;font-weight:600;font-size:14px;">Belum ada data anak yang terhubung dengan akun Anda.</p>
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
                    <div class="card" style="padding:32px;text-align:center;">
                        <p style="font-size:13px;font-weight:600;color:#94a3b8;">Belum ada data pekanan untuk periode ini.</p>
                    </div>
                @else
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:20px;">
                        <div class="chart-wrap">
                            <p class="chart-label">📈 Nilai per Pekan</p>
                            <canvas id="chartWeekly" height="180"></canvas>
                        </div>
                        <div class="chart-wrap">
                            <p class="chart-label">📊 Total Baris Hafalan per Bulan</p>
                            <canvas id="chartBaris" height="180"></canvas>
                        </div>
                    </div>
                    <div class="card" style="overflow:hidden;">
                        <table class="score-table">
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
                                        <td style="font-weight:600;color:#0f172a;">{{ $score->week?->date_label ?? 'Pekan '.$score->week?->week_number }}</td>
                                        <td style="color:#475569;">{{ $score->surah_ayat ?? '—' }}</td>
                                        <td style="color:#475569;">{{ $score->sabaq_amount ?? '—' }}</td>
                                        <td style="text-align:center;">
                                            @if ($score->score !== null)
                                                <span class="score-badge">{{ $score->score }}</span>
                                            @else
                                                <span style="color:#cbd5e1;font-weight:600;">—</span>
                                            @endif
                                        </td>
                                        <td style="color:#64748b;font-size:12px;">{{ $score->notes ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:20px;">
                        <div class="chart-wrap">
                            <p class="chart-label">📈 Tren Nilai Rata-rata</p>
                            <canvas id="chartMonthlyAvg" height="180"></canvas>
                        </div>
                        <div class="chart-wrap">
                            <p class="chart-label">📊 Nilai Manzil per Bulan</p>
                            <canvas id="chartManzil" height="180"></canvas>
                        </div>
                    </div>
                    <div class="card" style="overflow:hidden;">
                        <table class="score-table">
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
                                        <td style="font-weight:700;color:#0f172a;">{{ $recap->month_label ?? 'Bulan '.$recap->month_number }}</td>
                                        <td style="color:#475569;">{{ $recap->sabaq_monthly ?? '—' }}</td>
                                        <td style="text-align:center;">
                                            @if ($recap->average_score !== null)
                                                <span class="score-badge">{{ $recap->average_score }}</span>
                                            @else
                                                <span style="color:#cbd5e1;">—</span>
                                            @endif
                                        </td>
                                        <td style="color:#475569;">{{ $recap->total_hafalan ?? '—' }}</td>
                                        <td style="color:#475569;">{{ $recap->manzil_submitted ?? '—' }}</td>
                                        <td style="text-align:center;">{{ $recap->manzil_score ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;">
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
                            <div class="stat-mini" style="background:#fef3c7;border-color:#fde68a;">
                                <p class="label" style="color:#92400e;">UAS Tahfidz</p>
                                <p class="value" style="color:#92400e;">{{ $uasResult->final_score ?? '—' }}</p>
                                <p class="sub">{{ $uasResult->predicate ?? '' }}</p>
                            </div>
                            <div class="stat-mini">
                                <p class="label">Juz Ujian</p>
                                <p class="value" style="font-size:18px;">{{ $uasResult->juz_tested ?? '—' }}</p>
                            </div>
                        @endif
                    </div>

                    @if ($semesterRecap?->semester_notes)
                        <div style="margin-top:16px;background:#fef3c7;border:1px solid #fde68a;border-radius:12px;padding:16px 20px;">
                            <p style="font-size:12px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.04em;margin:0 0 6px;">Catatan Guru Tahfidz</p>
                            <p style="font-size:14px;color:#78350f;margin:0;line-height:1.6;">{{ $semesterRecap->semester_notes }}</p>
                        </div>
                    @endif
                </section>
            @endif
        @endif
    </main>

    @if ($selectedStudent && $chartData)
    <script>
        const chartData = @json($chartData);
        const chartDefaults = { responsive: true, plugins: { legend: { display: false } } };
        const lineStyle = { borderWidth: 2.5, pointRadius: 4, tension: 0.4 };

        const ctxW  = document.getElementById('chartWeekly');
        const ctxB  = document.getElementById('chartBaris');
        const ctxMA = document.getElementById('chartMonthlyAvg');
        const ctxMN = document.getElementById('chartManzil');

        if (ctxW && chartData.weekly.length > 0) {
            new Chart(ctxW, { type: 'line', data: {
                labels: chartData.weekly.map(d => d.label),
                datasets: [{ ...lineStyle, label: 'Nilai', data: chartData.weekly.map(d => d.value), borderColor: '#d97706', backgroundColor: 'rgba(217,119,6,0.08)', fill: true }]
            }, options: { ...chartDefaults, scales: { y: { min: 0, max: 100, ticks: { font: { family: 'Outfit', size: 11 } } }, x: { ticks: { font: { family: 'Outfit', size: 10 } } } } } });
        }
        if (ctxB && chartData.baris.length > 0) {
            new Chart(ctxB, { type: 'bar', data: {
                labels: chartData.baris.map(d => d.label),
                datasets: [{ label: 'Baris', data: chartData.baris.map(d => d.value), backgroundColor: 'rgba(14,165,233,0.75)', borderRadius: 6 }]
            }, options: { ...chartDefaults, scales: { x: { ticks: { font: { family: 'Outfit', size: 10 } } } } } });
        }
        if (ctxMA && chartData.monthly_avg.length > 0) {
            new Chart(ctxMA, { type: 'line', data: {
                labels: chartData.monthly_avg.map(d => d.label),
                datasets: [{ ...lineStyle, label: 'Rata-rata', data: chartData.monthly_avg.map(d => d.value), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', fill: true }]
            }, options: { ...chartDefaults, scales: { y: { min: 0, max: 100 }, x: { ticks: { font: { family: 'Outfit', size: 10 } } } } } });
        }
        if (ctxMN && chartData.manzil.length > 0) {
            new Chart(ctxMN, { type: 'bar', data: {
                labels: chartData.manzil.map(d => d.label),
                datasets: [{ label: 'Manzil', data: chartData.manzil.map(d => d.value), backgroundColor: 'rgba(139,92,246,0.75)', borderRadius: 6 }]
            }, options: { ...chartDefaults, scales: { y: { min: 0, max: 100 }, x: { ticks: { font: { family: 'Outfit', size: 10 } } } } } });
        }
    </script>
    @endif
</body>
</html>