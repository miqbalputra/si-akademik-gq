<x-layouts.portal title="UAS Tahfidz — {{ $halaqah->name }}" portalLabel="Portal Guru" breadcrumb="UAS Tahfidz">
    <x-slot name="navLinks">
        <a href="{{ route('guru.tahfidz.show', $halaqah) }}" class="btn btn-outline btn-sm">Input Pekanan</a>
        <a href="{{ route('guru.tahfidz.index') }}" class="btn btn-outline btn-sm hidden sm:inline-flex">
            Daftar Halaqah
        </a>
    </x-slot>

    @push('styles')
    <style>
        .card { background:var(--ui-surface); border:1px solid var(--ui-surface-muted); border-radius:16px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
        .score-input { width:100%; border:1.5px solid var(--ui-line); border-radius:7px; padding:5px 6px; font-size:12px; font-family:'Outfit', sans-serif; color:var(--ui-heading); background:var(--ui-surface-subtle); outline:none; text-align:center; transition:border-color .15s; }
        .score-input:focus { border-color:var(--color-warning-500); background:var(--ui-surface); box-shadow:0 0 0 3px rgba(245,158,11,.1); }
        .badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
        .badge-amber { background:var(--ui-warning-soft-strong); color:var(--ui-warning-ink); }
        .badge-slate { background:var(--ui-surface-muted); color:var(--ui-text); }
    </style>
    @endpush

    <!-- Header -->
    <header class="fade-up" style="margin-bottom:24px;">
        <div style="display:inline-flex;align-items:center;gap:6px;background:var(--ui-warning-soft-strong);border-radius:999px;padding:3px 12px;margin-bottom:10px;">
            <span style="font-size:11px;font-weight:700;color:var(--ui-warning-ink);text-transform:uppercase;letter-spacing:.05em;">Ujian Akhir Semester (UAS)</span>
        </div>
        <h1 style="font-size:24px;font-weight:700;color:var(--ui-heading);margin:0 0 4px;letter-spacing:-.02em;">UAS Tahfidz</h1>
        <p style="font-size:13px;color:var(--ui-muted);font-weight:500;margin:0;">{{ $halaqah->name }} &middot; {{ $halaqah->academicTerm?->name }}</p>
    </header>

        @if (session('status'))
            <div style="margin-bottom:20px;background:var(--ui-success-soft);border:1px solid var(--ui-success-line);border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:var(--ui-success-ink);display:flex;align-items:center;gap:8px;" class="fade-up">
                <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                {{ session('status') }}
            </div>
        @endif

        @if ($categories->isEmpty() || $days->isEmpty())
            <div class="card" style="padding:40px;text-align:center;">
                <svg style="width:40px;height:40px;color:var(--color-warning-400);margin:0 auto 14px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                <p style="font-size:14px;font-weight:700;color:var(--ui-heading);margin:0 0 6px;">Belum ada kategori UAS atau hari ujian.</p>
                <p style="font-size:13px;color:var(--ui-soft);font-weight:500;margin:0;">PJ Tahfidz perlu mengatur kategori UAS dan hari ujian di panel admin terlebih dahulu.</p>
            </div>
        @elseif ($members->isEmpty())
            <div class="card" style="padding:40px;text-align:center;">
                <p style="font-size:13px;font-weight:600;color:var(--ui-soft);">Belum ada santri aktif di halaqah ini.</p>
            </div>
        @else
            {{-- Category Info --}}
            <div class="card fade-up" style="padding:14px 20px;margin-bottom:20px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                <span style="font-size:12px;font-weight:700;color:var(--ui-muted);margin-right:4px;">Nilai Maks per Kategori:</span>
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
                                <tr style="background:var(--ui-surface-subtle);border-bottom:1px solid var(--ui-surface-muted);">
                                    <th style="padding:12px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--ui-soft);text-transform:uppercase;letter-spacing:.06em;position:sticky;left:0;z-index:20;background:var(--ui-surface-subtle);white-space:nowrap;">No</th>
                                    <th style="padding:12px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--ui-soft);text-transform:uppercase;letter-spacing:.06em;position:sticky;left:44px;z-index:20;background:var(--ui-surface-subtle);min-width:160px;">Santri</th>
                                    @foreach ($days as $day)
                                        <th colspan="{{ $categories->count() + 1 }}" style="padding:10px 14px;text-align:center;font-size:12px;font-weight:700;color:var(--ui-heading);border-left:2px solid var(--ui-line);background:var(--ui-warning-soft-strong);">
                                            {{ $day->label ?? 'Hari '.$day->day_number }}
                                            @if ($day->test_date)
                                                <span style="display:block;font-size:10px;font-weight:500;color:var(--ui-warning-ink);">{{ $day->test_date->format('d M Y') }}</span>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                                {{-- Category Sub-Header Row --}}
                                <tr style="background:var(--ui-surface-subtle);border-bottom:2px solid var(--ui-surface-muted);">
                                    <th style="position:sticky;left:0;z-index:20;background:var(--ui-surface-subtle);padding:8px 14px;"></th>
                                    <th style="position:sticky;left:44px;z-index:20;background:var(--ui-surface-subtle);padding:8px 14px;"></th>
                                    @foreach ($days as $day)
                                        @foreach ($categories as $cat)
                                            <th style="padding:6px 8px;text-align:center;font-size:10px;font-weight:700;color:var(--ui-muted);text-transform:uppercase;letter-spacing:.04em;border-left:1px solid var(--ui-surface-muted);min-width:60px;" title="{{ $cat->name }} (max {{ $cat->max_score }})">
                                                {{ strtoupper(substr($cat->code, 0, 4)) }}
                                            </th>
                                        @endforeach
                                        <th style="padding:6px 8px;text-align:center;font-size:10px;font-weight:600;color:var(--color-warning-600);text-transform:uppercase;border-left:2px solid var(--ui-line);min-width:60px;">
                                            TOTAL
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    <tr style="border-top:1px solid var(--ui-surface-subtle);{{ $loop->even ? 'background:var(--ui-surface-subtle);' : 'background:var(--ui-surface);' }}">
                                        <td style="padding:10px 14px;color:var(--ui-soft);font-size:12px;font-weight:600;position:sticky;left:0;z-index:10;background:inherit;">{{ $loop->iteration }}</td>
                                        <td style="padding:10px 14px;font-weight:700;color:var(--ui-heading);position:sticky;left:44px;z-index:10;background:inherit;min-width:160px;">{{ $member->student->name }}</td>
                                        @foreach ($days as $day)
                                            @php $dayTotal = 0; @endphp
                                            @foreach ($categories as $cat)
                                                @php
                                                    $score = $scores->get($member->student_id.'-'.$day->id.'-'.$cat->id);
                                                    $val   = $score?->score !== null ? (float) $score->score : null;
                                                    if ($val !== null) $dayTotal += min($val, $cat->max_score);
                                                @endphp
                                                <td style="padding:8px 6px;border-left:1px solid var(--ui-surface-muted);">
                                                    <input type="number"
                                                        name="scores[{{ $member->student_id }}][{{ $day->id }}][{{ $cat->id }}]"
                                                        value="{{ $val }}"
                                                        placeholder="—"
                                                        step="0.01" min="0" max="{{ $cat->max_score }}"
                                                        class="score-input"
                                                        title="{{ $cat->name }} (max {{ $cat->max_score }})">
                                                </td>
                                            @endforeach
                                            <td style="padding:10px 8px;border-left:2px solid var(--ui-line);text-align:center;font-weight:600;font-size:13px;color:{{ $dayTotal > 0 ? 'var(--color-warning-600)' : 'var(--ui-line-strong)' }};">
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