<x-layouts.portal title="Riwayat Tasmi'" portalLabel="Portal Guru" breadcrumb="Riwayat Tasmi'">
    @push('styles')
    <style>
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .empty-state { border: 2px dashed #e2e8f0; border-radius: 16px; padding: 40px 20px; text-align: center; }
        .form-input { width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:8px 13px; font-size:13px; font-weight:500; color:#1e293b; background:#f8fafc; outline:none; font-family:'Outfit',sans-serif; }
        .form-input:focus { border-color:#a855f7; background:#fff; }
        .predicate-maqbul { background: #f1f5f9; color: #475569; }
        .predicate-jayyid { background: #dbeafe; color: #1e40af; }
        .predicate-jayyid_jiddan { background: #dcfce7; color: #166534; }
        .predicate-mumtaz { background: #f3e8ff; color: #6b21a8; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; font-weight:700; font-size:12px; border-radius:8px; padding:6px 12px; transition: all .2s; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; }
        .btn-primary { background:#6b21a8; color:#fff; }
        .btn-primary:hover { background:#581c87; }
        .btn-outline { background:transparent; border:1.5px solid #e2e8f0; color:#475569; }
        .btn-outline:hover { background:#f8fafc; }
        table.tasmi-table { width:100%; border-collapse:collapse; font-size:13px; }
        table.tasmi-table th { text-align:left; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#475569; padding:10px 12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
        table.tasmi-table td { padding:12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        table.tasmi-table tr:hover td { background:#faf5ff; }
    </style>
    @endpush

    <header class="fade-up" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <a href="{{ route('guru.tasmi.index') }}" style="font-size:12px;font-weight:700;color:#6b21a8;display:inline-flex;align-items:center;gap:4px;margin-bottom:10px;text-decoration:none;">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                    Dashboard Tasmi'
                </a>
                <h1 style="font-size:24px;font-weight:900;color:#0f172a;margin:0 0 4px;letter-spacing:-.02em;">Riwayat &amp; Laporan Tasmi'</h1>
                <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">Semua record tasmi' yang Anda input. Klik baris untuk edit.</p>
            </div>
            <a href="{{ route('guru.tasmi.create') }}" class="btn btn-primary">
                <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Input Baru
            </a>
        </div>
    </header>

    @if (session('status'))
        <div style="margin-bottom:18px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px 16px;font-size:13px;font-weight:600;color:#166534;" class="fade-up">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" class="card fade-up delay-1" style="padding:16px;margin-bottom:18px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Cari santri</label>
                <input type="text" name="search" class="form-input" placeholder="Nama / NIS" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Tipe</label>
                <select name="exam_type" class="form-input">
                    <option value="">Semua</option>
                    @foreach($examTypeOptions as $value => $label)
                        <option value="{{ $value }}" @if(($filters['exam_type'] ?? '') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Predikat</label>
                <select name="predicate" class="form-input">
                    <option value="">Semua</option>
                    @foreach($predicateOptions as $value => $label)
                        <option value="{{ $value }}" @if(($filters['predicate'] ?? '') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Dari tanggal</label>
                <input type="date" name="date_from" class="form-input" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Sampai tanggal</label>
                <input type="date" name="date_until" class="form-input" value="{{ $filters['date_until'] ?? '' }}">
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('guru.tasmi.records') }}" class="btn btn-outline">Reset</a>
            </div>
        </div>
    </form>

    {{-- Tabel --}}
    <div class="card fade-up delay-2" style="overflow-x:auto;">
        @if($records->isEmpty())
            <div class="empty-state" style="margin:20px;">
                <p style="color:#94a3b8;font-weight:600;font-size:14px;">Belum ada record tasmi' yang sesuai filter.</p>
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
                                <div style="font-weight:700;color:#0f172a;">{{ $record->exam_date?->locale('id')->translatedFormat('d M Y') }}</div>
                                @if($record->hijri_date)
                                    <div style="font-size:11px;color:#64748b;">{{ $record->hijri_date }}</div>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight:700;color:#0f172a;">{{ $record->student?->name ?? '-' }}</div>
                                @if($record->student?->nis)
                                    <div style="font-size:11px;color:#64748b;">NIS {{ $record->student->nis }}</div>
                                @endif
                            </td>
                            <td>{{ $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? '-' }}</td>
                            <td><span class="badge badge-slate">{{ $examTypeOptions[$record->exam_type] ?? $record->exam_type }}</span></td>
                            <td style="font-weight:600;color:#0f172a;">{{ $record->juz_range_label }}</td>
                            <td><span class="badge predicate-{{ $record->predicate }}">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span></td>
                            <td>
                                <a href="{{ route('guru.tasmi.edit', $record) }}" class="btn btn-outline" style="font-size:11px;padding:5px 10px;">Edit</a>
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