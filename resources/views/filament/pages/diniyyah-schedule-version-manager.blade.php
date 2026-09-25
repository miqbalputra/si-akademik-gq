<x-filament-panels::page>
    @php
        $days = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $assignmentLabel = fn ($a) => trim(($a->teacher?->name ?? 'Guru belum diisi').' — '.($a->classSubject?->classroomTerm?->name ?? 'Kelas').' — '.($a->classSubject?->subject?->name ?? 'Mapel'));
        $statusLabel = fn ($row) => match ($row->version_status) { 'legacy' => 'Legacy · belum ditinjau', 'active' => 'Berlaku', 'superseded' => 'Riwayat', default => $row->version_status };
        $dateLabel = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('d M Y') : 'Tanpa batas';
        $selectStyle = 'width:100%;border:1px solid #d1d5db;border-radius:8px;padding:9px 11px;background:white;color:#111827;';
        $inputStyle = 'width:100%;border:1px solid #d1d5db;border-radius:8px;padding:9px 11px;background:white;color:#111827;';
    @endphp

    <x-filament::section icon="heroicon-o-calendar-days" heading="Jadwal berlaku per tanggal"
        description="Susun satu pola mingguan untuk satu penugasan. Jadwal legacy tetap dipakai sampai ditinjau; koreksi hanya mengubah slot yang diharapkan pada tanggal yang dipilih.">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
            <label style="display:grid;gap:6px;font-weight:600;">Penugasan (guru · kelas · mapel)
                <select wire:model.live="assignmentId" style="{{ $selectStyle }}">
                    @foreach ($assignments as $assignment)
                        <option value="{{ $assignment->id }}">{{ $assignmentLabel($assignment) }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display:grid;gap:6px;font-weight:600;">Jenis perubahan
                <select wire:model.live="changeType" style="{{ $selectStyle }}">
                    <option value="correction">Koreksi kesalahan</option>
                    <option value="approved">Perubahan disetujui</option>
                </select>
            </label>
            <label style="display:grid;gap:6px;font-weight:600;">Tanggal mulai berlaku
                <input type="date" wire:model.live="effectiveFrom" style="{{ $inputStyle }}">
            </label>
            <label style="display:grid;gap:6px;font-weight:600;">Tanggal akhir
                <input type="date" wire:model.live="effectiveUntil" style="{{ $inputStyle }}">
                <span style="font-size:12px;font-weight:400;color:#6b7280;">Wajib untuk koreksi. Kosong pada perubahan disetujui berarti berlaku seterusnya.</span>
            </label>
            <label style="display:grid;gap:6px;font-weight:600;">Alasan
                <input type="text" wire:model.live="reason" maxlength="3000" placeholder="Contoh: hari dan sesi pada jadwal awal keliru" style="{{ $inputStyle }}">
            </label>
            <label style="display:grid;gap:6px;font-weight:600;">Referensi pengajuan (opsional)
                <input type="text" wire:model.live="requestReference" maxlength="120" placeholder="Nomor / tautan / nama pengajuan" style="{{ $inputStyle }}">
            </label>
        </div>

        @error('assignmentId') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror
        @error('changeType') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror
        @error('effectiveFrom') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror
        @error('effectiveUntil') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror
        @error('reason') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror
        @error('requestReference') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror

        <div style="margin-top:18px;display:flex;justify-content:flex-end;">
            <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="loadCurrentPattern">Muat pola yang berlaku pada tanggal mulai</x-filament::button>
        </div>

        <div style="overflow-x:auto;margin-top:16px;">
            <table style="width:100%;border-collapse:collapse;min-width:540px;">
                <thead><tr style="background:#f3f4f6;text-align:left;">
                    <th style="padding:10px;border-bottom:1px solid #d1d5db;">Hari</th>
                    <th style="padding:10px;border-bottom:1px solid #d1d5db;">Sesi / Jam pelajaran</th>
                    <th style="padding:10px;border-bottom:1px solid #d1d5db;width:90px;">Aksi</th>
                </tr></thead>
                <tbody>
                    @foreach ($weeklySlots as $index => $slot)
                        <tr wire:key="schedule-slot-{{ $index }}">
                            <td style="padding:8px;border-bottom:1px solid #e5e7eb;">
                                <select wire:model.live="weeklySlots.{{ $index }}.day_of_week" style="{{ $selectStyle }}">
                                    <option value="">Pilih hari</option>
                                    @foreach ($days as $day => $label)<option value="{{ $day }}">{{ $label }}</option>@endforeach
                                </select>
                            </td>
                            <td style="padding:8px;border-bottom:1px solid #e5e7eb;">
                                <select wire:model.live="weeklySlots.{{ $index }}.class_session_id" style="{{ $selectStyle }}">
                                    <option value="">Pilih sesi</option>
                                    @foreach ($sessions as $session)
                                        <option value="{{ $session->id }}">Sesi {{ $session->session_name }}{{ $session->starts_at ? ' · '.substr($session->starts_at, 0, 5).'–'.substr($session->ends_at, 0, 5) : '' }}{{ $session->is_break ? ' · Istirahat' : '' }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:8px;border-bottom:1px solid #e5e7eb;">
                                <x-filament::button size="sm" color="danger" icon="heroicon-o-trash" wire:click="removeSlot({{ $index }})">Hapus</x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('slots') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror
        <div style="margin-top:12px;"><x-filament::button color="gray" icon="heroicon-o-plus" wire:click="addSlot">Tambah sesi mingguan</x-filament::button></div>

        <label style="display:grid;gap:6px;margin-top:20px;font-weight:600;">Tinjau satu log jadwal lama (opsional)
            <select wire:model.live="legacyLogId" style="{{ $selectStyle }}">
                <option value="">Tidak mengaitkan log lama</option>
                @foreach ($pendingLogs as $log)
                    <option value="{{ $log->id }}">#{{ $log->id }} · {{ $log->created_at?->locale('id')->translatedFormat('d M Y') }} · {{ $log->change_summary }}</option>
                @endforeach
            </select>
            <span style="font-size:12px;font-weight:400;color:#6b7280;">Log lama tidak menentukan tanggal berlaku otomatis. Pilih jenis dan tanggal di atas, lalu tandai log yang ditinjau.</span>
        </label>
        @error('legacyLogId') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror
    </x-filament::section>

    <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <x-filament::button icon="heroicon-o-eye" wire:click="preview" wire:loading.attr="disabled" wire:target="preview">Pratinjau slot kosong</x-filament::button>
    </div>
    @error('preview') <p style="color:#b91c1c;margin-top:8px;">{{ $message }}</p> @enderror

    @if ($preview)
        <x-filament::section style="margin-top:16px;" icon="heroicon-o-magnifying-glass" heading="Pratinjau laporan slot kosong" description="Periode perbandingan: {{ $preview['range_label'] }}. Jurnal terisi tetap dihitung sebagai terisi.">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;">
                <div>
                    <h3 style="font-weight:700;margin-bottom:8px;color:#047857;">Slot kosong yang akan hilang ({{ count($preview['gone']) }})</h3>
                    @forelse ($preview['gone'] as $row)
                        <div style="padding:10px 12px;margin-bottom:6px;border:1px solid #a7f3d0;border-radius:8px;background:#ecfdf5;">
                            <strong>{{ $row['date_label'] }}</strong> · {{ $row['session'] }} @if($row['session_time']) ({{ $row['session_time'] }}) @endif<br>
                            {{ implode(', ', $row['classes']) }} · {{ implode(', ', $row['subjects']) }}
                        </div>
                    @empty <p style="color:#6b7280;">Tidak ada slot kosong yang hilang.</p> @endforelse
                </div>
                <div>
                    <h3 style="font-weight:700;margin-bottom:8px;color:#b45309;">Slot kosong yang akan muncul ({{ count($preview['appeared']) }})</h3>
                    @forelse ($preview['appeared'] as $row)
                        <div style="padding:10px 12px;margin-bottom:6px;border:1px solid #fcd34d;border-radius:8px;background:#fffbeb;">
                            <strong>{{ $row['date_label'] }}</strong> · {{ $row['session'] }} @if($row['session_time']) ({{ $row['session_time'] }}) @endif<br>
                            {{ implode(', ', $row['classes']) }} · {{ implode(', ', $row['subjects']) }}
                        </div>
                    @empty <p style="color:#6b7280;">Tidak ada slot kosong baru.</p> @endforelse
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                <x-filament::button color="success" icon="heroicon-o-check" wire:click="apply" wire:loading.attr="disabled" wire:target="apply" onclick="return confirm('Terapkan versi jadwal ini? Jurnal tidak akan dihapus.')">Terapkan versi jadwal</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section style="margin-top:20px;" icon="heroicon-o-clock" heading="Versi jadwal tersimpan" description="Baris legacy belum ditinjau tetap berlaku seperti semula. Versi berstatus riwayat tidak digunakan oleh laporan tanggal.">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:860px;">
                <thead><tr style="background:#f3f4f6;text-align:left;">
                    <th style="padding:10px;border-bottom:1px solid #d1d5db;">Penugasan</th>
                    <th style="padding:10px;border-bottom:1px solid #d1d5db;">Hari / Sesi</th>
                    <th style="padding:10px;border-bottom:1px solid #d1d5db;">Berlaku</th>
                    <th style="padding:10px;border-bottom:1px solid #d1d5db;">Status</th>
                    <th style="padding:10px;border-bottom:1px solid #d1d5db;">Jenis / alasan</th>
                </tr></thead>
                <tbody>
                    @forelse ($versions as $version)
                        <tr>
                            <td style="padding:9px;border-bottom:1px solid #e5e7eb;">{{ $version->teacherAssignment ? $assignmentLabel($version->teacherAssignment) : 'Penugasan tidak tersedia' }}</td>
                            <td style="padding:9px;border-bottom:1px solid #e5e7eb;">{{ $days[$version->day_of_week] ?? 'Hari tidak dikenal' }} · Sesi {{ $version->classSession?->session_name ?? '-' }}</td>
                            <td style="padding:9px;border-bottom:1px solid #e5e7eb;">{{ $version->version_status === 'legacy' ? 'Belum ditinjau · perilaku lama' : $dateLabel($version->effective_from).' – '.$dateLabel($version->effective_until) }}</td>
                            <td style="padding:9px;border-bottom:1px solid #e5e7eb;">{{ $statusLabel($version) }}</td>
                            <td style="padding:9px;border-bottom:1px solid #e5e7eb;">{{ $version->change_type ? ($version->change_type === 'correction' ? 'Koreksi kesalahan' : 'Perubahan disetujui') : '-' }}{{ $version->change_reason ? ' · '.$version->change_reason : '' }}{{ $version->request_reference ? ' · Ref: '.$version->request_reference : '' }}</td>
                        </tr>
                    @empty <tr><td colspan="5" style="padding:12px;color:#6b7280;">Belum ada versi jadwal.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
