<div class="space-y-4">
    @forelse ($logs as $log)
        @php
            $beforeTotal = data_get($log->before_data, 'total_score');
            $afterTotal = data_get($log->after_data, 'total_score');
        @endphp
        <section class="rounded-xl border border-line p-4 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <strong>Revisi {{ $log->revision_number }} · {{ $log->action === 'opened' ? 'Dibuka' : 'Diterbitkan kembali' }}</strong>
                <span class="text-theme-sm text-muted">{{ $log->performed_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i') }} WIB</span>
            </div>
            <p class="mt-2 text-theme-sm">Alasan: {{ $log->reason }}</p>
            <p class="mt-1 text-theme-sm text-muted">Oleh: {{ $log->performer?->name ?? 'Akun yang dihapus' }}</p>
            @if ($beforeTotal !== null || $afterTotal !== null)
                <p class="mt-2 text-theme-sm">Nilai total: {{ $beforeTotal ?? '—' }} → {{ $afterTotal ?? '—' }}</p>
            @endif
        </section>
    @empty
        <p class="text-theme-sm text-muted">Belum ada riwayat revisi.</p>
    @endforelse
</div>
