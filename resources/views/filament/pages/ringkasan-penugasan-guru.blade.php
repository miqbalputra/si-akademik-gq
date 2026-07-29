<x-filament-panels::page>
    @php
        $stats = $this->stats ?? [];
        $classesWithout = $stats['classes_without_assignment'] ?? 0;
        $selectStyle = 'border:1.5px solid var(--gray-200,#e5e7eb);border-radius:10px;padding:9px 12px;font-size:14px;font-weight:600;background:var(--gray-50,#f9fafb);color:var(--gray-800,#1f2937);min-width:260px;';
        $labelStyle = 'font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500,#6b7280);';
    @endphp

    <x-filament::section icon="heroicon-o-clipboard-document-check" heading="Ringkasan Data Penugasan Guru"
        description="Audit penugasan guru per kelas, mapel, peran, dan jadwal untuk satu periode ajaran.">
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            <p style="font-size:14px;color:var(--gray-600,#4b5563);line-height:1.6;">
                Tabel memuat <strong>semua penugasan</strong> di periode terpilih. Status
                <strong style="color:var(--emerald-700,#047857);">Aktif</strong> = tanggal selesai kosong atau
                &ge; hari ini WIB; <strong style="color:var(--gray-600,#4b5563);">Berakhir</strong> = sudah lewat.
            </p>
            <p style="font-size:13px;color:var(--gray-500,#6b7280);line-height:1.6;">
                Gunakan kolom <em>search</em>, <em>filter</em>, dan <em>sort</em> di tabel untuk mengaudit.
                Untuk mengubah penugasan, buka menu <em>Penugasan Guru</em>.
            </p>
        </div>
    </x-filament::section>

    {{-- ===== FILTER PERIODE ===== --}}
    <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="{{ $labelStyle }}">Periode Ajaran</label>
            <select name="academicTermId" wire:model.live="academicTermId" style="{{ $selectStyle }}">
                @foreach ($termOptions as $termOpt)
                    <option value="{{ $termOpt['id'] }}" @selected((string) $termOpt['id'] === (string) $this->academicTermId)>{{ $termOpt['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ===== STAT CARDS ===== --}}
    <div style="margin-top:1.25rem;display:grid;gap:0.75rem;grid-template-columns:repeat(2,minmax(0,1fr));">
        @php
            $cards = [
                ['label' => 'Total Kelas', 'value' => $stats['total_classrooms'] ?? 0, 'color' => 'var(--gray-800,#1f2937)', 'bg' => 'var(--gray-50,#f9fafb)', 'border' => 'var(--gray-200,#e5e7eb)'],
                ['label' => 'Total Penugasan', 'value' => $stats['total_assignments'] ?? 0, 'color' => 'var(--indigo-900,#312e81)', 'bg' => 'var(--indigo-50,#eef2ff)', 'border' => 'var(--indigo-200,#c7d2fe)'],
                ['label' => 'Aktif', 'value' => $stats['total_active'] ?? 0, 'color' => 'var(--emerald-900,#064e3b)', 'bg' => 'var(--emerald-50,#ecfdf5)', 'border' => 'var(--emerald-200,#a7f3d0)'],
                ['label' => 'Berakhir', 'value' => $stats['total_inactive'] ?? 0, 'color' => 'var(--gray-700,#374151)', 'bg' => 'var(--gray-100,#f3f4f6)', 'border' => 'var(--gray-300,#d1d5db)'],
                ['label' => 'Guru Unik', 'value' => $stats['total_teachers_unique'] ?? 0, 'color' => '#ffffff', 'bg' => 'var(--gray-800,#1f2937)', 'border' => 'var(--gray-800,#1f2937)'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div style="border:1px solid {{ $card['border'] }};border-radius:14px;background:{{ $card['bg'] }};padding:16px 18px;">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:{{ $card['label'] === 'Guru Unik' ? 'var(--gray-300,#d1d5db)' : 'var(--gray-500,#6b7280)' }};">{{ $card['label'] }}</p>
                <p style="margin-top:8px;font-size:30px;font-weight:800;line-height:1;color:{{ $card['color'] }};">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($classesWithout > 0)
        <div style="margin-top:1rem;padding:14px 18px;border:1px solid var(--amber-300,#fcd34d);border-radius:14px;background:var(--amber-50,#fffbeb);">
            <p style="font-size:14px;font-weight:700;color:var(--amber-800,#92400e);line-height:1.5;">
                <span style="margin-right:6px;">!</span> {{ $classesWithout }} kelas di periode ini belum punya penugasan guru aktif.
            </p>
            <p style="margin-top:4px;font-size:13px;color:var(--amber-700,#b45309);line-height:1.5;">
                Cek apakah perlu dibuatkan penugasan baru di menu <em>Penugasan Guru</em>.
            </p>
        </div>
    @endif

    {{-- ===== INTERACTIVE FILAMENT TABLE ===== --}}
    <div style="margin-top:1.25rem;">
        {{ $this->getTable() }}
    </div>
</x-filament-panels::page>