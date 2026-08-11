<x-layouts.portal title="Tasmi' Kelas Saya" portalLabel="Portal Guru" breadcrumb="Tasmi' Kelas Saya">
    @push('styles')
    <style>
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .empty-state { border: 2px dashed #e2e8f0; border-radius: 16px; padding: 40px 20px; text-align: center; }
        .form-input { width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:8px 13px; font-size:13px; font-weight:500; color:#1e293b; background:#f8fafc; outline:none; font-family:'Outfit',sans-serif; }
        .form-input:focus { border-color:#3b82f6; background:#fff; }
        .predicate-maqbul { background: #f1f5f9; color: #475569; }
        .predicate-jayyid { background: #dbeafe; color: #1e40af; }
        .predicate-jayyid_jiddan { background: #dcfce7; color: #166534; }
        .predicate-mumtaz { background: #f3e8ff; color: #6b21a8; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; font-weight:700; font-size:12px; border-radius:8px; padding:6px 12px; transition: all .2s; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; }
        .btn-outline { background:transparent; border:1.5px solid #e2e8f0; color:#475569; }
        .btn-outline:hover { background:#f8fafc; }
        table.tasmi-table { width:100%; border-collapse:collapse; font-size:13px; }
        table.tasmi-table th { text-align:left; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#475569; padding:10px 12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
        table.tasmi-table td { padding:12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        table.tasmi-table tr:hover td { background:#eff6ff; }
    </style>
    @endpush

    <header class="fade-up" style="margin-bottom:24px;">
        <div style="display:inline-flex;align-items:center;gap:6px;background:#dbeafe;border-radius:999px;padding:4px 12px;margin-bottom:12px;">
            <svg style="width:12px;height:12px;color:#1e40af;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#1e40af"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
            <span style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.05em;">Read-only · Wali Kelas</span>
        </div>
        <h1 style="font-size:24px;font-weight:900;color:#0f172a;margin:0 0 4px;letter-spacing:-.02em;">Tasmi' Kelas Saya</h1>
        <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">Data ujian tasmi' santri di kelas yang Anda wali. Anda hanya bisa melihat (read-only).</p>
    </header>

    @if (session('status'))
        <div style="margin-bottom:18px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px 16px;font-size:13px;font-weight:600;color:#166534;" class="fade-up">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" class="card fade-up delay-1" style="padding:16px;margin-bottom:18px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Kelas</label>
                <select name="classroom_term_id" class="form-input">
                    <option value="">Semua kelas saya</option>
                    @foreach($homeroomClassroomTerms as $ct)
                        <option value="{{ $ct->id }}" @if(($filters['classroom_term_id'] ?? '') === (string)$ct->id) selected @endif>{{ $ct->classroom?->name ?? $ct->name }}</option>
                    @endforeach
                </select>
            </div>
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
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Dari</label>
                <input type="date" name="date_from" class="form-input" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Sampai</label>
                <input type="date" name="date_until" class="form-input" value="{{ $filters['date_until'] ?? '' }}">
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="btn btn-outline" style="background:#3b82f6;color:#fff;border-color:#3b82f6;">Filter</button>
                <a href="{{ route('guru.tasmi-wali.index') }}" class="btn btn-outline">Reset</a>
            </div>
        </div>
    </form>

    <div class="card fade-up delay-2" style="overflow-x:auto;">
        @if($records->isEmpty())
            <div class="empty-state" style="margin:20px;">
                <p style="color:#94a3b8;font-weight:600;font-size:14px;">Belum ada record tasmi' untuk santri di kelas yang Anda wali.</p>
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
                        <th>Penguji</th>
                        <th>Detail</th>
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
                            <td>{{ $record->classroomTerm?->classroom?->name ?? '-' }}</td>
                            <td><span class="badge badge-slate">{{ $examTypeOptions[$record->exam_type] ?? $record->exam_type }}</span></td>
                            <td style="font-weight:600;color:#0f172a;">{{ $record->juz_range_label }}</td>
                            <td><span class="badge predicate-{{ $record->predicate }}">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span></td>
                            <td style="font-size:12px;color:#475569;">{{ $record->examinerTeacher?->name ?? '-' }}</td>
                            <td>
                                <a href="{{ route('guru.tasmi-wali.show', $record) }}" class="btn btn-outline" style="font-size:11px;padding:5px 10px;">Lihat</a>
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