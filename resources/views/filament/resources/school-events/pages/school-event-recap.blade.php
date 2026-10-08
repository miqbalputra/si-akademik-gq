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
        <section class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-warning-ink">Rekap Event Sekolah</p>
                    <h2 class="mt-1 text-2xl font-bold text-heading">{{ $event->title }}</h2>
                    <p class="mt-2 text-sm text-body">
                        {{ $event->is_no_kbm ? 'Agenda Tanpa KBM' : $event->typeLabel() }} &middot; {{ $event->starts_on->locale('id')->translatedFormat('l, d F Y') }}
                        @if (! $event->starts_on->equalTo($event->ends_on))
                            s.d. {{ $event->ends_on->locale('id')->translatedFormat('l, d F Y') }}
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-body">Target: {{ $event->targetSummary(4) }}</p>
                    <p class="mt-1 text-sm text-body">Periode: {{ $event->academicTerm?->academicYear?->name }} - {{ $event->academicTerm?->name }}</p>
                    @if ($event->location)
                        <p class="mt-1 text-sm text-body">Lokasi: {{ $event->location }}</p>
                    @endif
                    @if ($event->description)
                        <p class="mt-3 text-sm text-body">{{ $event->description }}</p>
                    @endif
                </div>
                <div class="rounded-lg border border-line bg-surface-subtle p-4 text-sm text-body">
                    <p class="font-semibold text-heading">Cakupan Target</p>
                    <p class="mt-2">Mode: {{ $event->targetScopeLabel() }}</p>
                    <p class="mt-1">Kelas aktif: {{ $targetClassroomTerms->count() }}</p>
                    <p class="mt-1">Santri target: {{ $stats['target_students'] }}</p>
                    <p class="mt-1">Wali target: {{ $stats['target_guardians'] }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-muted">Total Wali Target</p>
                <p class="mt-2 text-3xl font-bold text-heading">{{ $stats['target_guardians'] }}</p>
            </div>
            <div class="rounded-lg border border-success-line bg-success-soft p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-success-ink">Sudah Respon</p>
                <p class="mt-2 text-3xl font-bold text-success-ink">{{ $stats['responded'] }}</p>
            </div>
            <div class="rounded-lg border border-warning-line bg-warning-soft p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-warning-ink">Belum Respon</p>
                <p class="mt-2 text-3xl font-bold text-warning-ink">{{ $stats['pending'] }}</p>
            </div>
            <div class="rounded-lg border border-brand-line bg-brand-soft p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-brand-ink">Tingkat Respon</p>
                <p class="mt-2 text-3xl font-bold text-brand-ink">{{ number_format($stats['response_rate'], 2) }}%</p>
            </div>
            <div class="rounded-lg border border-success-line bg-surface p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-success-ink">Hadir</p>
                <p class="mt-2 text-3xl font-bold text-success-ink">{{ $stats['attending'] }}</p>
            </div>
            <div class="rounded-lg border border-warning-line bg-surface p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-warning-ink">Izin</p>
                <p class="mt-2 text-3xl font-bold text-warning-ink">{{ $stats['permission'] }}</p>
            </div>
            <div class="rounded-lg border border-danger-line bg-surface p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-danger-ink">Tidak Hadir</p>
                <p class="mt-2 text-3xl font-bold text-danger-ink">{{ $stats['not_attending'] }}</p>
            </div>
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase text-muted">Total Santri Target</p>
                <p class="mt-2 text-3xl font-bold text-heading">{{ $stats['target_students'] }}</p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-heading">Rekap Bapak</h3>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-lg bg-surface-subtle p-3">
                        <p class="text-xs text-muted">Target</p>
                        <p class="mt-1 text-2xl font-bold text-heading">{{ $stats['father_target'] }}</p>
                    </div>
                    <div class="rounded-lg bg-success-soft p-3">
                        <p class="text-xs text-success-ink">Sudah Respon</p>
                        <p class="mt-1 text-2xl font-bold text-success-ink">{{ $stats['father_responded'] }}</p>
                    </div>
                    <div class="rounded-lg bg-warning-soft p-3">
                        <p class="text-xs text-warning-ink">Belum Respon</p>
                        <p class="mt-1 text-2xl font-bold text-warning-ink">{{ max($stats['father_target'] - $stats['father_responded'], 0) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-heading">Rekap Ibu</h3>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-lg bg-surface-subtle p-3">
                        <p class="text-xs text-muted">Target</p>
                        <p class="mt-1 text-2xl font-bold text-heading">{{ $stats['mother_target'] }}</p>
                    </div>
                    <div class="rounded-lg bg-success-soft p-3">
                        <p class="text-xs text-success-ink">Sudah Respon</p>
                        <p class="mt-1 text-2xl font-bold text-success-ink">{{ $stats['mother_responded'] }}</p>
                    </div>
                    <div class="rounded-lg bg-warning-soft p-3">
                        <p class="text-xs text-warning-ink">Belum Respon</p>
                        <p class="mt-1 text-2xl font-bold text-warning-ink">{{ max($stats['mother_target'] - $stats['mother_responded'], 0) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <div class="flex flex-col gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-heading">Rekap Detail per Wali Santri</h3>
                    <p class="mt-1 text-sm text-body">Menampilkan bapak, ibu, atau wali yang terhubung ke santri target event ini.</p>
                </div>

                <div class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-end">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-muted">Cari Wali / Anak / Kontak</span>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="guardianSearch"
                            class="mt-1 w-full rounded-lg border border-line-strong px-3 py-2 text-sm"
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
                                class="rounded-lg px-3 py-2 text-sm font-semibold {{ $this->filterStatus === $statusKey ? 'bg-warning-600 text-white' : 'border border-line-strong bg-surface text-body' }}"
                            >
                                {{ $statusLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-5">
                <div class="rounded-lg bg-surface-subtle p-3 text-center">
                    <p class="text-xs text-muted">Baris Tampil</p>
                    <p class="mt-1 text-2xl font-bold text-heading">{{ $filteredStats['total'] }}</p>
                </div>
                <div class="rounded-lg bg-success-soft p-3 text-center">
                    <p class="text-xs text-success-ink">Sudah Respon</p>
                    <p class="mt-1 text-2xl font-bold text-success-ink">{{ $filteredStats['responded'] }}</p>
                </div>
                <div class="rounded-lg bg-warning-soft p-3 text-center">
                    <p class="text-xs text-warning-ink">Belum Respon</p>
                    <p class="mt-1 text-2xl font-bold text-warning-ink">{{ $filteredStats['pending'] }}</p>
                </div>
                <div class="rounded-lg bg-warning-soft p-3 text-center">
                    <p class="text-xs text-warning-ink">Izin</p>
                    <p class="mt-1 text-2xl font-bold text-warning-ink">{{ $filteredStats['permission'] }}</p>
                </div>
                <div class="rounded-lg bg-danger-soft p-3 text-center">
                    <p class="text-xs text-danger-ink">Tidak Hadir</p>
                    <p class="mt-1 text-2xl font-bold text-danger-ink">{{ $filteredStats['not_attending'] }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-subtle">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold text-body">Nama Wali</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Peran</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Anak Terhubung</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Kontak</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Status</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Waktu Respon</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse ($filteredGuardianRows as $row)
                            <tr>
                                <td class="px-3 py-3 align-top">
                                    <p class="font-semibold text-heading">{{ $row['guardian_name'] }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ $row['guardian_gender'] }}</p>
                                </td>
                                <td class="px-3 py-3 align-top text-body">{{ implode(', ', $row['relationship_labels']) ?: '-' }}</td>
                                <td class="px-3 py-3 align-top text-body">{{ implode(', ', $row['student_names']) ?: '-' }}</td>
                                <td class="px-3 py-3 align-top text-body">
                                    <p>{{ $row['phone'] ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ $row['email'] ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ match ($row['attendance_status']) {
                                        'attending' => 'bg-success-soft-strong text-success-ink',
                                        'permission' => 'bg-warning-soft-strong text-warning-ink',
                                        'not_attending' => 'bg-danger-soft-strong text-danger-ink',
                                        default => 'bg-surface-muted text-body',
                                    } }}">
                                        {{ $row['attendance_label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top text-body">
                                    {{ $row['responded_at']?->locale('id')->translatedFormat('d F Y H:i') ?? '-' }}
                                </td>
                                <td class="px-3 py-3 align-top text-body">{{ $row['notes'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-muted">Tidak ada data wali yang cocok dengan filter saat ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-heading">Daftar Follow-up</h3>
                    <p class="mt-1 text-sm text-body">Fokus pada wali yang belum respon, izin, atau menyatakan tidak hadir.</p>
                </div>
                <span class="rounded-full bg-surface-muted px-3 py-1 text-sm font-semibold text-body">
                    {{ $followUpRows->count() }} wali perlu tindak lanjut
                </span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-subtle">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold text-body">Nama Wali</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Peran</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Anak Terhubung</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Kontak</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Status</th>
                            <th class="px-3 py-3 text-left font-semibold text-body">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse ($followUpRows as $row)
                            <tr>
                                <td class="px-3 py-3 align-top font-semibold text-heading">{{ $row['guardian_name'] }}</td>
                                <td class="px-3 py-3 align-top text-body">{{ implode(', ', $row['relationship_labels']) ?: '-' }}</td>
                                <td class="px-3 py-3 align-top text-body">{{ implode(', ', $row['student_names']) ?: '-' }}</td>
                                <td class="px-3 py-3 align-top text-body">
                                    <p>{{ $row['phone'] ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ $row['email'] ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ match ($row['attendance_status']) {
                                        'permission' => 'bg-warning-soft-strong text-warning-ink',
                                        'not_attending' => 'bg-danger-soft-strong text-danger-ink',
                                        default => 'bg-surface-muted text-body',
                                    } }}">
                                        {{ $row['attendance_label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top text-body">{{ $row['notes'] ?: 'Perlu dihubungi untuk konfirmasi.' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-muted">Semua wali sudah merespon dan tidak ada follow-up tertunda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
