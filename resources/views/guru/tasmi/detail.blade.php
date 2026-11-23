<x-layouts.portal title="Detail Hasil Tasmi'" :portal-label="$portalLabel" :breadcrumb="$breadcrumb">
    <section class="mx-auto max-w-4xl space-y-6">
        <header class="portal-page-header"><x-workspace-link :href="$backUrl" class="inline-flex items-center gap-2 text-success-ink hover:text-success-ink text-theme-sm font-medium"><span aria-hidden="true">←</span> {{ $backLabel }}</x-workspace-link><p class="mt-4 text-theme-xs font-normal uppercase text-success-ink">{{ $readOnly ? 'Data baca-saja' : 'Hasil Tasmi\'' }}</p><h1 class="mt-1 text-heading ui-page-title">Detail Hasil Tasmi'</h1><p class="mt-2 text-theme-sm font-normal text-muted">Informasi setoran, pemeriksa, dan riwayat pencatatan.</p></header>

        @if(session('status'))<p role="status" class="ui-card p-4 text-success-ink">{{ session('status') }}</p>@endif
        @if($errors->any())<div role="alert" class="ui-card p-4 text-danger-ink">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <x-tasmi-review-status :record="$record" />

        @if(($canReview ?? false) && in_array($record->review_status, [null, \App\Models\TasmiRecord::REVIEW_PENDING], true))
            <section class="ui-card p-5 sm:p-6">
                <h2 class="ui-card-title text-heading">Keputusan pemeriksaan</h2>
                <form method="POST" action="{{ route('guru.tasmi-wali.review', $record) }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $record->review_version }}">
                    <input type="hidden" name="decision" value="approved">
                    <label class="flex items-start gap-2 text-theme-sm text-body"><input type="checkbox" required class="mt-1"> Saya sudah memeriksa dan data Tasmi' ini benar.</label>
                    <div class="flex flex-wrap gap-3"><button type="submit" class="btn btn-primary">Setujui untuk piagam</button><button type="button" class="btn btn-outline" data-tasmi-correction-open>Minta perbaikan</button></div>
                </form>
                <dialog data-tasmi-correction-dialog class="m-auto w-full max-w-xl rounded-2xl border border-line bg-surface p-6 text-body shadow-2xl backdrop:bg-gray-950/55" aria-labelledby="tasmi-correction-title">
                    <h2 id="tasmi-correction-title" class="ui-card-title text-heading">Minta perbaikan data Tasmi'</h2>
                    <p class="mt-2 text-theme-sm text-muted">{{ $record->student?->name }} · {{ $record->juz_range_label }}. Alasan akan dikirim ke PJ Tasmi'.</p>
                    <form method="POST" action="{{ route('guru.tasmi-wali.review', $record) }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="review_version" value="{{ $record->review_version }}">
                        <input type="hidden" name="decision" value="correction_requested">
                        <label class="ui-field"><span>Alasan perbaikan *</span><textarea name="correction_reason" required maxlength="2000" rows="4" autofocus>{{ old('correction_reason') }}</textarea></label>
                        <div class="flex flex-wrap justify-end gap-3"><button type="button" class="btn btn-outline" data-tasmi-correction-close>Batal</button><button type="submit" class="btn btn-primary">Kirim ke PJ Tasmi'</button></div>
                    </form>
                </dialog>
            </section>
            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const dialog = document.querySelector('[data-tasmi-correction-dialog]');
                        document.querySelector('[data-tasmi-correction-open]')?.addEventListener('click', () => dialog.showModal());
                        document.querySelector('[data-tasmi-correction-close]')?.addEventListener('click', () => dialog.close());
                        @if($errors->any() && old('decision') === 'correction_requested') dialog.showModal(); @endif
                    });
                </script>
            @endpush
        @endif

        <section class="ui-card overflow-hidden">
            <div class="border-b border-success-line bg-success-soft px-5 py-5 sm:px-7"><p class="text-theme-xs font-normal uppercase text-success-ink">{{ $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? 'Kelas' }}</p><div class="mt-1 flex flex-wrap items-center justify-between gap-3"><h2 class="text-heading ui-card-title">{{ $record->student?->name ?? '-' }}</h2><span class="rounded-full bg-surface px-3 py-1 text-theme-sm font-medium text-success-ink shadow-sm">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span></div><p class="mt-1 text-theme-sm font-normal text-muted">{{ $record->student?->nis ? 'NIS '.$record->student->nis : 'NIS tidak tersedia' }}</p></div>
            <dl class="divide-y divide-line px-5 sm:px-7">
                <div class="detail-row"><dt>Semester</dt><dd>{{ $record->academicTerm?->name ?? '-' }}</dd></div><div class="detail-row"><dt>Penguji / PJ Tasmi'</dt><dd>{{ $record->examinerTeacher?->name ?? '-' }}</dd></div><div class="detail-row"><dt>Jenis dan juz</dt><dd>{{ \App\Models\TasmiRecord::examTypeOptions()[$record->exam_type] ?? $record->exam_type }} · {{ $record->juz_range_label }}</dd></div><div class="detail-row"><dt>Tanggal ujian</dt><dd>{{ $record->exam_date?->locale('id')->translatedFormat('l, d F Y') ?? '-' }}{{ $record->hijri_date ? ' · '.$record->hijri_date : '' }}</dd></div><div class="detail-row"><dt>Label hari</dt><dd>{{ $record->exam_day_label ?: '-' }}</dd></div><div class="detail-row"><dt>Catatan</dt><dd class="whitespace-pre-line font-medium">{{ $record->notes ?: '-' }}</dd></div><div class="detail-row"><dt>Diinput</dt><dd>{{ $record->inputBy?->name ?? '-' }} · {{ $record->input_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }} WIB</dd></div><div class="detail-row"><dt>Terakhir diperbarui</dt><dd>{{ $record->lastUpdatedBy?->name ?? $record->inputBy?->name ?? '-' }} · {{ $record->updated_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }} WIB</dd></div>
            </dl>
        </section>

        <section class="ui-card p-5 sm:p-7"><p class="text-theme-xs font-normal uppercase text-soft">Jejak pencatatan</p><h2 class="mt-1 text-heading ui-card-title">Riwayat perubahan</h2><div class="mt-5 space-y-3">@forelse($auditLogs as $log)<article class="rounded-2xl border border-line bg-surface-subtle px-4 py-3"><p class="text-theme-sm font-medium text-heading">{{ match($log->reason) { 'created_tasmi' => 'Hasil Tasmi dibuat', 'approved_tasmi' => 'Disetujui wali kelas', 'correction_requested_tasmi' => 'Wali kelas meminta perbaikan', default => 'Hasil Tasmi diperbarui' } }}</p><p class="mt-1 text-theme-xs font-normal text-muted">{{ $log->changer?->name ?? 'Sistem' }} · {{ $log->changed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }} WIB</p></article>@empty<p class="text-theme-sm font-normal text-muted">Belum ada riwayat perubahan tambahan.</p>@endforelse</div></section>
    </section>
</x-layouts.portal>
