<x-filament-panels::page>
    @php
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
        $classroomOptions = $classroomOptions ?? [];
        $inputStyle = 'border:1.5px solid var(--ui-line);border-radius:8px;padding:6px 10px;font-size:14px;font-weight:500;background:var(--ui-surface);color:var(--ui-heading);width:118px;';
        $selectStyle = 'border:1.5px solid var(--ui-line);border-radius:10px;padding:9px 12px;font-size:14px;font-weight:600;background:var(--ui-surface-subtle);color:var(--ui-heading);min-width:260px;';
    @endphp

    <x-filament::section icon="heroicon-o-clock" heading="Atur Jadwal Sesi Diniyyah per Kelas"
        description="Ubah jam sesi per kelas tanpa deploy. Pantau Jurnal Kelas & input jurnal guru langsung mengikuti.">
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            <p style="font-size:14px;color:var(--ui-text);line-height:20px;font-weight:400;">
                Pilih kelas, atur jam tiap sesi, lalu klik <strong>Simpan</strong> di bawah tabel.
                <strong>Terapkan ke Ikhwan/Akhwat</strong> menyalin matrix kelas ini ke semua kelas gender sama
                (M2–M6 sesama band; M1 hanya ke M1). <strong>Reset ke Default</strong> memulihkan jam dari kode.
            </p>
            <p style="font-size:14px;color:var(--ui-muted);line-height:20px;font-weight:400;">
                Baris dengan jam <em>kosong</em> = sesi tidak aktif di hari itu (tidak muncul di jurnal/monitoring).
                Kosongkan kedua jam untuk meniadakan sesi, lalu Simpan.
            </p>
        </div>
    </x-filament::section>

    <div style="margin-top:1rem;">
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="letter-spacing:normal;color:var(--ui-muted);font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Kelas</label>
            <select wire:model.live="classroomId" style="{{ $selectStyle }};font-size:14px;font-weight:400;line-height:20px;">
                <option value="">— Pilih Kelas —</option>
                @foreach ($classroomOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (empty($classroomOptions))
        <div style="margin-top:1.5rem;padding:20px;border:1.5px dashed var(--ui-line);border-radius:14px;background:var(--ui-surface-subtle);color:var(--ui-muted);font-size:14px;line-height:20px;">
            Belum ada classroom Mustawa (Ikhwan/Akhwat). Buat dulu di <em>Struktur Kelas → Master Kelas</em>, lalu refresh halaman ini.
        </div>
    @elseif (empty($rows))
        <div style="margin-top:1.5rem;padding:20px;border:1.5px dashed var(--ui-line);border-radius:14px;background:var(--ui-surface-subtle);color:var(--ui-muted);font-size:14px;line-height:20px;">
            Tidak ada sesi diniyyah. Tambah sesi di menu <em>Data Sekolah → Jam Pelajaran / Sesi</em>.
        </div>
    @else
        <div style="margin-top:1.25rem;overflow-x:auto;border:1px solid var(--ui-line);border-radius:14px;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;line-height:20px;">
                <thead>
                    <tr style="background:var(--ui-surface-muted);">
                        <th style="text-align:left;padding:10px 14px;letter-spacing:normal;color:var(--ui-muted);border-bottom:1px solid var(--ui-line);font-size:12px;font-weight:500;text-transform:none;line-height:18px;">Sesi</th>
                        <th style="text-align:left;padding:10px 14px;letter-spacing:normal;color:var(--ui-muted);border-bottom:1px solid var(--ui-line);font-size:12px;font-weight:500;text-transform:none;line-height:18px;">Mulai</th>
                        <th style="text-align:left;padding:10px 14px;letter-spacing:normal;color:var(--ui-muted);border-bottom:1px solid var(--ui-line);font-size:12px;font-weight:500;text-transform:none;line-height:18px;">Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        @php
                            $showDayHeader = $loop->first || $row['day'] !== $rows[$loop->index - 1]['day'];
                            $rowBg = ($row['is_break'] ?? false) ? 'background:var(--ui-warning-soft);' : '';
                        @endphp
                        @if ($showDayHeader)
                            <tr>
                                <td colspan="3" style="padding:9px 14px;background:var(--ui-surface-subtle);border-top:1px solid var(--ui-line);border-bottom:1px solid var(--ui-line);font-weight:500;color:var(--ui-text);font-size:14px;line-height:20px;">
                                    {{ $dayNames[$row['day']] ?? 'Hari '.$row['day'] }}
                                </td>
                            </tr>
                        @endif
                        <tr style="{{ $rowBg }}border-bottom:1px solid var(--ui-surface-muted);">
                            <td style="padding:10px 14px;font-weight:500;color:var(--ui-text);">
                                {{ \App\Support\SessionTimetable::label($row['session_name']) }}
                                @if ($row['is_break'] ?? false)
                                    <span style="font-size:12px;color:var(--ui-warning-ink);line-height:18px;font-weight:400;">· istirahat</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;">
                                <input type="time" wire:model.defer="rows.{{ $i }}.starts_at"
                                    value="{{ $row['starts_at'] ? substr($row['starts_at'], 0, 5) : '' }}"
                                    style="{{ $inputStyle }};font-size:14px;font-weight:400;line-height:20px;">
                            </td>
                            <td style="padding:10px 14px;">
                                <input type="time" wire:model.defer="rows.{{ $i }}.ends_at"
                                    value="{{ $row['ends_at'] ? substr($row['ends_at'], 0, 5) : '' }}"
                                    style="{{ $inputStyle }};font-size:14px;font-weight:400;line-height:20px;">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <x-filament::button wire:click="save" icon="heroicon-o-check" size="md">Simpan</x-filament::button>
            <x-filament::button wire:click="propagate('ikhwan')" color="info" icon="heroicon-o-arrow-right-circle">Terapkan ke Ikhwan</x-filament::button>
            <x-filament::button wire:click="propagate('akhwat')" color="info" icon="heroicon-o-arrow-right-circle">Terapkan ke Akhwat</x-filament::button>
            <x-filament::button wire:click="resetToDefault" color="danger" icon="heroicon-o-arrow-path"
                wire:confirm="Jam sesi kelas ini akan dipulihkan ke default kode. Perubahan tersimpan akan hilang. Lanjut?">Reset ke Default</x-filament::button>
        </div>
    @endif
</x-filament-panels::page>