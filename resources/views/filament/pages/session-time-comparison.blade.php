<x-filament-panels::page>
    @php
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
        $ikhwanOptions = $ikhwanOptions ?? [];
        $akhwatOptions = $akhwatOptions ?? [];
        $hm = fn (?string $t) => $t ? substr($t, 0, 5) : '—';
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <x-slot:heading>Perbandingan Sesi Diniyyah: Ikhwan vs Akhwat</x-slot:heading>
            <p class="text-sm text-gray-600">
                Matrix Ikhwan &amp; Akhwat berbeda pada hari <strong>Senin</strong> (Ikhwan lebih pagai: 07:40 vs Akhwat 10:30).
                Khusus <strong>Kamis</strong>, Mustawa 1 tidak memiliki sesi Tafsir; M2–M6 memiliki Tafsir 09:50–10:20.
                Baris yang <span class="font-bold text-amber-700">BERBEDA</span> ditandai kuning.
            </p>
        </x-filament::section>

        <div class="flex flex-wrap items-end gap-4">
            <div class="flex flex-col gap-1 min-w-[220px]">
                <label class="text-xs font-bold uppercase tracking-wide text-gray-500">Kelas Ikhwan</label>
                <select wire:model.live="ikhwanClassroomId" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold focus:border-primary-500 focus:ring-primary-500">
                    @foreach ($ikhwanOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1 min-w-[220px]">
                <label class="text-xs font-bold uppercase tracking-wide text-gray-500">Kelas Akhwat</label>
                <select wire:model.live="akhwatClassroomId" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold focus:border-primary-500 focus:ring-primary-500">
                    @foreach ($akhwatOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (empty($rows))
            <x-filament::section>
                <p class="text-sm text-gray-600">Pilih satu kelas Ikhwan dan satu kelas Akhwat untuk membandingkan.</p>
            </x-filament::section>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Hari</th>
                            <th class="px-4 py-3">Sesi</th>
                            <th class="px-4 py-3">Ikhwan (Mulai – Selesai)</th>
                            <th class="px-4 py-3">Akhwat (Mulai – Selesai)</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            @php
                                $showDayHeader = $loop->first || $row['day'] !== $rows[$loop->index - 1]['day'];
                            @endphp
                            @if ($showDayHeader)
                                <tr class="bg-gray-50/60">
                                    <td colspan="5" class="px-4 py-2 font-bold text-gray-700">
                                        {{ $dayNames[$row['day']] ?? 'Hari '.$row['day'] }}
                                    </td>
                                </tr>
                            @endif
                            <tr class="{{ $row['differs'] ? 'bg-amber-50' : '' }}">
                                <td class="px-4 py-2 text-gray-400"></td>
                                <td class="px-4 py-2 font-semibold">{{ \App\Support\SessionTimetable::label($row['session_name']) }}</td>
                                <td class="px-4 py-2">
                                    @if ($row['ikhwan'])
                                        {{ $hm($row['ikhwan']['starts_at']) }} – {{ $hm($row['ikhwan']['ends_at']) }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($row['akhwat'])
                                        {{ $hm($row['akhwat']['starts_at']) }} – {{ $hm($row['akhwat']['ends_at']) }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if ($row['differs'])
                                        <span class="inline-block rounded-md bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-700">Berbeda</span>
                                    @else
                                        <span class="inline-block rounded-md bg-emerald-100 px-2 py-1 text-xs font-bold uppercase text-emerald-700">Sama</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>