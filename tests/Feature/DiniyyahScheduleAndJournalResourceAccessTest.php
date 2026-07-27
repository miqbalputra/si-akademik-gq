<?php

namespace Tests\Feature;

use App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource;
use App\Filament\Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Gate peran pada resource Filament Jadwal Mengajar & Jurnal KBM Diniyyah.
 * Keduanya sebelumnya tanpa HasRoleBasedResourceAccess, sehingga kepala_sekolah
 * (seharusnya read-only) bisa Create/Edit/Delete. Setelah perbaikan harus konsisten
 * dengan sibling (DiniyyahTeacherAssignmentResource / DiniyyahClassSubjectResource).
 */
class DiniyyahScheduleAndJournalResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[DataProvider('resourceProvider')]
    public function test_view_and_manage_roles_match_diniyyah_siblings(string $resourceClass): void
    {
        // admin & kabag_diniyyah: view + manage
        $this->actingAs($this->userWithRole('admin'));
        $this->assertTrue($resourceClass::canAccess());
        $this->assertTrue($resourceClass::canCreate());

        $this->actingAs($this->userWithRole('kepala_sekolah'));
        $this->assertTrue($resourceClass::canAccess(), 'kepala_sekolah harus bisa lihat');
        $this->assertFalse($resourceClass::canCreate(), 'kepala_sekolah harus read-only');

        $this->actingAs($this->userWithRole('guru'));
        $this->assertFalse($resourceClass::canAccess(), 'guru tidak boleh akses panel resource');

        $this->actingAs($this->userWithRole('wali_santri'));
        $this->assertFalse($resourceClass::canAccess(), 'wali tidak boleh akses panel resource');
    }

    public static function resourceProvider(): array
    {
        return [
            'Jadwal Mengajar' => [DiniyyahTeachingScheduleResource::class],
            'Jurnal KBM' => [DiniyyahClassJournalResource::class],
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}