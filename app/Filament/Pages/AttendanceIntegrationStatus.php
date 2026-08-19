<?php

namespace App\Filament\Pages;

use App\Services\AttendanceIntegrationStatusService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class AttendanceIntegrationStatus extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Data Sekolah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Integrasi GeoPresensi';

    protected static ?int $navigationSort = 65;

    protected string $view = 'filament.pages.attendance-integration-status';

    /** @var array<string, mixed> */
    public array $audit = [];

    public function mount(AttendanceIntegrationStatusService $statusService): void
    {
        $this->audit = $statusService->audit();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Integrasi GeoPresensi';
    }

    public function refreshStatus(AttendanceIntegrationStatusService $statusService): void
    {
        $this->audit = $statusService->audit(force: true);
    }
}
