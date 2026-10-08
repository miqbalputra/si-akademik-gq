<x-filament-panels::page>
    @php
        $stats = $this->stats ?? [];
        $classesWithout = $stats['classes_without_assignment'] ?? 0;
        $selectStyle = 'border:1.5px solid var(--ui-line);border-radius:10px;padding:9px 12px;font-size:14px;font-weight:600;background:var(--ui-surface-subtle);color:var(--ui-heading);min-width:260px;';
        $labelStyle = 'font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:normal;color:var(--ui-muted);';
    @endphp

    <x-filament::section icon="heroicon-o-clipboard-document-check" heading="Ringkasan Data Penugasan Guru"
        description="Audit penugasan guru per kelas, mapel, peran, dan jadwal untuk satu periode ajaran.">
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            <p style="font-size:14px;color:var(--ui-text);line-height:20px;font-weight:400;">
                Tabel memuat <strong>semua penugasan</strong> di periode terpilih. Status
                <strong style="color:var(--ui-success-ink);font-weight:500;">Aktif</strong> = tanggal selesai kosong atau
                &ge; hari ini WIB; <strong style="color:var(--ui-text);font-weight:500;">Berakhir</strong> = sudah lewat.
            </p>
            <p style="font-size:14px;color:var(--ui-muted);line-height:20px;font-weight:400;">
                Gunakan kolom <em>search</em>, <em>filter</em>, dan <em>sort</em> di tabel untuk mengaudit.
                Untuk mengubah penugasan, buka menu <em>Penugasan Guru</em>.
            </p>
        </div>
    </x-filament::section>

    {{-- ===== FILTER PERIODE ===== --}}
    <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="{{ $labelStyle }};font-size:14px;font-weight:500;text-transform:none;line-height:20px;">Periode Ajaran</label>
            <select name="academicTermId" wire:model.live="academicTermId" style="{{ $selectStyle }};font-size:14px;font-weight:400;line-height:20px;">
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
                ['label' => 'Total Kelas', 'value' => $stats['total_classrooms'] ?? 0, 'color' => 'var(--ui-heading)', 'bg' => 'var(--ui-surface-subtle)', 'border' => 'var(--ui-line)'],
                ['label' => 'Total Penugasan', 'value' => $stats['total_assignments'] ?? 0, 'color' => 'var(--ui-brand-ink)', 'bg' => 'var(--ui-brand-soft)', 'border' => 'var(--ui-brand-line)'],
                ['label' => 'Aktif', 'value' => $stats['total_active'] ?? 0, 'color' => 'var(--ui-success-ink)', 'bg' => 'var(--ui-success-soft)', 'border' => 'var(--ui-success-line)'],
                ['label' => 'Berakhir', 'value' => $stats['total_inactive'] ?? 0, 'color' => 'var(--ui-text)', 'bg' => 'var(--ui-surface-muted)', 'border' => 'var(--ui-line-strong)'],
                ['label' => 'Guru Unik', 'value' => $stats['total_teachers_unique'] ?? 0, 'color' => 'var(--ui-on-color)', 'bg' => 'var(--color-brand-600)', 'border' => 'var(--color-brand-600)'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div style="border:1px solid {{ $card['border'] }};border-radius:14px;background:{{ $card['bg'] }};padding:16px 18px;">
                <p style="font-size:14px;color:{{ $card['label'] === 'Guru Unik' ? 'var(--ui-on-color)' : 'var(--ui-muted)' }};line-height:20px;font-weight:400;">{{ $card['label'] }}</p>
                <p style="margin-top:8px;font-size:30px;color:{{ $card['color'] }};line-height:38px;font-weight:700;">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($classesWithout > 0)
        <div style="margin-top:1rem;padding:14px 18px;border:1px solid var(--ui-warning-line);border-radius:14px;background:var(--ui-warning-soft);">
            <p style="font-size:14px;color:var(--ui-warning-ink);line-height:20px;font-weight:400;">
                <span style="margin-right:6px;">!</span> {{ $classesWithout }} kelas di periode ini belum punya penugasan guru aktif.
            </p>
            <p style="margin-top:4px;font-size:14px;color:var(--ui-warning-ink);line-height:20px;font-weight:400;">
                Cek apakah perlu dibuatkan penugasan baru di menu <em>Penugasan Guru</em>.
            </p>
        </div>
    @endif

    {{-- ===== INTERACTIVE FILAMENT TABLE ===== --}}
    <div style="margin-top:1.25rem;">
        {{ $this->getTable() }}
    </div>
</x-filament-panels::page>
