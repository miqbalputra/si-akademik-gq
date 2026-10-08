<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Tests\TestCase;

class WebTypographyTest extends TestCase
{
    public function test_public_and_authentication_pages_use_local_outfit(): void
    {
        foreach (['/', '/login', '/forgot-password'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('fonts/outfit/outfit.css', false)
                ->assertSee('fonts/outfit/outfit-latin-variable.woff2', false)
                ->assertDontSee('fonts.googleapis.com', false)
                ->assertDontSee('fonts.bunny.net', false);
        }
    }

    public function test_filament_uses_the_same_local_font_provider(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('Outfit', $panel->getFontFamily());
        $this->assertSame(LocalFontProvider::class, $panel->getFontProvider());
        $this->assertSame(asset('fonts/outfit/outfit.css'), $panel->getFontUrl());
        $this->assertStringContainsString('fonts/outfit/outfit.css', $panel->getFontHtml()->toHtml());
    }

    public function test_variable_font_assets_and_license_are_available(): void
    {
        $css = file_get_contents(public_path('fonts/outfit/outfit.css'));
        $this->assertStringContainsString('font-weight: 100 900', $css);
        $this->assertStringNotContainsString('https://', $css);

        foreach (['outfit-latin-variable.woff2', 'outfit-latin-ext-variable.woff2'] as $font) {
            $this->assertSame('wOF2', substr(file_get_contents(public_path('fonts/outfit/'.$font)), 0, 4));
        }

        $this->assertStringContainsString('SIL OPEN FONT LICENSE', file_get_contents(public_path('fonts/outfit/OFL.txt')));
        $this->assertStringContainsString('/fonts/outfit/outfit.css', file_get_contents(public_path('offline.html')));
        $serviceWorker = file_get_contents(public_path('sw.js'));
        foreach (['outfit.css', 'outfit-latin-variable.woff2', 'outfit-latin-ext-variable.woff2'] as $asset) {
            $this->assertStringContainsString('/fonts/outfit/'.$asset, $serviceWorker);
        }
    }

    public function test_both_themes_share_tailadmin_typography_tokens(): void
    {
        foreach (['app.css', 'filament/admin/theme.css'] as $theme) {
            $this->assertStringContainsString('ui-typography.css', file_get_contents(resource_path('css/'.$theme)));
        }

        $tokens = file_get_contents(resource_path('css/ui-typography.css'));
        foreach (['--text-title-sm: 30px', '--text-title-sm--line-height: 38px', '--text-title-md: 36px', '--text-title-md--line-height: 44px', '--text-title-xl: 60px', '--text-title-xl--line-height: 72px', '--text-theme-sm: 14px', '--text-theme-sm--line-height: 20px', '--text-theme-xs: 12px', '--text-theme-xs--line-height: 18px'] as $token) {
            $this->assertStringContainsString($token, $tokens);
        }
    }
}
