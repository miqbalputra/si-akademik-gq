<x-filament-panels::page>
    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm dark:border-gray-800 dark:bg-surface">
                <p class="text-sm font-medium text-muted dark:text-muted">Kesiapan Setup</p>
                <p class="mt-2 text-3xl font-bold text-heading dark:text-white">{{ $audit['readiness_percentage'] ?? 0 }}%</p>
                <p class="mt-1 text-sm text-body dark:text-body">
                    {{ $audit['ready_sections'] ?? 0 }} dari {{ $audit['total_sections'] ?? 0 }} checklist sudah aman.
                </p>
            </div>

            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm dark:border-gray-800 dark:bg-surface">
                <p class="text-sm font-medium text-muted dark:text-muted">Total Masalah</p>
                <p class="mt-2 text-3xl font-bold {{ ($audit['total_issues'] ?? 0) > 0 ? 'text-warning-ink dark:text-warning-300' : 'text-success-ink dark:text-success-300' }}">
                    {{ number_format($audit['total_issues'] ?? 0) }}
                </p>
                <p class="mt-1 text-sm text-body dark:text-body">
                    Data yang perlu dilengkapi sebelum operasional.
                </p>
            </div>

            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm dark:border-gray-800 dark:bg-surface">
                <p class="text-sm font-medium text-muted dark:text-muted">Status</p>
                <p class="mt-2 text-2xl font-bold {{ ($audit['status'] ?? '') === 'ready' ? 'text-success-ink dark:text-success-300' : 'text-warning-ink dark:text-warning-300' }}">
                    {{ ($audit['status'] ?? '') === 'ready' ? 'Siap dipakai' : 'Perlu dicek' }}
                </p>
                <button
                    type="button"
                    wire:click="refreshAudit"
                    class="mt-3 inline-flex rounded-lg bg-warning-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-warning-700"
                >
                    Refresh Audit
                </button>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            @foreach (($audit['sections'] ?? []) as $section)
                <article class="rounded-lg border {{ $section['count'] > 0 ? 'border-warning-line bg-warning-soft dark:border-warning-900 dark:bg-warning-950/30' : 'border-success-line bg-success-soft dark:border-success-900 dark:bg-success-950/30' }} p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-heading dark:text-white">{{ $section['title'] }}</h2>
                            <p class="mt-1 text-sm text-body dark:text-body">{{ $section['description'] }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $section['count'] > 0 ? 'bg-warning-soft-strong text-warning-ink dark:bg-warning-900 dark:text-warning-100' : 'bg-success-soft-strong text-success-ink dark:bg-success-900 dark:text-success-100' }}">
                            {{ number_format($section['count']) }}
                        </span>
                    </div>

                    @if ($section['count'] > 0)
                        <div class="mt-4 rounded-lg bg-surface/70 p-3 dark:bg-surface/60">
                            <p class="text-xs font-semibold uppercase text-muted dark:text-muted">Contoh data</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($section['samples'] as $sample)
                                    <span class="rounded-full bg-surface-muted px-3 py-1 text-xs font-medium text-body dark:bg-surface-muted dark:text-heading">{{ $sample }}</span>
                                @empty
                                    <span class="text-sm text-muted dark:text-muted">Tidak ada contoh yang bisa ditampilkan.</span>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-sm font-medium text-success-ink dark:text-success-300">Aman.</p>
                    @endif
                </article>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
