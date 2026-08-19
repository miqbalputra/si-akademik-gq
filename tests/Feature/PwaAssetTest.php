<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetTest extends TestCase
{
    public function test_manifest_uses_gq_edu_and_declares_real_icon_assets(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('GQ Edu', $manifest['name']);
        $this->assertSame('GQ Edu', $manifest['short_name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);

        foreach ($manifest['icons'] as $icon) {
            $path = parse_url($icon['src'], PHP_URL_PATH);
            $this->assertNotFalse($path);
            $this->assertFileExists(public_path(ltrim($path, '/')));
        }
    }

    public function test_pwa_partial_contains_install_prompt_metadata(): void
    {
        $html = view('partials.pwa-head')->render();
        $prompt = view('partials.pwa-install-prompt')->render();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('GQ Edu', $html);
        $this->assertStringContainsString('data-pwa-install-root', $prompt);
        $this->assertStringContainsString('data-pwa-install-action', $prompt);
    }

    public function test_service_worker_keeps_authenticated_navigation_out_of_cache(): void
    {
        $serviceWorker = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("'/admin'", $serviceWorker);
        $this->assertStringContainsString("'/api'", $serviceWorker);
        $this->assertStringContainsString("'/livewire'", $serviceWorker);
        $this->assertStringContainsString('PUBLIC_NAVIGATIONS', $serviceWorker);
        $this->assertStringContainsString("caches.match('/offline.html')", $serviceWorker);
    }
}
