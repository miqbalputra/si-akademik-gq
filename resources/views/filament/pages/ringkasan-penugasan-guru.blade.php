<x-filament-panels::page>
    @php
        $stats = $this->stats ?? [];
        $classesWithout = $stats['classes_without_assignment'] ?? 0;
    @endphp

    <div class="space-y-6">
        {{-- ===== FILTER PERIODE ===== --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-3 md:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Periode Ajaran</span>
                    <select
                        name="academicTermId"
                        wire:model.live="academicTermId"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-amber-200"
                    >
                        @foreach ($termOptions as $termOpt)
                            <option value="{{ $termOpt['id'] }}" @selected((string) $termOpt['id'] === (string) $this->academicTermId)>{{ $termOpt['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end">
                    <p class="text-sm text-slate-600">
                        Periode: <span class="font-semibold">{{ $this->termLabel }}</span>
                    </p>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                Tabel memuat semua penugasan di periode terpilih. Status Aktif = belum selesai (tanggal selesai kosong atau &ge; hari ini WIB). Gunakan kolom search, filter, dan sort untuk mengaudit.
            </p>
        </section>

        {{-- ===== STAT CARDS ===== --}}
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Total Kelas</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['total_classrooms'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-indigo-700">Total Penugasan</p>
                <p class="mt-2 text-3xl font-bold text-indigo-900">{{ $stats['total_assignments'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-emerald-700">Aktif</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $stats['total_active'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-300 bg-slate-100 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-600">Berakhir</p>
                <p class="mt-2 text-3xl font-bold text-slate-800">{{ $stats['total_inactive'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-300">Guru Unik</p>
                <p class="mt-2 text-3xl font-bold text-white">{{ $stats['total_teachers_unique'] ?? 0 }}</p>
            </div>
        </section>

        @if ($classesWithout > 0)
            <section class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm">
                <p class="text-sm font-semibold text-amber-800">
                    <span class="mr-1">!</span> {{ $classesWithout }} kelas di periode ini belum punya penugasan guru aktif.
                </p>
                <p class="mt-1 text-xs text-amber-700">Cek apakah perlu dibuatkan penugasan baru di menu Penugasan Guru.</p>
            </section>
        @endif

        {{-- ===== INTERACTIVE FILAMENT TABLE ===== --}}
        <div class="mt-2">
            {{ $this->getTable() }}
        </div>
    </div>
</x-filament-panels::page>