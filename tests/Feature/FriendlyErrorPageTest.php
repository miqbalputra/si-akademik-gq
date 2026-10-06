<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FriendlyErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_response_uses_a_friendly_page_without_debug_details(): void
    {
        $this->get('/alamat-yang-tidak-tersedia')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertDontSee('vendor/laravel');
    }

    public function test_server_error_response_uses_a_friendly_page_without_exception_details(): void
    {
        config(['app.debug' => false]);

        $this->app['router']->get('/test-server-error', function () {
            throw new RuntimeException('secret internal exception detail');
        });

        $this->get('/test-server-error')
            ->assertInternalServerError()
            ->assertSee('Terjadi gangguan sementara')
            ->assertDontSee('secret internal exception detail');
    }
}
