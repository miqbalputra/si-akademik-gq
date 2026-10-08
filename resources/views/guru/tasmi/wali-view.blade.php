<x-layouts.portal title="Tasmi' Kelas Saya" portalLabel="Portal Guru" breadcrumb="Tasmi' Kelas Saya">
    @push('styles')
    <style>














        table.tasmi-table { width:100%; border-collapse:collapse; font-size:14px; line-height:20px; font-weight:500; }
        table.tasmi-table th { text-align:left; text-transform:none; letter-spacing:normal; color:var(--ui-text); padding:10px 12px; border-bottom:1px solid var(--ui-line); background:var(--ui-surface-subtle); font-size:12px; line-height:18px; font-weight:500; }
        table.tasmi-table td { padding:12px; border-bottom:1px solid var(--ui-surface-muted); vertical-align:middle; }
        table.tasmi-table tr:hover td { background:var(--ui-info-soft); }
    </style>
    @endpush

    <header class="fade-up" style="margin-bottom:24px;">
        <div style="display:inline-flex;align-items:center;gap:6px;background:var(--ui-info-soft-strong);border-radius:999px;padding:4px 12px;margin-bottom:12px;">
            <svg style="width:12px;height:12px;color:var(--ui-info-ink);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--ui-info-ink)"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
            <span style="font-size:12px;color:var(--ui-info-ink);text-transform:uppercase;letter-spacing:normal;line-height:18px;font-weight:400;">Read-only · Wali Kelas</span>
        </div>
        <h1 style="color:var(--ui-heading);margin:0 0 4px;letter-spacing:normal;font-size:20px;font-weight:600;line-height:28px;">Tasmi' Kelas Saya</h1>
        <p style="font-size:14px;color:var(--ui-muted);margin:0;line-height:20px;font-weight:400;">Data ujian tasmi' santri di kelas yang Anda wali. Anda hanya bisa melihat (read-only).</p>
    </header>

    @if (session('status'))
        <div style="margin-bottom:18px;background:var(--ui-success-soft);border:1px solid var(--ui-success-line);border-radius:12px;padding:12px 16px;font-size:14px;font-weight:500;color:var(--ui-success-ink);line-height:20px;" class="fade-up">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" class="card fade-up delay-1" style="padding:16px;margin-bottom:18px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%, 140px),1fr));gap:10px;">
            <div>
                <label style="color:var(--ui-text);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Kelas</label>
                <select name="classroom_term_id" class="form-input text-theme-sm font-normal">
                    <option value="">Semua kelas saya</option>
                    @foreach($homeroomClassroomTerms as $ct)
                        <option value="{{ $ct->id }}" @if(($filters['classroom_term_id'] ?? '') === (string)$ct->id) selected @endif>{{ $ct->classroom?->name ?? $ct->name }}</option>
                    @endforeach
                </select>
            </div>
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
                <label style="color:var(--ui-text);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Dari</label>
                <input type="date" name="date_from" class="form-input text-theme-sm font-normal" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label style="color:var(--ui-text);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Sampai</label>
                <input type="date" name="date_until" class="form-input text-theme-sm font-normal" value="{{ $filters['date_until'] ?? '' }}">
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="btn btn-outline text-theme-sm font-medium" style="background:var(--color-info-500);color:var(--ui-on-color);border-color:var(--color-info-500);">Filter</button>
                <a href="{{ route('guru.tasmi-wali.index') }}" class="btn btn-outline text-theme-sm font-medium">Reset</a>
            </div>
        </div>
    </form>

    <div class="card fade-up delay-2" style="overflow-x:auto;">
        @if($records->isEmpty())
            <div class="empty-state" style="margin:20px;">
                <p style="color:var(--ui-soft);font-size:14px;line-height:20px;font-weight:400;">Belum ada record tasmi' untuk santri di kelas yang Anda wali.</p>
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
                            <td>{{ $record->classroomTerm?->classroom?->name ?? '-' }}</td>
                            <td><span class="badge badge-slate text-theme-xs font-medium">{{ $examTypeOptions[$record->exam_type] ?? $record->exam_type }}</span></td>
                            <td style="font-weight:500;color:var(--ui-heading);">{{ $record->juz_range_label }}</td>
                            <td><span class="badge predicate-{{ $record->predicate }} text-theme-xs font-medium">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span></td>
                            <td style="color:var(--ui-text);font-weight:400;font-size:14px;line-height:20px;">{{ $record->examinerTeacher?->name ?? '-' }}</td>
                            <td>
                                <a href="{{ route('guru.tasmi-wali.show', $record) }}" class="btn btn-outline text-theme-sm font-medium" style="padding:5px 10px;font-size:14px;line-height:20px;font-weight:500;">Lihat</a>
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