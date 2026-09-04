<?php

namespace App\Filament\Pages;

use App\Jobs\ReconcileRppSource;
use App\Models\RppSyncConflict;
use App\Models\RppSyncEvent;
use App\Models\RppSyncState;
use App\Models\RppSyncMapping;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RppSyncDashboard extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Kurikulum & RPP';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;
    protected static ?string $navigationLabel = 'Sinkronisasi RPP';
    protected static ?int $navigationSort = 35;
    protected string $view = 'filament.pages.rpp-sync-dashboard';
    public string $mappingType = 'teacher';
    public string $sourceId = '';
    public string $targetId = '';

    public static function canAccess(): bool { return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false; }

    public function syncNow(): void
    {
        if (! config('rpp_sync.enabled')) {
            Notification::make()->title('Integrasi RPP belum aktif')->warning()->send();
            return;
        }
        ReconcileRppSource::dispatch();
        Notification::make()->title('Sinkronisasi RPP dijadwalkan')->success()->send();
    }

    public function saveMapping(): void
    {
        $data = $this->validate(['mappingType' => ['required', 'in:teacher,class_subject'], 'sourceId' => ['required', 'string', 'max:255'], 'targetId' => ['required', 'integer', 'min:1']]);
        RppSyncMapping::updateOrCreate(['mapping_type' => $data['mappingType'], 'source_id' => $data['sourceId']], ['target_id' => $data['targetId']]);
        $this->reset(['sourceId', 'targetId']);
        ReconcileRppSource::dispatch();
        Notification::make()->title('Pemetaan disimpan; rekonsiliasi dijadwalkan')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'enabled' => config('rpp_sync.enabled'),
            'state' => RppSyncState::where('source', 'rpp-next')->first(),
            'conflicts' => RppSyncConflict::whereNull('resolved_at')->latest()->limit(20)->get(),
            'events' => RppSyncEvent::latest()->limit(20)->get(),
        ];
    }
}
