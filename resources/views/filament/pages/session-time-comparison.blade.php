<x-filament-panels::page>
    @php
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
        $ikhwanOptions = $ikhwanOptions ?? [];
        $akhwatOptions = $akhwatOptions ?? [];
        $hm = fn (?string $t) => $t ? substr($t, 0, 5) : null;
        $selectStyle = 'border:1.5px solid var(--ui-line);border-radius:10px;padding:9px 12px;font-size:14px;font-weight:600;background:var(--ui-surface-subtle);color:var(--ui-heading);min-width:240px;';
        $thStyle = 'text-align:left;padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ui-muted);border-bottom:1px solid var(--ui-line);';
        $tdStyle = 'padding:10px 14px;border-bottom:1px solid var(--ui-line);';
    @endphp

    <x-filament::section icon="heroicon-o-arrows-right-left" heading="Perbandingan Sesi Diniyyah: Ikhwan vs Akhwat"
        description="Lihat sesi mana yang sama & berbeda antar gender, per hari.">
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            <p style="font-size:14px;color:var(--ui-text);line-height:1.6;">
                Matrix Ikhwan &amp; Akhwat berbeda pada hari <strong>Senin</strong> (Ikhwan lebih pagi: 07:40 vs Akhwat 10:30).
                Khusus <strong>Kamis</strong>, Mustawa 1 tidak punya sesi Tafsir; M2–M6 punya Tafsir 09:50–10:20.
                Baris yang <strong style="color:var(--ui-warning-ink);">BERBEDA</strong> ditandai kuning.
            </p>
            <p style="font-size:13px;color:var(--ui-muted);line-height:1.6;">
                Halaman ini hanya untuk membandingkan — untuk mengubah jam, buka menu <em>Atur Jadwal Sesi Diniyyah</em>.
            </p>
        </div>
    </x-filament::section>

    <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ui-muted);">Kelas Ikhwan</label>
            <select wire:model.live="ikhwanClassroomId" style="{{ $selectStyle }}">
                @foreach ($ikhwanOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ui-muted);">Kelas Akhwat</label>
            <select wire:model.live="akhwatClassroomId" style="{{ $selectStyle }}">
                @foreach ($akhwatOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (empty($rows))
        <div style="margin-top:1.5rem;padding:20px;border:1.5px dashed var(--ui-line);border-radius:14px;background:var(--ui-surface-subtle);color:var(--ui-muted);font-size:14px;">
            Pilih satu kelas Ikhwan dan satu kelas Akhwat untuk membandingkan.
        </div>
    @else
        <div style="margin-top:1.25rem;overflow-x:auto;border:1px solid var(--ui-line);border-radius:14px;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="background:var(--ui-surface-muted);">
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
                            $rowBg = $row['differs'] ? 'background:var(--ui-warning-soft);' : '';
                        @endphp
                        @if ($showDayHeader)
                            <tr>
                                <td colspan="4" style="padding:9px 14px;background:var(--ui-surface-subtle);border-top:1px solid var(--ui-line);border-bottom:1px solid var(--ui-line);font-weight:600;color:var(--ui-text);font-size:13px;">
                                    {{ $dayNames[$row['day']] ?? 'Hari '.$row['day'] }}
                                </td>
                            </tr>
                        @endif
                        <tr style="{{ $rowBg }}">
                            <td style="padding:10px 14px;font-weight:600;color:var(--ui-text);border-bottom:1px solid var(--ui-line);">
                                {{ \App\Support\SessionTimetable::label($row['session_name']) }}
                            </td>
                            <td style="padding:10px 14px;border-bottom:1px solid var(--ui-line);">
                                @if ($row['ikhwan'])
                                    <span style="font-weight:600;color:var(--ui-heading);">{{ $hm($row['ikhwan']['starts_at']) }}</span>
                                    <span style="color:var(--ui-soft);"> – </span>
                                    <span style="font-weight:600;color:var(--ui-heading);">{{ $hm($row['ikhwan']['ends_at']) }}</span>
                                @else
                                    <span style="color:var(--ui-soft);">—</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;border-bottom:1px solid var(--ui-line);">
                                @if ($row['akhwat'])
                                    <span style="font-weight:600;color:var(--ui-heading);">{{ $hm($row['akhwat']['starts_at']) }}</span>
                                    <span style="color:var(--ui-soft);"> – </span>
                                    <span style="font-weight:600;color:var(--ui-heading);">{{ $hm($row['akhwat']['ends_at']) }}</span>
                                @else
                                    <span style="color:var(--ui-soft);">—</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;text-align:center;border-bottom:1px solid var(--ui-line);">
                                @if ($row['differs'])
                                    <span style="display:inline-block;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;text-transform:uppercase;background:var(--ui-warning-soft-strong);color:var(--ui-warning-ink);">Berbeda</span>
                                @else
                                    <span style="display:inline-block;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;text-transform:uppercase;background:var(--ui-success-soft-strong);color:var(--ui-success-ink);">Sama</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>