<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deactivation_is_soft_deleted_and_audited_and_can_be_restored(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $target = User::factory()->create(['name' => 'Akun Guru']);
        $this->actingAs($admin);

        $target->delete();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertNull(User::find($target->id));
        $this->assertDatabaseHas('activity_log', [
            'description' => 'user_account_deactivated',
            'subject_id' => $target->id,
        ]);

        $target->restore();

        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'user_account_reactivated',
            'subject_id' => $target->id,
        ]);
    }
}
