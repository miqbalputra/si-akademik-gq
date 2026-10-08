<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <x-filament::section
            heading="Palet warna global"
            description="Warna ini diterapkan pada portal sekolah dan panel admin untuk seluruh pengguna."
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    'primary' => ['label' => 'Utama', 'description' => 'Tombol utama, tautan aktif, dan aksen navigasi.'],
                    'info' => ['label' => 'Informasi', 'description' => 'Info, tautan sekunder, dan indikator informasi.'],
                    'success' => ['label' => 'Berhasil', 'description' => 'Status selesai dan konfirmasi positif.'],
                    'warning' => ['label' => 'Peringatan', 'description' => 'Status yang memerlukan perhatian.'],
                    'danger' => ['label' => 'Bahaya', 'description' => 'Kesalahan dan tindakan berisiko.'],
                ] as $key => $color)
                    <label class="flex items-center gap-4 rounded-xl border border-line bg-surface p-4 shadow-sm dark:border-gray-800 dark:bg-surface ui-form-label">
                        <input
                            type="color"
                            wire:model.live.debounce.150ms="palette.{{ $key }}"
                            aria-label="Warna {{ strtolower($color['label']) }}"
                            class="h-12 w-12 cursor-pointer rounded-lg border-0 bg-transparent p-0"
                        >
                        <span class="min-w-0 flex-1">
                            <span class="block text-theme-sm font-medium text-heading dark:text-white/90">{{ $color['label'] }}</span>
                            <span class="mt-1 block text-theme-xs text-muted dark:text-muted font-normal">{{ $color['description'] }}</span>
                            <span class="mt-2 block font-sans text-theme-xs font-normal text-body dark:text-body">{{ $palette[$key] ?? '#465FFF' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section heading="Pratinjau">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" style="background-color: {{ $this->safeColor($palette['primary'] ?? '') }}; color:var(--ui-on-color)" class="inline-flex min-h-10 items-center rounded-lg px-4 shadow-sm text-theme-sm font-medium">
                    Tombol utama
                </button>
                @foreach ([
                    'info' => 'Informasi',
                    'success' => 'Berhasil',
                    'warning' => 'Peringatan',
                    'danger' => 'Bahaya',
                ] as $key => $label)
                    <span style="border-color: {{ $this->safeColor($palette[$key] ?? '') }}; color: {{ $this->safeColor($palette[$key] ?? '') }}" class="rounded-full border px-3 py-1 text-theme-xs font-medium">
                        {{ $label }}
                    </span>
                @endforeach
            </div>
            <p class="mt-4 text-theme-sm text-muted dark:text-muted">
                Nuansa terang dan gelap dibuat dari warna pilihan. Warna netral dan teks mengikuti tema TailAdmin.
            </p>
        </x-filament::section>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-filament::button type="button" color="gray" wire:click="resetToDefaults">
                Pulihkan warna TailAdmin
            </x-filament::button>
            <x-filament::button type="submit" wire:loading.attr="disabled">
                Simpan palet global
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
