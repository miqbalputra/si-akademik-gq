<x-filament-panels::page>
    @php
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
        $classroomOptions = $classroomOptions ?? [];
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <x-slot:heading>Atur Jadwal Sesi Diniyyah per Kelas</x-slot:heading>
            <p class="text-sm text-gray-600">
                Jam sesi disimpan per kelas (matrix hari × sesi). Ubah jam di sini lalu <strong>Simpan</strong> —
                Pantau Jurnal Kelas (wali) &amp; input jurnal guru langsung mengikuti tanpa deploy.
                Tombol <strong>Terapkan ke Ikhwan/Akhwat</strong> menyalin matrix kelas ini ke semua kelas gender sama
                (M2–M6 sesama band). <strong>Reset ke Default</strong> memulihkan jam dari kode.
            </p>
        </x-filament::section>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1 min-w-[260px]">
                <label class="text-xs font-bold uppercase tracking-wide text-gray-500">Kelas</label>
                <select wire:model.live="classroomId" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold focus:border-primary-500 focus:ring-primary-500">
                    @foreach ($classroomOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (empty($classroomOptions))
            <x-filament::section>
                <p class="text-sm text-gray-600">
                    Belum ada classroom Mustawa (Ikhwan/Akhwat). Buat dulu di <em>Struktur Kelas → Master Kelas</em>,
                    lalu refresh halaman ini.
                </p>
            </x-filament::section>
        @elseif (empty($rows))
            <x-filament::section>
                <p class="text-sm text-gray-600">Tidak ada sesi diniyyah. Tambah sesi di menu <em>Data Sekolah → Jam Pelajaran / Sesi</em>.</p>
            </x-filament::section>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Hari</th>
                            <th class="px-4 py-3">Sesi</th>
                            <th class="px-4 py-3">Mulai</th>
                            <th class="px-4 py-3">Selesai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $i => $row)
                            @php
                                $showDayHeader = $loop->first || $row['day'] !== $rows[$loop->index - 1]['day'];
                            @endphp
                            @if ($showDayHeader)
                                <tr class="bg-gray-50/60">
                                    <td colspan="4" class="px-4 py-2 font-bold text-gray-700">
                                        {{ $dayNames[$row['day']] ?? 'Hari '.$row['day'] }}
                                    </td>
                                </tr>
                            @endif
                            <tr class="{{ $row['is_break'] ? 'bg-amber-50/40' : '' }}">
                                <td class="px-4 py-2 text-gray-400"></td>
                                <td class="px-4 py-2 font-semibold">
                                    {{ \App\Support\SessionTimetable::label($row['session_name']) }}
                                    @if ($row['is_break'])
                                        <span class="text-xs text-amber-600">(istirahat)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <input type="time" wire:model.defer="rows.{{ $i }}.starts_at"
                                        value="{{ $row['starts_at'] ? substr($row['starts_at'], 0, 5) : '' }}"
                                        class="rounded-lg border border-gray-200 px-2 py-1 text-sm">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="time" wire:model.defer="rows.{{ $i }}.ends_at"
                                        value="{{ $row['ends_at'] ? substr($row['ends_at'], 0, 5) : '' }}"
                                        class="rounded-lg border border-gray-200 px-2 py-1 text-sm">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-gray-500">
                Baris dengan jam kosong = sesi tidak aktif di hari itu (tidak akan muncul di jurnal/monitoring).
                Kosongkan kedua jam untuk menghapus sesi di hari tersebut, lalu Simpan.
            </p>
        @endif
    </div>
</x-filament-panels::page>