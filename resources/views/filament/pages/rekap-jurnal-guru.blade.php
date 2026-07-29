<x-filament-panels::page>
    @php
        $stats = $this->recap['stats'] ?? [];
        $term = $this->recap['term'] ?? null;
        $rows = $this->teacherRows();
    @endphp

    <div class="space-y-6">
        {{-- ===== FILTER FORM ===== --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-3 md:grid-cols-3">
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Periode Ajaran</span>
                    <select
                        name="academicTermId"
                        wire:model.live="academicTermId"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-amber-200"
                    >
                        @foreach ($termOptions as $termOpt)
                            <option value="{{ $termOpt['id'] }}">{{ $termOpt['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dari Tanggal</span>
                    <input
                        type="date"
                        wire:model.live.debounce.300ms="dateFrom"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-amber-200"
                    >
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sampai Tanggal</span>
                    <input
                        type="date"
                        wire:model.live.debounce.300ms="dateUntil"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-amber-200"
                    >
                </label>
            </div>
            @if ($term)
                <p class="mt-3 text-sm text-slate-600">
                    Periode: <span class="font-semibold">{{ $term->academicYear?->name ?? '-' }} - {{ $term->name }}</span>
                    @if ($this->recap['date_from'] && $this->recap['date_until'])
                        &middot; {{ $this->recap['date_from'] }} s.d. {{ $this->recap['date_until'] }}
                    @endif
                </p>
            @endif
        </section>

        {{-- ===== STAT CARDS ===== --}}
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Total Guru</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['total_teachers'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-emerald-700">Sesi Asli</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $stats['total_sesi_asli'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-indigo-700">Sesi Pengganti</p>
                <p class="mt-2 text-3xl font-bold text-indigo-900">{{ $stats['total_sesi_pengganti'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-amber-700">Sesi Tafsir</p>
                <p class="mt-2 text-3xl font-bold text-amber-900">{{ $stats['total_sesi_tafsir'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-300">Total JP</p>
                <p class="mt-2 text-3xl font-bold text-white">{{ $stats['total_jp'] ?? 0 }}</p>
            </div>
        </section>

        {{-- ===== PER-TEACHER TABLE ===== --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Rekap JP per Guru</h3>
            <p class="mt-1 text-sm text-slate-600">
                Setiap guru yang mengisi jurnal (asli atau pengganti) mendapat 1 JP per sesi.
                Tafsir serentak dihitung 1 JP per (guru, tanggal).
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">No</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Nama Guru</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">NIY</th>
                            <th class="px-3 py-3 text-right font-semibold text-slate-600">Sesi Asli</th>
                            <th class="px-3 py-3 text-right font-semibold text-slate-600">Sesi Pengganti</th>
                            <th class="px-3 py-3 text-right font-semibold text-slate-600">Sesi Tafsir</th>
                            <th class="px-3 py-3 text-right font-semibold text-slate-600">Total JP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($rows as $i => $row)
                            <tr>
                                <td class="px-3 py-3 text-slate-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-900">
                                    {{ $row['name'] }}
                                    @if (($row['status'] ?? null) !== 'active')
                                        <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $row['niy'] ?: '-' }}</td>
                                <td class="px-3 py-3 text-right text-slate-700">{{ $row['sesi_asli'] }}</td>
                                <td class="px-3 py-3 text-right text-slate-700">{{ $row['sesi_pengganti'] }}</td>
                                <td class="px-3 py-3 text-right text-slate-700">{{ $row['sesi_tafsir'] }}</td>
                                <td class="px-3 py-3 text-right font-bold text-slate-900">{{ $row['total_jp'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-500">Tidak ada jurnal pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-slate-50">
                            <tr>
                                <td colspan="3" class="px-3 py-3 font-semibold text-slate-700">Total</td>
                                <td class="px-3 py-3 text-right font-bold text-slate-700">{{ $stats['total_sesi_asli'] ?? 0 }}</td>
                                <td class="px-3 py-3 text-right font-bold text-slate-700">{{ $stats['total_sesi_pengganti'] ?? 0 }}</td>
                                <td class="px-3 py-3 text-right font-bold text-slate-700">{{ $stats['total_sesi_tafsir'] ?? 0 }}</td>
                                <td class="px-3 py-3 text-right font-bold text-slate-900">{{ $stats['total_jp'] ?? 0 }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>