<?php

namespace App\Providers\Filament;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\AuthenticateAdminPanel;
use App\Services\WorkspaceRedirectService;
use App\Support\AdminNavigation;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
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
            ->authGuard('admin')
            ->login([AuthenticatedSessionController::class, 'redirectToMainLogin'])
            ->brandName('Ruang GQ')
            ->font('Outfit', url: asset('fonts/outfit/outfit.css'), provider: LocalFontProvider::class)
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(null)
            ->colors([
                'primary' => Color::Indigo,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigation(fn (NavigationBuilder $builder) => app(AdminNavigation::class)->build($builder))
            ->userMenuItems([
                Action::make('workspace_guru')
                    ->label('Buka Portal Guru')
                    ->icon('heroicon-o-academic-cap')
                    ->url(fn (): string => route('workspace.open-portal', ['workspace' => WorkspaceRedirectService::GURU]))->postToUrl()
                    ->visible(fn (): bool => auth()->user()?->hasRole('guru') ?? false),
                Action::make('workspace_kabag_tahfidz')
                    ->label('Buka Kabag Tahfidz')
                    ->icon('heroicon-o-book-open')
                    ->url(fn (): string => route('workspace.open-portal', ['workspace' => WorkspaceRedirectService::KABAG_TAHFIDZ]))->postToUrl()
                    ->visible(fn (): bool => auth()->user()?->hasRole('kabag_tahfidz') ?? false),
                Action::make('workspace_kabag_diniyyah')
                    ->label('Buka Kabag Diniyyah')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (): string => route('workspace.open-portal', ['workspace' => WorkspaceRedirectService::KABAG_DINIYYAH]))->postToUrl()
                    ->visible(fn (): bool => auth()->user()?->hasRole('kabag_diniyyah') ?? false),
                Action::make('workspace_wali')
                    ->label('Buka Portal Wali')
                    ->icon('heroicon-o-users')
                    ->url(fn (): string => route('workspace.open-portal', ['workspace' => WorkspaceRedirectService::WALI]))->postToUrl()
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
            ->authMiddleware([AuthenticateAdminPanel::class], isPersistent: true)
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => view('filament.partials.navigation-search')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.partials.notifications')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.partials.theme-runtime')->render(),
            );
    }
}
