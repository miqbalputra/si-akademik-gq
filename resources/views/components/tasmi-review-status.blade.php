@props(['record'])
<section class="ui-card p-5 sm:p-6" aria-label="Status persetujuan Tasmi'">
    <p class="text-theme-xs uppercase text-muted">Persetujuan wali kelas</p>
    <h2 class="mt-1 text-heading ui-card-title">{{ $record->review_status_label }}</h2>
    @if($record->reviewed_at)
        <p class="mt-2 text-theme-sm text-muted">{{ $record->reviewer?->name ?? 'Wali kelas' }} · {{ $record->reviewed_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
    @endif
    @if($record->review_status === \App\Models\TasmiRecord::REVIEW_CORRECTION)
        <p class="mt-3 text-theme-sm font-medium text-heading">Alasan perbaikan</p>
        <p class="mt-1 whitespace-pre-line break-words text-theme-sm text-body">{{ $record->correction_reason }}</p>
        <p class="mt-3 text-theme-sm text-muted">PJ Tasmi' perlu mengedit data dan menyimpan perbaikan untuk diperiksa kembali.</p>
    @elseif($record->review_status === \App\Models\TasmiRecord::REVIEW_APPROVED)
        <p class="mt-2 text-theme-sm text-muted">Siap diunduh admin untuk proses piagam. Perubahan hasil akan membutuhkan persetujuan ulang.</p>
    @else
        <p class="mt-2 text-theme-sm text-muted">Periksa identitas santri, kelas, jenis ujian, juz, tanggal, dan predikat sebelum menyetujui.</p>
    @endif
</section>
