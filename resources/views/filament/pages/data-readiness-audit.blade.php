<x-filament-panels::page>
    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Kesiapan Setup</p>
                <p class="mt-2 text-3xl font-bold text-gray-950 dark:text-white">{{ $audit['readiness_percentage'] ?? 0 }}%</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ $audit['ready_sections'] ?? 0 }} dari {{ $audit['total_sections'] ?? 0 }} checklist sudah aman.
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Masalah</p>
                <p class="mt-2 text-3xl font-bold {{ ($audit['total_issues'] ?? 0) > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                    {{ number_format($audit['total_issues'] ?? 0) }}
                </p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Data yang perlu dilengkapi sebelum operasional.
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</p>
                <p class="mt-2 text-2xl font-bold {{ ($audit['status'] ?? '') === 'ready' ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">
                    {{ ($audit['status'] ?? '') === 'ready' ? 'Siap dipakai' : 'Perlu dicek' }}
                </p>
                <button
                    type="button"
                    wire:click="refreshAudit"
                    class="mt-3 inline-flex rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700"
                >
                    Refresh Audit
                </button>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            @foreach (($audit['sections'] ?? []) as $section)
                <article class="rounded-lg border {{ $section['count'] > 0 ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30' : 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30' }} p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $section['description'] }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $section['count'] > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-100' }}">
                            {{ number_format($section['count']) }}
                        </span>
                    </div>

                    @if ($section['count'] > 0)
                        <div class="mt-4 rounded-lg bg-white/70 p-3 dark:bg-gray-900/60">
                            <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Contoh data</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($section['samples'] as $sample)
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $sample }}</span>
                                @empty
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Tidak ada contoh yang bisa ditampilkan.</span>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-sm font-medium text-emerald-700 dark:text-emerald-300">Aman.</p>
                    @endif
                </article>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
