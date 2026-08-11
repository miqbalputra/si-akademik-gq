<x-layouts.portal title="Detail Tasmi'" portalLabel="Portal Guru" breadcrumb="Detail Tasmi'">
    @push('styles')
    <style>
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .predicate-maqbul { background: #f1f5f9; color: #475569; }
        .predicate-jayyid { background: #dbeafe; color: #1e40af; }
        .predicate-jayyid_jiddan { background: #dcfce7; color: #166534; }
        .predicate-mumtaz { background: #f3e8ff; color: #6b21a8; }
        .detail-row { display:grid; grid-template-columns: 160px 1fr; gap:12px; padding:12px 0; border-bottom:1px solid #f1f5f9; }
        .detail-label { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
        .detail-value { font-size:14px; font-weight:600; color:#0f172a; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; font-weight:700; font-size:13px; border-radius:10px; padding:9px 18px; transition: all .2s; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; }
        .btn-outline { background:transparent; border:1.5px solid #e2e8f0; color:#475569; }
        .btn-outline:hover { background:#f8fafc; }
    </style>
    @endpush

    <header class="fade-up" style="margin-bottom:24px;">
        <a href="{{ route('guru.tasmi-wali.index') }}" style="font-size:12px;font-weight:700;color:#1e40af;display:inline-flex;align-items:center;gap:4px;margin-bottom:10px;text-decoration:none;">
            <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Tasmi' Kelas Saya
        </a>
        <div style="display:inline-flex;align-items:center;gap:6px;background:#dbeafe;border-radius:999px;padding:4px 12px;margin-bottom:10px;">
            <span style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.05em;">Read-only</span>
        </div>
        <h1 style="font-size:24px;font-weight:900;color:#0f172a;margin:0 0 4px;letter-spacing:-.02em;">Detail Tasmi'</h1>
        <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">Setoran tasmi' santri.</p>
    </header>

    <div class="card fade-up delay-1" style="padding:24px;margin-bottom:18px;">
        <div class="detail-row">
            <span class="detail-label">Santri</span>
            <span class="detail-value">{{ $record->student?->name ?? '-' }}@if($record->student?->nis) · NIS {{ $record->student->nis }}@endif</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Kelas</span>
            <span class="detail-value">{{ $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? '-' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Periode</span>
            <span class="detail-value">{{ $record->academicTerm?->name ?? '-' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Penguji (PJ Tasmi')</span>
            <span class="detail-value">{{ $record->examinerTeacher?->name ?? '-' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Jenis Ujian</span>
            <span class="detail-value">{{ \App\Models\TasmiRecord::examTypeOptions()[$record->exam_type] ?? $record->exam_type }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Juz</span>
            <span class="detail-value">{{ $record->juz_range_label }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Hari</span>
            <span class="detail-value">{{ $record->exam_day_label ?: '-' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Tanggal (Masehi)</span>
            <span class="detail-value">{{ $record->exam_date?->locale('id')->translatedFormat('l, d F Y') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Tanggal (Hijriyah)</span>
            <span class="detail-value">{{ $record->hijri_date ?: '-' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Predikat</span>
            <span><span class="badge predicate-{{ $record->predicate }}">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span></span>
        </div>
        <div class="detail-row" style="border-bottom:none;">
            <span class="detail-label">Catatan</span>
            <span class="detail-value" style="font-weight:500;">{{ $record->notes ?: '-' }}</span>
        </div>
    </div>

    <a href="{{ route('guru.tasmi-wali.index') }}" class="btn btn-outline">
        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
        Kembali
    </a>
</x-layouts.portal>