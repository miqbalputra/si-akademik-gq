<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_cannot_open_teacher_routes_and_teacher_cannot_open_guardian_routes(): void
    {
        $guardian = $this->userWithRole('wali_santri');
        $teacher = $this->userWithRole('guru');

        $this->actingAs($guardian)->get(route('guru.dashboard'))->assertForbidden();
        $this->actingAs($teacher)->get(route('wali.dashboard'))->assertForbidden();
    }

    public function test_teacher_assignment_is_required_even_after_teacher_role_check(): void
    {
        $teacherWithoutAssignment = $this->userWithRole('guru');

        $this->actingAs($teacherWithoutAssignment)
            ->get(route('guru.diniyyah-journals.index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $role = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
