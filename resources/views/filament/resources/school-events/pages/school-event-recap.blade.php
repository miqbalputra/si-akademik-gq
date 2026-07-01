<x-filament-panels::page>
    @php
        $event = $recap['event'];
        $stats = $recap['stats'];
        $targetClassroomTerms = $recap['target_classroom_terms'];
        $filteredGuardianRows = $this->filteredGuardianRows();
        $filteredStats = $this->filteredStats();
        $followUpRows = $this->followUpRows();
    @endphp

    <div class="space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-amber-700">Rekap Event Sekolah</p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-900">{{ $event->title }}</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $event->typeLabel() }} &middot; {{ $event->starts_on->locale('id')->translatedFormat('l, d F Y') }}
                        @if (! $event->starts_on->equalTo($event->ends_on))
                            s.d. {{ $event->ends_on->locale('id')->translatedFormat('l, d F Y') }}
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-slate-600">Target: {{ $event->targetSummary(4) }}</p>
                    <p class="mt-1 text-sm text-slate-600">Periode: {{ $event->academicTerm?->academicYear?->name }} - {{ $event->academicTerm?->name }}</p>
                    @if ($event->location)
                        <p class="mt-1 text-sm text-slate-600">Lokasi: {{ $event->location }}</p>
                    @endif
                    @if ($event->description)
                        <p class="mt-3 text-sm text-slate-700">{{ $event->description }}</p>
                    @endif
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-semibold text-slate-900">Cakupan Target</p>
                    <p class="mt-2">Mode: {{ $event->targetScopeLabel() }}</p>
                    <p class="mt-1">Kelas aktif: {{ $targetClassroomTerms->count() }}</p>
                    <p class="mt-1">Santri target: {{ $stats['target_students'] }}</p>
                    <p class="mt-1">Wali target: {{ $stats['target_guardians'] }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Total Wali Target</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['target_guardians'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-emerald-700">Sudah Respon</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $stats['responded'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-amber-700">Belum Respon</p>
                <p class="mt-2 text-3xl font-bold text-amber-900">{{ $stats['pending'] }}</p>
            </div>
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-indigo-700">Tingkat Respon</p>
                <p class="mt-2 text-3xl font-bold text-indigo-900">{{ number_format($stats['response_rate'], 2) }}%</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-emerald-700">Hadir</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $stats['attending'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-amber-700">Izin</p>
                <p class="mt-2 text-3xl font-bold text-amber-900">{{ $stats['permission'] }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-rose-700">Tidak Hadir</p>
                <p class="mt-2 text-3xl font-bold text-rose-900">{{ $stats['not_attending'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">Total Santri Target</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['target_students'] }}</p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Rekap Bapak</h3>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Target</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['father_target'] }}</p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-3">
                        <p class="text-xs text-emerald-700">Sudah Respon</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $stats['father_responded'] }}</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-3">
                        <p class="text-xs text-amber-700">Belum Respon</p>
                        <p class="mt-1 text-2xl font-bold text-amber-900">{{ max($stats['father_target'] - $stats['father_responded'], 0) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Rekap Ibu</h3>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Target</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['mother_target'] }}</p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-3">
                        <p class="text-xs text-emerald-700">Sudah Respon</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $stats['mother_responded'] }}</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-3">
                        <p class="text-xs text-amber-700">Belum Respon</p>
                        <p class="mt-1 text-2xl font-bold text-amber-900">{{ max($stats['mother_target'] - $stats['mother_responded'], 0) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Rekap Detail per Wali Santri</h3>
                    <p class="mt-1 text-sm text-slate-600">Menampilkan bapak, ibu, atau wali yang terhubung ke santri target event ini.</p>
                </div>

                <div class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-end">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Cari Wali / Anak / Kontak</span>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="guardianSearch"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            placeholder="Misalnya nama wali, nama anak, nomor WhatsApp, atau email"
                        >
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([
                            'all' => 'Semua',
                            'pending' => 'Belum Respon',
                            'attending' => 'Hadir',
                            'permission' => 'Izin',
                            'not_attending' => 'Tidak Hadir',
                        ] as $statusKey => $statusLabel)
                            <button
                                type="button"
                                wire:click="$set('filterStatus', '{{ $statusKey }}')"
                                class="rounded-lg px-3 py-2 text-sm font-semibold {{ $this->filterStatus === $statusKey ? 'bg-amber-600 text-white' : 'border border-slate-300 bg-white text-slate-700' }}"
                            >
                                {{ $statusLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-5">
                <div class="rounded-lg bg-slate-50 p-3 text-center">
                    <p class="text-xs text-slate-500">Baris Tampil</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $filteredStats['total'] }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 p-3 text-center">
                    <p class="text-xs text-emerald-700">Sudah Respon</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $filteredStats['responded'] }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 p-3 text-center">
                    <p class="text-xs text-amber-700">Belum Respon</p>
                    <p class="mt-1 text-2xl font-bold text-amber-900">{{ $filteredStats['pending'] }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 p-3 text-center">
                    <p class="text-xs text-amber-700">Izin</p>
                    <p class="mt-1 text-2xl font-bold text-amber-900">{{ $filteredStats['permission'] }}</p>
                </div>
                <div class="rounded-lg bg-rose-50 p-3 text-center">
                    <p class="text-xs text-rose-700">Tidak Hadir</p>
                    <p class="mt-1 text-2xl font-bold text-rose-900">{{ $filteredStats['not_attending'] }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Nama Wali</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Peran</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Anak Terhubung</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Kontak</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Status</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Waktu Respon</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($filteredGuardianRows as $row)
                            <tr>
                                <td class="px-3 py-3 align-top">
                                    <p class="font-semibold text-slate-900">{{ $row['guardian_name'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['guardian_gender'] }}</p>
                                </td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ implode(', ', $row['relationship_labels']) ?: '-' }}</td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ implode(', ', $row['student_names']) ?: '-' }}</td>
                                <td class="px-3 py-3 align-top text-slate-700">
                                    <p>{{ $row['phone'] ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['email'] ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ match ($row['attendance_status']) {
                                        'attending' => 'bg-emerald-100 text-emerald-800',
                                        'permission' => 'bg-amber-100 text-amber-800',
                                        'not_attending' => 'bg-rose-100 text-rose-800',
                                        default => 'bg-slate-100 text-slate-700',
                                    } }}">
                                        {{ $row['attendance_label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top text-slate-700">
                                    {{ $row['responded_at']?->locale('id')->translatedFormat('d F Y H:i') ?? '-' }}
                                </td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ $row['notes'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-slate-500">Tidak ada data wali yang cocok dengan filter saat ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Daftar Follow-up</h3>
                    <p class="mt-1 text-sm text-slate-600">Fokus pada wali yang belum respon, izin, atau menyatakan tidak hadir.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
                    {{ $followUpRows->count() }} wali perlu tindak lanjut
                </span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Nama Wali</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Peran</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Anak Terhubung</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Kontak</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Status</th>
                            <th class="px-3 py-3 text-left font-semibold text-slate-600">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($followUpRows as $row)
                            <tr>
                                <td class="px-3 py-3 align-top font-semibold text-slate-900">{{ $row['guardian_name'] }}</td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ implode(', ', $row['relationship_labels']) ?: '-' }}</td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ implode(', ', $row['student_names']) ?: '-' }}</td>
                                <td class="px-3 py-3 align-top text-slate-700">
                                    <p>{{ $row['phone'] ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['email'] ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ match ($row['attendance_status']) {
                                        'permission' => 'bg-amber-100 text-amber-800',
                                        'not_attending' => 'bg-rose-100 text-rose-800',
                                        default => 'bg-slate-100 text-slate-700',
                                    } }}">
                                        {{ $row['attendance_label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ $row['notes'] ?: 'Perlu dihubungi untuk konfirmasi.' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-slate-500">Semua wali sudah merespon dan tidak ada follow-up tertunda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
