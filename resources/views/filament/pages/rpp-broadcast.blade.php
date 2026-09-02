<x-filament-panels::page>
    <x-filament::section heading="Kirim Pesan RPP" description="Pesan dikirim melalui pusat notifikasi aplikasi kepada peran yang dipilih.">
        <form wire:submit="send" class="space-y-5">
            <div><label class="fi-fo-field-wrp-label">Penerima</label><select wire:model="audience" class="fi-input mt-1 w-full"><option value="guru">Semua Guru</option><option value="kabag_diniyyah">Kabag Diniyyah</option><option value="kepala_sekolah">Kepala Sekolah</option><option value="admin">Admin</option></select>@error('audience')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror</div>
            <div><label class="fi-fo-field-wrp-label">Judul</label><input wire:model="broadcastTitle" class="fi-input mt-1 w-full" maxlength="160">@error('broadcastTitle')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror</div>
            <div><label class="fi-fo-field-wrp-label">Pesan</label><textarea wire:model="body" class="fi-input mt-1 w-full" rows="6" maxlength="2000"></textarea>@error('body')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror</div>
            <x-filament::button type="submit">Kirim notifikasi</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
