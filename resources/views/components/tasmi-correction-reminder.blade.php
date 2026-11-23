@props(['reminder' => null])
@if(($reminder['count'] ?? 0) > 0)
    <aside class="mb-5 rounded-2xl border border-line bg-surface p-5" role="status">
        <p class="font-medium text-heading">{{ $reminder['count'] }} hasil Tasmi' perlu Anda perbaiki.</p>
        <button type="button" class="btn btn-outline mt-3" data-tasmi-pj-open>Lihat alasan wali kelas</button>
    </aside>
    <dialog data-tasmi-pj-dialog class="m-auto max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-line bg-surface p-6 text-body shadow-2xl backdrop:bg-gray-950/55" aria-labelledby="tasmi-pj-title">
        <h2 id="tasmi-pj-title" class="ui-card-title text-heading">Perbaikan data Tasmi'</h2>
        <p class="mt-2 text-theme-sm text-muted">Perbaiki data sesuai alasan wali kelas, lalu simpan untuk diperiksa kembali. Menampilkan maksimal 10 hasil.</p>
        <ul class="mt-4 space-y-4">
            @foreach($reminder['records'] as $record)
                <li class="rounded-xl border border-line p-4">
                    <p class="font-medium">{{ $record->student?->name }} · {{ $record->juz_range_label }}</p>
                    <p class="mt-2 whitespace-pre-line break-words text-theme-sm">{{ $record->correction_reason }}</p>
                    <a href="{{ route('guru.tasmi.edit', $record) }}" class="btn btn-primary mt-3">Perbaiki data</a>
                </li>
            @endforeach
        </ul>
        <div class="mt-5 flex flex-wrap justify-end gap-3"><a class="btn btn-outline" href="{{ route('guru.tasmi.records', ['review_status' => 'correction_requested']) }}">Lihat semua perbaikan</a><button type="button" class="btn btn-outline" data-tasmi-pj-close>Tutup sementara</button></div>
    </dialog>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const dialog = document.querySelector('[data-tasmi-pj-dialog]');
                document.querySelector('[data-tasmi-pj-open]')?.addEventListener('click', () => dialog.showModal());
                document.querySelector('[data-tasmi-pj-close]')?.addEventListener('click', () => dialog.close());
                // Avoid stacking dialogs for a teacher who is also a wali kelas.
                if (!document.querySelector('[data-tasmi-wali-modal]')) dialog.showModal();
            });
        </script>
    @endpush
@endif
