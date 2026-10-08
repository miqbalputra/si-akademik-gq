<x-layouts.portal title="Riwayat Perubahan Jadwal" portalLabel="Portal Guru" breadcrumb="Riwayat Perubahan Jadwal">
    <x-slot name="navLinks">
        <a href="{{ route('guru.dashboard') }}" class="btn btn-outline btn-sm">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Dashboard</span>
        </a>
    </x-slot>

    <div class="mb-6 flex justify-between items-center glass-card p-4 rounded-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-heading">Riwayat Perubahan Jadwal</h1>
            <p class="text-sm text-muted">Catatan setiap perubahan jadwal mengajar &amp; penugasan yang menyangkut Anda.</p>
        </div>
        <span class="text-sm font-bold text-info-ink bg-info-soft-strong px-3 py-1 rounded-full whitespace-nowrap">
            {{ $changes->count() }} perubahan
        </span>
    </div>

    <div class="glass-card rounded-2xl p-6 border border-line">
        @if($changes->isEmpty())
            <p class="text-sm text-muted italic text-center py-8">Belum ada perubahan jadwal yang tercatat untuk Anda.</p>
        @else
            @php
                $grouped = $changes->groupBy(fn ($c) => $c->created_at->format('Y-m-d'));
            @endphp
            <div class="space-y-5">
                @foreach($grouped as $date => $items)
                    <div>
                        <div class="flex items-baseline gap-2 mb-2 pb-1 border-b border-line">
                            <span class="text-sm font-semibold text-heading">
                                {{ \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y') }}
                            </span>
                            <span class="text-xs font-medium text-soft">· {{ $items->count() }} perubahan</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($items as $change)
                                @php
                                    $eventBadge = match($change->event) {
                                        'created' => ['label' => 'Dibuat', 'class' => 'bg-success-soft-strong text-success-ink'],
                                        'updated' => ['label' => 'Diubah', 'class' => 'bg-warning-soft-strong text-warning-ink'],
                                        'deleted' => ['label' => 'Dihapus', 'class' => 'bg-danger-soft-strong text-danger-ink'],
                                        default => ['label' => $change->event, 'class' => 'bg-surface-muted text-body'],
                                    };
                                    $typeBadge = match($change->entity_type) {
                                        'schedule' => 'Jadwal',
                                        'assignment' => 'Penugasan',
                                        default => $change->entity_type,
                                    };
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-start gap-3 p-3 rounded-xl border border-line bg-surface">
                                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $eventBadge['class'] }}">{{ $eventBadge['label'] }}</span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-surface-muted text-body">{{ $typeBadge }}</span>
                                        <span class="text-[10px] text-soft whitespace-nowrap">{{ $change->created_at->format('H:i') }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-body">{{ $change->change_summary }}</p>
                                        @if($change->old_teacher_id && $change->old_teacher_id !== $change->teacher_id)
                                            <p class="text-[11px] text-soft mt-0.5">Menyangkut: {{ $change->oldTeacher?->name ?? '-' }} → {{ $change->teacher?->name ?? '-' }}</p>
                                        @endif
                                        <p class="text-[11px] text-soft mt-0.5">Diubah oleh: {{ $change->changer?->name ?? 'sistem' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.portal>