<x-layouts.portal title="UAS Tahfidz — {{ $halaqah->name }}" portalLabel="Portal Guru" breadcrumb="UAS Tahfidz">
    <x-slot name="navLinks">
        <a href="{{ route('guru.tahfidz.show', $halaqah) }}" class="btn btn-outline btn-sm">Input Pekanan</a>
        <a href="{{ route('guru.tahfidz.index') }}" class="btn btn-outline btn-sm hidden sm:inline-flex">
            Daftar Halaqah
        </a>
    </x-slot>

    @push('styles')
    <style>
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .score-input { width: 100%; border: 1.5px solid #e2e8f0; border-radius: 7px; padding: 5px 6px; font-size: 12px; font-family: 'Outfit', sans-serif; color: #1e293b; background: #f8fafc; outline: none; text-align: center; transition: border-color .15s; }
        .score-input:focus { border-color: #f59e0b; background: #fff; box-shadow: 0 0 0 3px rgba(245,158,11,.1); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-slate { background: #f1f5f9; color: #475569; }
    </style>
    @endpush

    <!-- Header -->
    <header class="fade-up" style="margin-bottom:24px;">
        <div style="display:inline-flex;align-items:center;gap:6px;background:#fef3c7;border-radius:999px;padding:3px 12px;margin-bottom:10px;">
            <span style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.05em;">Ujian Akhir Semester (UAS)</span>
        </div>
        <h1 style="font-size:24px;font-weight:900;color:#0f172a;margin:0 0 4px;letter-spacing:-.02em;">UAS Tahfidz</h1>
        <p style="font-size:13px;color:#64748b;font-weight:500;margin:0;">{{ $halaqah->name }} &middot; {{ $halaqah->academicTerm?->name }}</p>
    </header>

        @if (session('status'))
            <div style="margin-bottom:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:#166534;display:flex;align-items:center;gap:8px;" class="fade-up">
                <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                {{ session('status') }}
            </div>
        @endif

        @if ($categories->isEmpty() || $days->isEmpty())
            <div class="card" style="padding:40px;text-align:center;">
                <svg style="width:40px;height:40px;color:#fbbf24;margin:0 auto 14px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                <p style="font-size:14px;font-weight:700;color:#0f172a;margin:0 0 6px;">Belum ada kategori UAS atau hari ujian.</p>
                <p style="font-size:13px;color:#94a3b8;font-weight:500;margin:0;">PJ Tahfidz perlu mengatur kategori UAS dan hari ujian di panel admin terlebih dahulu.</p>
            </div>
        @elseif ($members->isEmpty())
            <div class="card" style="padding:40px;text-align:center;">
                <p style="font-size:13px;font-weight:600;color:#94a3b8;">Belum ada santri aktif di halaqah ini.</p>
            </div>
        @else
            {{-- Category Info --}}
            <div class="card fade-up" style="padding:14px 20px;margin-bottom:20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                <span style="font-size:12px;font-weight:700;color:#64748b;margin-right:4px;">Nilai Maks per Kategori:</span>
                @foreach ($categories as $cat)
                    <span class="badge badge-slate">{{ $cat->name }}: {{ $cat->max_score }}</span>
                @endforeach
                <span class="badge badge-amber">Total/hari: {{ $categories->sum('max_score') }}</span>
            </div>

            <form method="POST" action="{{ route('guru.tahfidz.uas.update', $halaqah) }}">
                @csrf

                <div class="card fade-up" style="overflow:hidden;margin-bottom:20px;">
                    <div style="overflow-x:auto;">
                        <table style="min-width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                {{-- Day Row --}}
                                <tr style="background:#f8fafc;border-bottom:1px solid #f1f5f9;">
                                    <th style="padding:12px 14px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;position:sticky;left:0;z-index:20;background:#f8fafc;white-space:nowrap;">No</th>
                                    <th style="padding:12px 14px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;position:sticky;left:44px;z-index:20;background:#f8fafc;min-width:160px;">Santri</th>
                                    @foreach ($days as $day)
                                        <th colspan="{{ $categories->count() + 1 }}" style="padding:10px 14px;text-align:center;font-size:12px;font-weight:700;color:#0f172a;border-left:2px solid #e2e8f0;background:#fef3c7;">
                                            {{ $day->label ?? 'Hari '.$day->day_number }}
                                            @if ($day->test_date)
                                                <span style="display:block;font-size:10px;font-weight:500;color:#92400e;">{{ $day->test_date->format('d M Y') }}</span>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                                {{-- Category Sub-Header Row --}}
                                <tr style="background:#fafaf8;border-bottom:2px solid #f1f5f9;">
                                    <th style="position:sticky;left:0;z-index:20;background:#fafaf8;padding:8px 14px;"></th>
                                    <th style="position:sticky;left:44px;z-index:20;background:#fafaf8;padding:8px 14px;"></th>
                                    @foreach ($days as $day)
                                        @foreach ($categories as $cat)
                                            <th style="padding:6px 8px;text-align:center;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;border-left:1px solid #f1f5f9;min-width:60px;" title="{{ $cat->name }} (max {{ $cat->max_score }})">
                                                {{ strtoupper(substr($cat->code, 0, 4)) }}
                                            </th>
                                        @endforeach
                                        <th style="padding:6px 8px;text-align:center;font-size:10px;font-weight:800;color:#d97706;text-transform:uppercase;border-left:2px solid #e2e8f0;min-width:60px;">
                                            TOTAL
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    <tr style="border-top:1px solid #f8fafc;{{ $loop->even ? 'background:#fafaf8;' : 'background:#fff;' }}">
                                        <td style="padding:10px 14px;color:#94a3b8;font-size:12px;font-weight:600;position:sticky;left:0;z-index:10;background:inherit;">{{ $loop->iteration }}</td>
                                        <td style="padding:10px 14px;font-weight:700;color:#0f172a;position:sticky;left:44px;z-index:10;background:inherit;min-width:160px;">{{ $member->student->name }}</td>
                                        @foreach ($days as $day)
                                            @php $dayTotal = 0; @endphp
                                            @foreach ($categories as $cat)
                                                @php
                                                    $score = $scores->get($member->student_id.'-'.$day->id.'-'.$cat->id);
                                                    $val   = $score?->score !== null ? (float) $score->score : null;
                                                    if ($val !== null) $dayTotal += min($val, $cat->max_score);
                                                @endphp
                                                <td style="padding:8px 6px;border-left:1px solid #f1f5f9;">
                                                    <input type="number"
                                                        name="scores[{{ $member->student_id }}][{{ $day->id }}][{{ $cat->id }}]"
                                                        value="{{ $val }}"
                                                        placeholder="—"
                                                        step="0.01" min="0" max="{{ $cat->max_score }}"
                                                        class="score-input"
                                                        title="{{ $cat->name }} (max {{ $cat->max_score }})">
                                                </td>
                                            @endforeach
                                            <td style="padding:10px 8px;border-left:2px solid #e2e8f0;text-align:center;font-weight:800;font-size:13px;color:{{ $dayTotal > 0 ? '#d97706' : '#cbd5e1' }};">
                                                {{ $dayTotal > 0 ? number_format($dayTotal, 0) : '—' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn-primary">
                        <svg style="width:15px;height:15px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        Simpan Nilai UAS
                    </button>
                </div>
            </form>
        @endif
</x-layouts.portal>