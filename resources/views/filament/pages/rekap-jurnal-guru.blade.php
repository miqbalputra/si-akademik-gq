<x-filament-panels::page>
    @php
        $stats = $this->recap['stats'] ?? [];
        $term = $this->recap['term'] ?? null;
        $rows = $this->teacherRows();
    @endphp

    <div class="space-y-6">
        {{-- ===== FILTER FORM ===== --}}
        <section class="rounded-xl border border-line bg-surface p-5 shadow-sm">
            <div class="grid gap-3 md:grid-cols-3">
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal uppercase text-muted">Periode Ajaran</span>
                    <select
                        name="academicTermId"
                        wire:model.live="academicTermId"
                        class="mt-1 w-full rounded-lg border border-line-strong px-3 py-2 focus:border-warning-500 focus:ring-warning-line text-theme-sm font-normal"
                    >
                        @foreach ($termOptions as $termOpt)
                            <option value="{{ $termOpt['id'] }}">{{ $termOpt['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal uppercase text-muted">Dari Tanggal</span>
                    <input
                        type="date"
                        wire:model.live.debounce.300ms="dateFrom"
                        class="mt-1 w-full rounded-lg border border-line-strong px-3 py-2 focus:border-warning-500 focus:ring-warning-line text-theme-sm font-normal"
                    >
                </label>
                <label class="block ui-form-label">
                    <span class="text-theme-xs font-normal uppercase text-muted">Sampai Tanggal</span>
                    <input
                        type="date"
                        wire:model.live.debounce.300ms="dateUntil"
                        class="mt-1 w-full rounded-lg border border-line-strong px-3 py-2 focus:border-warning-500 focus:ring-warning-line text-theme-sm font-normal"
                    >
                </label>
            </div>
            @if ($term)
                <p class="mt-3 text-theme-sm text-body">
                    Periode: <span class="font-semibold">{{ $term->academicYear?->name ?? '-' }} - {{ $term->name }}</span>
                    @if ($this->recap['date_from'] && $this->recap['date_until'])
                        &middot; {{ $this->recap['date_from'] }} s.d. {{ $this->recap['date_until'] }}
                    @endif
                </p>
            @endif
        </section>

        {{-- ===== STAT CARDS ===== --}}
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm">
                <p class="text-theme-xs font-normal uppercase text-muted">Total Guru</p>
                <p class="mt-2 text-heading ui-metric-value">{{ $stats['total_teachers'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-success-line bg-success-soft p-4 shadow-sm">
                <p class="text-theme-xs font-normal uppercase text-success-ink">Sesi Asli</p>
                <p class="mt-2 text-success-ink ui-metric-value">{{ $stats['total_sesi_asli'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-brand-line bg-brand-soft p-4 shadow-sm">
                <p class="text-theme-xs font-normal uppercase text-brand-ink">Sesi Pengganti</p>
                <p class="mt-2 text-brand-ink ui-metric-value">{{ $stats['total_sesi_pengganti'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-warning-line bg-warning-soft p-4 shadow-sm">
                <p class="text-theme-xs font-normal uppercase text-warning-ink">Sesi Tafsir</p>
                <p class="mt-2 text-warning-ink ui-metric-value">{{ $stats['total_sesi_tafsir'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-4 shadow-sm">
                <p class="text-theme-xs font-normal uppercase text-soft">Total JP</p>
                <p class="mt-2 text-white ui-metric-value">{{ $stats['total_jp'] ?? 0 }}</p>
            </div>
        </section>

        {{-- ===== PER-TEACHER TABLE ===== --}}
        <section class="rounded-xl border border-line bg-surface p-5 shadow-sm">
            <h3 class="text-heading ui-card-title">Rekap JP per Guru</h3>
            <p class="mt-1 text-theme-sm text-body">
                Setiap guru yang mengisi jurnal (asli atau pengganti) mendapat 1 JP per sesi.
                Tafsir serentak dihitung 1 JP per (guru, tanggal).
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-theme-sm">
                    <thead class="bg-surface-subtle text-theme-xs font-medium">
                        <tr>
                            <th class="px-3 py-3 text-left text-body text-theme-xs font-medium">No</th>
                            <th class="px-3 py-3 text-left text-body text-theme-xs font-medium">Nama Guru</th>
                            <th class="px-3 py-3 text-left text-body text-theme-xs font-medium">NIY</th>
                            <th class="px-3 py-3 text-right text-body text-theme-xs font-medium">Sesi Asli</th>
                            <th class="px-3 py-3 text-right text-body text-theme-xs font-medium">Sesi Pengganti</th>
                            <th class="px-3 py-3 text-right text-body text-theme-xs font-medium">Sesi Tafsir</th>
                            <th class="px-3 py-3 text-right text-body text-theme-xs font-medium">Total JP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse ($rows as $i => $row)
                            <tr>
                                <td class="px-3 py-3 text-muted">{{ $i + 1 }}</td>
                                <td class="px-3 py-3 font-medium text-heading">
                                    {{ $row['name'] }}
                                    @if (($row['status'] ?? null) !== 'active')
                                        <span class="ml-2 rounded-full bg-surface-muted px-2 py-0.5 text-body text-theme-xs font-medium">nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-body">{{ $row['niy'] ?: '-' }}</td>
                                <td class="px-3 py-3 text-right text-body">{{ $row['sesi_asli'] }}</td>
                                <td class="px-3 py-3 text-right text-body">{{ $row['sesi_pengganti'] }}</td>
                                <td class="px-3 py-3 text-right text-body">{{ $row['sesi_tafsir'] }}</td>
                                <td class="px-3 py-3 text-right font-medium text-heading">{{ $row['total_jp'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-muted">Tidak ada jurnal pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-surface-subtle">
                            <tr>
                                <td colspan="3" class="px-3 py-3 font-medium text-body">Total</td>
                                <td class="px-3 py-3 text-right font-medium text-body">{{ $stats['total_sesi_asli'] ?? 0 }}</td>
                                <td class="px-3 py-3 text-right font-medium text-body">{{ $stats['total_sesi_pengganti'] ?? 0 }}</td>
                                <td class="px-3 py-3 text-right font-medium text-body">{{ $stats['total_sesi_tafsir'] ?? 0 }}</td>
                                <td class="px-3 py-3 text-right font-medium text-heading">{{ $stats['total_jp'] ?? 0 }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>