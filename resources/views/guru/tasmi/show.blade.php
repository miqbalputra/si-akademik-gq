<x-layouts.portal title="Detail Tasmi'" portalLabel="Portal Guru" breadcrumb="Detail Tasmi'">
    @push('styles')
    <style>





        .detail-row { display:grid; grid-template-columns:160px 1fr; gap:12px; padding:12px 0; border-bottom:1px solid var(--ui-surface-muted); }
        .detail-label {  text-transform:none; letter-spacing:normal; color:var(--ui-muted); font-size:12px; line-height:18px; font-weight:400; }
        .detail-value {  color:var(--ui-heading); font-size:14px; line-height:20px; font-weight:500; }



    </style>
    @endpush

    <header class="fade-up" style="margin-bottom:24px;">
        <a href="{{ route('guru.tasmi-wali.index') }}" style="color:var(--ui-info-ink);display:inline-flex;align-items:center;gap:4px;margin-bottom:10px;text-decoration:none;font-size:14px;line-height:20px;font-weight:500;">
            <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Tasmi' Kelas Saya
        </a>
        <div style="display:inline-flex;align-items:center;gap:6px;background:var(--ui-info-soft-strong);border-radius:999px;padding:4px 12px;margin-bottom:10px;">
            <span style="font-size:12px;color:var(--ui-info-ink);text-transform:uppercase;letter-spacing:normal;line-height:18px;font-weight:400;">Read-only</span>
        </div>
        <h1 style="color:var(--ui-heading);margin:0 0 4px;letter-spacing:normal;font-size:20px;font-weight:600;line-height:28px;">Detail Tasmi'</h1>
        <p style="font-size:14px;color:var(--ui-muted);margin:0;line-height:20px;font-weight:400;">Setoran tasmi' santri.</p>
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
            <span><span class="badge predicate-{{ $record->predicate }} text-theme-xs font-medium">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span></span>
        </div>
        <div class="detail-row" style="border-bottom:none;">
            <span class="detail-label">Catatan</span>
            <span class="detail-value" style="font-weight:500;">{{ $record->notes ?: '-' }}</span>
        </div>
    </div>

    <a href="{{ route('guru.tasmi-wali.index') }}" class="btn btn-outline text-theme-sm font-medium">
        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
        Kembali
    </a>
</x-layouts.portal>