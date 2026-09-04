<x-filament-panels::page>
    <x-filament::section heading="Status Integrasi Project RPP">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><strong>{{ $enabled ? 'Aktif' : 'Belum aktif' }}</strong><p class="text-sm text-gray-500">Sinkron terakhir: {{ $state?->last_synced_at?->diffForHumans() ?? 'belum pernah' }}</p>@if($state?->last_error)<p class="mt-1 text-sm text-danger-600">{{ $state->last_error }}</p>@endif</div>
            <x-filament::button wire:click="syncNow">Sinkronkan sekarang</x-filament::button>
        </div>
    </x-filament::section>
    <x-filament::section heading="Konflik pemetaan" description="RPP yang belum cocok dengan guru, mapel, kelas, atau penugasan sekolah ditahan sampai data master diperbaiki.">
        <form wire:submit="saveMapping" class="mb-5 grid gap-3 md:grid-cols-4"><select wire:model="mappingType" class="rounded-lg border-gray-300"><option value="teacher">Guru</option><option value="class_subject">Mapel + kelas</option></select><input wire:model="sourceId" class="rounded-lg border-gray-300" placeholder="ID sumber (mapel|kelas untuk pasangan)"><input wire:model="targetId" class="rounded-lg border-gray-300" placeholder="ID target sekolah" inputmode="numeric"><x-filament::button type="submit">Simpan pemetaan</x-filament::button></form>
        <div class="space-y-3">@forelse($conflicts as $conflict)<div class="rounded-lg border p-3"><strong>{{ $conflict->source_type }} · {{ $conflict->source_id }}</strong><p class="text-sm text-danger-600">{{ $conflict->reason }}</p><pre class="mt-2 overflow-auto text-xs text-gray-500">{{ json_encode($conflict->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>@empty<p class="text-sm text-gray-500">Tidak ada konflik aktif.</p>@endforelse</div>
    </x-filament::section>
    <x-filament::section heading="Event terakhir"><div class="space-y-2">@forelse($events as $event)<p class="text-sm"><strong>{{ $event->event_type }}</strong> · {{ $event->entity_id }} · <span class="text-gray-500">{{ $event->status }}</span></p>@empty<p class="text-sm text-gray-500">Belum ada webhook diterima.</p>@endforelse</div></x-filament::section>
</x-filament-panels::page>
