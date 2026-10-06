<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_http_requests_redirect_to_the_canonical_https_host(): void
    {
        config([
            'app.env' => 'production',
            'app.url' => 'https://edu.griyaquran.web.id',
            'security.force_https' => true,
        ]);

        $this->get('http://localhost/login?next=1')
            ->assertRedirect('https://edu.griyaquran.web.id/login?next=1')
            ->assertStatus(301);
    }

    public function test_security_headers_and_hsts_are_sent_on_production_https_responses(): void
    {
        config([
            'app.env' => 'production',
            'app.url' => 'https://localhost',
            'security.force_https' => true,
        ]);

        $response = $this->get('https://localhost/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('X-Powered-By'));
    }
}
