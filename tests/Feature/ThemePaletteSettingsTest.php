<?php

namespace Tests\Feature;

use App\Filament\Pages\AppearanceSettings;
use App\Models\User;
use App\Services\ThemePaletteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThemePaletteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_palette_is_persisted_globally_and_exposes_filament_and_portal_tokens(): void
    {
        $palette = [
            'primary' => '#336699',
            'info' => '#0099cc',
            'success' => '#22aa66',
            'warning' => '#dd9900',
            'danger' => '#dd3344',
        ];

        $this->assertSame(
            array_map('strtoupper', $palette),
            app(ThemePaletteService::class)->save($palette),
        );

        $freshService = new ThemePaletteService();

        $this->assertSame(array_map('strtoupper', $palette), $freshService->palette());
        $this->assertStringContainsString('--color-brand-500:#336699', $freshService->cssVariables());
        $this->assertStringContainsString('--primary-500:#336699', $freshService->cssVariables());
        $this->assertStringContainsString('--success-500:#22AA66', $freshService->cssVariables());
    }

    public function test_only_admin_can_access_appearance_settings(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $this->actingAs($admin, 'admin');
        $this->assertTrue(AppearanceSettings::canAccess());

        $guruRole = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $guru = User::factory()->create();
        $guru->assignRole($guruRole);

        $this->actingAs($guru, 'admin');
        $this->assertFalse(AppearanceSettings::canAccess());
    }

    public function test_invalid_palette_cannot_change_settings_or_existing_user_data(): void
    {
        $user = User::factory()->create();
        $attributes = $user->fresh()->getAttributes();
        $service = new ThemePaletteService();
        $palette = $service->save($service->defaults());

        try {
            $service->save([...$palette, 'primary' => '</style><script>alert(1)</script>']);
            $this->fail('Warna yang tidak valid seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('palette.primary', $exception->errors());
        }

        $this->assertSame($palette, (new ThemePaletteService())->palette());
        $this->assertSame($attributes, $user->fresh()->getAttributes());
    }

    public function test_appearance_migration_preserves_existing_data_and_missing_table_has_a_fallback(): void
    {
        $user = User::factory()->create();
        $attributes = $user->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_10_08_000001_create_site_appearance_settings_table.php');

        $migration->down();
        $service = new ThemePaletteService();
        $this->assertSame(array_map('strtoupper', $service->defaults()), $service->palette());
        $migration->up();

        $this->assertSame($attributes, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('site_appearance_settings', 0);
    }
}
