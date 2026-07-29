<x-filament-panels::page>
    @php
        $stats = $this->recap['stats'] ?? [];
        $termLabel = $this->recap['term_label'] ?? '-';
        $blocks = $this->classroomBlocks();
        $classesWithout = $stats['classes_without_assignment'] ?? 0;
    @endphp

    <div class="space-y-6">
        {{-- ===== FILTER FORM ===== --}}
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
                        Periode: <span class="font-semibold">{{ $termLabel }}</span>
                    </p>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                Menampilkan semua penugasan (aktif &amp; berakhir) di periode terpilih. Status Aktif = belum selesai (tanggal selesai kosong atau &ge; hari ini WIB).
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
                    <span class="mr-1">⚠</span> {{ $classesWithout }} kelas di periode ini belum punya penugasan guru aktif.
                </p>
                <p class="mt-1 text-xs text-amber-700">Cek apakah perlu dibuatkan penugasan baru di menu Penugasan Guru.</p>
            </section>
        @endif

        {{-- ===== PER-CLASSROOM BLOCKS ===== --}}
        <section class="space-y-4">
            @forelse ($blocks as $block)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-lg font-semibold text-slate-900">Kelas {{ $block['label'] }}</h3>
                        @if ($block['classroom_name'] !== '-' && $block['classroom_name'] !== $block['label'])
                            <span class="text-xs text-slate-500">{{ $block['classroom_name'] }}</span>
                        @endif
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $block['rows']->count() }} penugasan</span>
                    </div>

                    <div class="mt-3 overflow-x-auto">
                        @if ($block['rows']->isNotEmpty())
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Mapel</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Guru</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Peran</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Jadwal</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Mulai</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Selesai</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($block['rows'] as $row)
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-900">{{ $row['subject_name'] }}</td>
                                            <td class="px-3 py-2 text-slate-700">{{ $row['teacher_name'] }}</td>
                                            <td class="px-3 py-2">
                                                @if ($row['assignment_role'] === 'primary')
                                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Utama</span>
                                                @elseif ($row['assignment_role'] === 'assistant')
                                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">Pendamping</span>
                                                @else
                                                    <span class="text-slate-500">{{ $row['assignment_role'] ?? '-' }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-slate-600">
                                                @if (! empty($row['schedules']))
                                                    <ul class="list-disc pl-4 text-xs leading-5">
                                                        @foreach ($row['schedules'] as $sched)
                                                            <li>{{ $sched }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="text-xs text-slate-400">belum dijadwalkan</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-slate-600">{{ $row['starts_at'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-slate-600">{{ $row['ends_at'] ?? '-' }}</td>
                                            <td class="px-3 py-2">
                                                @if ($row['is_active'])
                                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Aktif</span>
                                                @else
                                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">Berakhir</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="py-4 text-sm text-slate-500">Tidak ada penugasan untuk kelas ini di periode terpilih.</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-sm text-slate-500">Tidak ada data penugasan di periode ini.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>