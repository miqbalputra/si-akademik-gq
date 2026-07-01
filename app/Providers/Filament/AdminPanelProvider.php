<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->brandName('SIAKAD Griya Qur\'an')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(null)
            ->colors([
                'primary' => Color::Amber,
            ])
            // ── Inject Outfit font + premium auth-page CSS ─────────────────
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
                    <style>
                        /* ── Global font override ── */
                        *, body, .fi-body { font-family: "Outfit", ui-sans-serif, system-ui, sans-serif !important; }

                        /* ── Auth page background ── */
                        .fi-simple-layout {
                            background: #f8fafc !important;
                            background-image:
                                linear-gradient(to right, rgba(0,0,0,.025) 1px, transparent 1px),
                                linear-gradient(to bottom, rgba(0,0,0,.025) 1px, transparent 1px) !important;
                            background-size: 40px 40px !important;
                            min-height: 100vh !important;
                            display: flex !important;
                            align-items: center !important;
                            justify-content: center !important;
                            position: relative !important;
                        }
                        /* subtle brand blobs */
                        .fi-simple-layout::before {
                            content: "";
                            position: fixed;
                            top: -10%;
                            left: -10%;
                            width: 500px;
                            height: 500px;
                            background: rgba(251,191,36,.12);
                            border-radius: 50%;
                            filter: blur(80px);
                            pointer-events: none;
                            z-index: 0;
                        }
                        .fi-simple-layout::after {
                            content: "";
                            position: fixed;
                            bottom: -10%;
                            right: -10%;
                            width: 400px;
                            height: 400px;
                            background: rgba(245,158,11,.1);
                            border-radius: 50%;
                            filter: blur(80px);
                            pointer-events: none;
                            z-index: 0;
                        }

                        /* ── Card / form wrapper ── */
                        .fi-simple-main {
                            background: rgba(255,255,255,.92) !important;
                            backdrop-filter: blur(16px) !important;
                            -webkit-backdrop-filter: blur(16px) !important;
                            border: 1px solid rgba(255,255,255,.7) !important;
                            border-radius: 24px !important;
                            box-shadow: 0 20px 60px -12px rgba(0,0,0,.08) !important;
                            padding: 2.25rem !important;
                            width: 100% !important;
                            max-width: 420px !important;
                            position: relative;
                            z-index: 1;
                        }

                        /* ── Brand heading ── */
                        .fi-simple-header { margin-bottom: 1.75rem !important; text-align: center !important; }
                        .fi-logo { justify-content: center !important; margin-bottom: 1rem !important; }
                        .fi-heading {
                            font-size: 1.5rem !important;
                            font-weight: 900 !important;
                            color: #0f172a !important;
                            letter-spacing: -0.02em !important;
                            text-align: center !important;
                        }
                        .fi-subheading {
                            font-size: 13px !important;
                            color: #64748b !important;
                            font-weight: 500 !important;
                            text-align: center !important;
                            margin-top: 4px !important;
                        }

                        /* ── Form inputs ── */
                        .fi-input-wrp input,
                        .fi-fo-field-wrp input {
                            border-radius: 10px !important;
                            border: 1.5px solid #e2e8f0 !important;
                            background: #f8fafc !important;
                            font-family: "Outfit", sans-serif !important;
                            font-size: 14px !important;
                            font-weight: 500 !important;
                            padding: 9px 12px !important;
                            color: #1e293b !important;
                            transition: border-color .2s, background .2s !important;
                        }
                        .fi-input-wrp input:focus,
                        .fi-fo-field-wrp input:focus {
                            border-color: #f59e0b !important;
                            background: #ffffff !important;
                            box-shadow: 0 0 0 3px rgba(245,158,11,.1) !important;
                            outline: none !important;
                        }

                        /* ── Labels ── */
                        .fi-fo-field-wrp label,
                        .fi-label {
                            font-size: 11px !important;
                            font-weight: 700 !important;
                            text-transform: uppercase !important;
                            letter-spacing: .06em !important;
                            color: #94a3b8 !important;
                            margin-bottom: 6px !important;
                        }

                        /* ── Submit button ── */
                        .fi-btn-primary,
                        .fi-form-component-action-button[type="submit"],
                        button[type="submit"].fi-btn {
                            background: #0f172a !important;
                            border-radius: 12px !important;
                            font-weight: 700 !important;
                            font-size: 14px !important;
                            font-family: "Outfit", sans-serif !important;
                            padding: 11px 24px !important;
                            letter-spacing: 0 !important;
                            transition: all .2s !important;
                            box-shadow: 0 4px 12px rgba(15,23,42,.15) !important;
                            border: none !important;
                        }
                        .fi-btn-primary:hover,
                        button[type="submit"].fi-btn:hover {
                            background: #1e293b !important;
                            transform: translateY(-1px) !important;
                        }

                        /* ── Checkbox "remember me" ── */
                        .fi-checkbox-input:checked { background-color: #f59e0b !important; border-color: #f59e0b !important; }

                        /* ── Back link / footer text ── */
                        .fi-simple-footer { text-align: center; margin-top: 1.25rem; font-size: 12px; color: #94a3b8; font-weight: 500; }

                        /* ── Sidebar Styling (Admin Menu) ── */
                        .fi-sidebar-group-label {
                            font-weight: 800 !important;
                            font-size: 0.7rem !important;
                            letter-spacing: 0.05em !important;
                            text-transform: uppercase !important;
                        }
                        .fi-sidebar-item-button {
                            border-radius: 12px !important;
                            font-weight: 600 !important;
                            transition: all 0.2s ease !important;
                            padding: 0.5rem 0.75rem !important;
                        }
                        .fi-sidebar-item-active .fi-sidebar-item-label {
                            font-weight: 700 !important;
                        }

                        /* Light Mode Colors */
                        html:not(.dark) .fi-sidebar {
                            background-color: #fafafa !important;
                            border-right: 1px solid #f1f5f9 !important;
                        }
                        html:not(.dark) .fi-sidebar-group-label { color: #b45309 !important; }
                        html:not(.dark) .fi-sidebar-item-button:hover { background-color: #fffbeb !important; }
                        html:not(.dark) .fi-sidebar-item-active > .fi-sidebar-item-button {
                            background: linear-gradient(to right, #fffbeb, #fef3c7) !important;
                            color: #92400e !important;
                            box-shadow: inset 2px 0 0 #d97706 !important;
                        }
                        html:not(.dark) .fi-sidebar-item-active .fi-sidebar-item-icon { color: #d97706 !important; }

                        /* Dark Mode Colors */
                        .dark .fi-sidebar {
                            background-color: #0f172a !important; /* slate-900 */
                            border-right: 1px solid #1e293b !important; /* slate-800 */
                        }
                        .dark .fi-sidebar-group-label { color: #fcd34d !important; /* amber-300 */ }
                        .dark .fi-sidebar-item-button:hover { background-color: #1e293b !important; /* slate-800 */ }
                        .dark .fi-sidebar-item-active > .fi-sidebar-item-button {
                            background: linear-gradient(to right, #1e293b, #0f172a) !important;
                            color: #fbbf24 !important; /* amber-400 */
                            box-shadow: inset 2px 0 0 #f59e0b !important;
                        }
                        .dark .fi-sidebar-item-active .fi-sidebar-item-icon { color: #fbbf24 !important; }
                    </style>
                ')
            )
            // ── Google OAuth divider + button ───────────────────────────────
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render('
                    <div style="display:flex;align-items:center;gap:12px;margin:16px 0;">
                        <div style="flex:1;height:1px;background:#e2e8f0;"></div>
                        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;">atau</span>
                        <div style="flex:1;height:1px;background:#e2e8f0;"></div>
                    </div>
                    <a href="' . route('auth.google') . '" style="display:flex;width:100%;align-items:center;justify-content:center;gap:10px;border:1.5px solid #e2e8f0;background:#fff;border-radius:12px;padding:10px 20px;font-size:13px;font-weight:700;color:#374151;text-decoration:none;transition:all .2s;font-family:Outfit,sans-serif;" onmouseover="this.style.borderColor=\'#d1d5db\';this.style.boxShadow=\'0 2px 8px rgba(0,0,0,.06)\';" onmouseout="this.style.borderColor=\'#e2e8f0\';this.style.boxShadow=\'none\';">
                        <svg style="width:18px;height:18px;" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                        </svg>
                        Masuk dengan Google
                    </a>
                ')
            )
            ->navigationGroups([
                NavigationGroup::make('Data Sekolah'),
                NavigationGroup::make('Struktur Kelas'),
                NavigationGroup::make('Diniyyah'),
                NavigationGroup::make('Tahfidz'),
                NavigationGroup::make('Leger & Rapor'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
