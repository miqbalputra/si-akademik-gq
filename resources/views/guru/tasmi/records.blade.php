<x-layouts.portal title="Riwayat Tasmi'" portalLabel="Portal Guru" breadcrumb="Riwayat Tasmi'">
    @push('styles')
    <style>















        table.tasmi-table { width:100%; border-collapse:collapse; font-size:14px; line-height:20px; font-weight:500; }
        table.tasmi-table th { text-align:left; text-transform:none; letter-spacing:normal; color:var(--ui-text); padding:10px 12px; border-bottom:1px solid var(--ui-line); background:var(--ui-surface-subtle); font-size:12px; line-height:18px; font-weight:500; }
        table.tasmi-table td { padding:12px; border-bottom:1px solid var(--ui-surface-muted); vertical-align:middle; }
        table.tasmi-table tr:hover td { background:var(--ui-brand-soft); }
    </style>
    @endpush

    <header class="fade-up" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <a href="{{ route('guru.tasmi.index') }}" style="color:var(--ui-brand-ink);display:inline-flex;align-items:center;gap:4px;margin-bottom:10px;text-decoration:none;font-size:14px;line-height:20px;font-weight:500;">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                    Dashboard Tasmi'
                </a>
                <h1 style="color:var(--ui-heading);margin:0 0 4px;letter-spacing:normal;font-size:20px;font-weight:600;line-height:28px;">Riwayat &amp; Laporan Tasmi'</h1>
                <p style="font-size:14px;color:var(--ui-muted);margin:0;line-height:20px;font-weight:400;">Semua record tasmi' yang Anda input. Klik baris untuk edit.</p>
            </div>
            <a href="{{ route('guru.tasmi.create') }}" class="btn btn-primary text-theme-sm font-medium">
                <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Input Baru
            </a>
        </div>
    </header>

    @if (session('status'))
        <div style="margin-bottom:18px;background:var(--ui-success-soft);border:1px solid var(--ui-success-line);border-radius:12px;padding:12px 16px;font-size:14px;font-weight:500;color:var(--ui-success-ink);line-height:20px;" class="fade-up">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" class="card fade-up delay-1" style="padding:16px;margin-bottom:18px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%, 150px),1fr));gap:10px;">
            <div>
                <label style="color:var(--ui-text);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Cari santri</label>
                <input type="text" name="search" class="form-input text-theme-sm font-normal" placeholder="Nama / NIS" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div>
                <label style="color:var(--ui-text);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Tipe</label>
                <select name="exam_type" class="form-input text-theme-sm font-normal">
                    <option value="">Semua</option>
                    @foreach($examTypeOptions as $value => $label)
                        <option value="{{ $value }}" @if(($filters['exam_type'] ?? '') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="color:var(--ui-text);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Predikat</label>
                <select name="predicate" class="form-input text-theme-sm font-normal">
                    <option value="">Semua</option>
                    @foreach($predicateOptions as $value => $label)
                        <option value="{{ $value }}" @if(($filters['predicate'] ?? '') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="color:var(--ui-text);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Dari tanggal</label>
                <input type="date" name="date_from" class="form-input text-theme-sm font-normal" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label style="color:var(--ui-text);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Sampai tanggal</label>
                <input type="date" name="date_until" class="form-input text-theme-sm font-normal" value="{{ $filters['date_until'] ?? '' }}">
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="btn btn-primary text-theme-sm font-medium">Filter</button>
                <a href="{{ route('guru.tasmi.records') }}" class="btn btn-outline text-theme-sm font-medium">Reset</a>
            </div>
        </div>
    </form>

    {{-- Tabel --}}
    <div class="card fade-up delay-2" style="overflow-x:auto;">
        @if($records->isEmpty())
            <div class="empty-state" style="margin:20px;">
                <p style="color:var(--ui-soft);font-size:14px;line-height:20px;font-weight:400;">Belum ada record tasmi' yang sesuai filter.</p>
            </div>
        @else
            <table class="tasmi-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Santri</th>
                        <th>Kelas</th>
                        <th>Tipe</th>
                        <th>Juz</th>
                        <th>Predikat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        <tr>
                            <td>
                                <div style="font-weight:500;color:var(--ui-heading);">{{ $record->exam_date?->locale('id')->translatedFormat('d M Y') }}</div>
                                @if($record->hijri_date)
                                    <div style="font-size:12px;color:var(--ui-muted);line-height:18px;font-weight:400;">{{ $record->hijri_date }}</div>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight:500;color:var(--ui-heading);">{{ $record->student?->name ?? '-' }}</div>
                                @if($record->student?->nis)
                                    <div style="font-size:12px;color:var(--ui-muted);line-height:18px;font-weight:400;">NIS {{ $record->student->nis }}</div>
                                @endif
                            </td>
                            <td>{{ $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? '-' }}</td>
                            <td><span class="badge badge-slate text-theme-xs font-medium">{{ $examTypeOptions[$record->exam_type] ?? $record->exam_type }}</span></td>
                            <td style="font-weight:500;color:var(--ui-heading);">{{ $record->juz_range_label }}</td>
                            <td><span class="badge predicate-{{ $record->predicate }} text-theme-xs font-medium">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span></td>
                            <td>
                                <a href="{{ route('guru.tasmi.edit', $record) }}" class="btn btn-outline text-theme-sm font-medium" style="padding:5px 10px;font-size:14px;line-height:20px;font-weight:500;">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding:14px 18px;">
                {{ $records->withQueryString()->links() }}
            </div>
        @endif
    </div>
</x-layouts.portal>