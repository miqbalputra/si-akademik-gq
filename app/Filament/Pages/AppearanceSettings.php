<?php

namespace App\Filament\Pages;

use App\Services\ThemePaletteService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class AppearanceSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Pengaturan Tampilan';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.appearance-settings';

    /** @var array<string, string> */
    public array $palette = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasRole('admin') ?? false;
    }

    public function mount(ThemePaletteService $theme): void
    {
        $this->palette = $theme->palette();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pengaturan Tampilan';
    }

    public function save(ThemePaletteService $theme): void
    {
        $this->palette = $theme->save($this->palette);

        Notification::make()
            ->success()
            ->title('Palet warna disimpan')
            ->body('Warna baru akan digunakan di seluruh aplikasi.')
            ->send();

        $this->dispatch('gq-theme-palette-saved', css: $theme->cssVariables());
    }

    public function resetToDefaults(ThemePaletteService $theme): void
    {
        $this->palette = $theme->defaults();
    }

    public function safeColor(string $color): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : '#465FFF';
    }
}
