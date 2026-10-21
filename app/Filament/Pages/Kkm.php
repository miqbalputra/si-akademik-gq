<?php

namespace App\Filament\Pages;

use App\Models\DiniyyahKkmRevision;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Kkm extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Diniyyah';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Penetapan KKM';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'kkm-menu';

    protected string $view = 'filament.pages.kkm';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false;
    }

    public static function getNavigationUrl(): string
    {
        return route('admin.kkm.index');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) DiniyyahKkmRevision::where('status', 'submitted')->count();
    }
}
