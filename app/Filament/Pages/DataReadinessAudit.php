<?php

namespace App\Filament\Pages;

use App\Services\DataReadinessAuditService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class DataReadinessAudit extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Data Sekolah';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Audit Kesiapan Data';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.data-readiness-audit';

    /** @var array<string, mixed> */
    public array $audit = [];

    public function mount(DataReadinessAuditService $auditService): void
    {
        $this->audit = $auditService->audit();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Audit Kesiapan Data';
    }

    public function refreshAudit(DataReadinessAuditService $auditService): void
    {
        $this->audit = $auditService->audit();
    }
}
