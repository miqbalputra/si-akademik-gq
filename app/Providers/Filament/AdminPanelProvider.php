<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Actions\Action;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->brandName('Ruang GQ')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(null)
            ->colors(['primary' => Color::Green])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.partials.google-login')->render(),
            )
            ->navigationGroups([
                NavigationGroup::make('Data Sekolah')->collapsible(),
                NavigationGroup::make('Struktur Kelas')->collapsible(),
                NavigationGroup::make('Diniyyah')->collapsible(),
                NavigationGroup::make('Kurikulum & RPP')->collapsible(),
                NavigationGroup::make('Tahfidz')->collapsible(),
                NavigationGroup::make('Leger & Rapor')->collapsible(),
                NavigationGroup::make('Pengaturan')->collapsible(),
            ])
            ->userMenuItems([
                Action::make('workspace_guru')
                    ->label('Buka Portal Guru')
                    ->icon('heroicon-o-academic-cap')
                    ->url(fn (): string => route('guru.dashboard'))
                    ->visible(fn (): bool => auth()->user()?->hasRole('guru') ?? false),
                Action::make('workspace_kabag_tahfidz')
                    ->label('Buka Kabag Tahfidz')
                    ->icon('heroicon-o-book-open')
                    ->url(fn (): string => route('kabag-tahfidz.dashboard'))
                    ->visible(fn (): bool => auth()->user()?->hasRole('kabag_tahfidz') ?? false),
                Action::make('workspace_kabag_diniyyah')
                    ->label('Buka Kabag Diniyyah')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (): string => route('kabag-diniyyah.dashboard'))
                    ->visible(fn (): bool => auth()->user()?->hasRole('kabag_diniyyah') ?? false),
                Action::make('workspace_wali')
                    ->label('Buka Portal Wali')
                    ->icon('heroicon-o-users')
                    ->url(fn (): string => route('wali.dashboard'))
                    ->visible(fn (): bool => auth()->user()?->hasRole('wali_santri') ?? false),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.partials.notifications')->render(),
            );
    }
}
