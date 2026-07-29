<x-filament-panels::page>
    @php
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
        $ikhwanOptions = $ikhwanOptions ?? [];
        $akhwatOptions = $akhwatOptions ?? [];
        $hm = fn (?string $t) => $t ? substr($t, 0, 5) : null;
        $selectStyle = 'border:1.5px solid var(--gray-200,#e5e7eb);border-radius:10px;padding:9px 12px;font-size:14px;font-weight:600;background:var(--gray-50,#f9fafb);color:var(--gray-800,#1f2937);min-width:240px;';
        $thStyle = 'text-align:left;padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500,#6b7280);border-bottom:1px solid var(--gray-200,#e5e7eb);';
        $tdStyle = 'padding:10px 14px;border-bottom:1px solid var(--gray-100,#f3f4f6);';
    @endphp

    <x-filament::section icon="heroicon-o-arrows-right-left" heading="Perbandingan Sesi Diniyyah: Ikhwan vs Akhwat"
        description="Lihat sesi mana yang sama & berbeda antar gender, per hari.">
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            <p style="font-size:14px;color:var(--gray-600,#4b5563);line-height:1.6;">
                Matrix Ikhwan &amp; Akhwat berbeda pada hari <strong>Senin</strong> (Ikhwan lebih pagi: 07:40 vs Akhwat 10:30).
                Khusus <strong>Kamis</strong>, Mustawa 1 tidak punya sesi Tafsir; M2–M6 punya Tafsir 09:50–10:20.
                Baris yang <strong style="color:var(--amber-700,#b45309);">BERBEDA</strong> ditandai kuning.
            </p>
            <p style="font-size:13px;color:var(--gray-500,#6b7280);line-height:1.6;">
                Halaman ini hanya untuk membandingkan — untuk mengubah jam, buka menu <em>Atur Jadwal Sesi Diniyyah</em>.
            </p>
        </div>
    </x-filament::section>

    <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500,#6b7280);">Kelas Ikhwan</label>
            <select wire:model.live="ikhwanClassroomId" style="{{ $selectStyle }}">
                @foreach ($ikhwanOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500,#6b7280);">Kelas Akhwat</label>
            <select wire:model.live="akhwatClassroomId" style="{{ $selectStyle }}">
                @foreach ($akhwatOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (empty($rows))
        <div style="margin-top:1.5rem;padding:20px;border:1.5px dashed var(--gray-200,#e5e7eb);border-radius:14px;background:var(--gray-50,#f9fafb);color:var(--gray-500,#6b7280);font-size:14px;">
            Pilih satu kelas Ikhwan dan satu kelas Akhwat untuk membandingkan.
        </div>
    @else
        <div style="margin-top:1.25rem;overflow-x:auto;border:1px solid var(--gray-200,#e5e7eb);border-radius:14px;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="background:var(--gray-100,#f3f4f6);">
                        <th style="{{ $thStyle }}">Sesi</th>
                        <th style="{{ $thStyle }}">Ikhwan (Mulai – Selesai)</th>
                        <th style="{{ $thStyle }}">Akhwat (Mulai – Selesai)</th>
                        <th style="{{ $thStyle }}text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $showDayHeader = $loop->first || $row['day'] !== $rows[$loop->index - 1]['day'];
                            $rowBg = $row['differs'] ? 'background:var(--amber-50,#fffbeb);' : '';
                        @endphp
                        @if ($showDayHeader)
                            <tr>
                                <td colspan="4" style="padding:9px 14px;background:var(--gray-50,#f9fafb);border-top:1px solid var(--gray-200,#e5e7eb);border-bottom:1px solid var(--gray-200,#e5e7eb);font-weight:800;color:var(--gray-700,#374151);font-size:13px;">
                                    {{ $dayNames[$row['day']] ?? 'Hari '.$row['day'] }}
                                </td>
                            </tr>
                        @endif
                        <tr style="{{ $rowBg }}">
                            <td style="padding:10px 14px;font-weight:600;color:var(--gray-700,#374151);border-bottom:1px solid var(--gray-100,#f3f4f6);">
                                {{ \App\Support\SessionTimetable::label($row['session_name']) }}
                            </td>
                            <td style="padding:10px 14px;border-bottom:1px solid var(--gray-100,#f3f4f6);">
                                @if ($row['ikhwan'])
                                    <span style="font-weight:600;color:var(--gray-800,#1f2937);">{{ $hm($row['ikhwan']['starts_at']) }}</span>
                                    <span style="color:var(--gray-400,#9ca3af);"> – </span>
                                    <span style="font-weight:600;color:var(--gray-800,#1f2937);">{{ $hm($row['ikhwan']['ends_at']) }}</span>
                                @else
                                    <span style="color:var(--gray-400,#9ca3af);">—</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;border-bottom:1px solid var(--gray-100,#f3f4f6);">
                                @if ($row['akhwat'])
                                    <span style="font-weight:600;color:var(--gray-800,#1f2937);">{{ $hm($row['akhwat']['starts_at']) }}</span>
                                    <span style="color:var(--gray-400,#9ca3af);"> – </span>
                                    <span style="font-weight:600;color:var(--gray-800,#1f2937);">{{ $hm($row['akhwat']['ends_at']) }}</span>
                                @else
                                    <span style="color:var(--gray-400,#9ca3af);">—</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;text-align:center;border-bottom:1px solid var(--gray-100,#f3f4f6);">
                                @if ($row['differs'])
                                    <span style="display:inline-block;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;text-transform:uppercase;background:var(--amber-100,#fef3c7);color:var(--amber-800,#92400e);">Berbeda</span>
                                @else
                                    <span style="display:inline-block;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;text-transform:uppercase;background:var(--emerald-100,#d1fae5);color:var(--emerald-800,#065f46);">Sama</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>