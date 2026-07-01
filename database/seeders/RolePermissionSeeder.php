<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage_users',
            'manage_school_profile',
            'manage_academic_years',
            'manage_students',
            'manage_guardians',
            'manage_teachers',
            'manage_classrooms',
            'manage_class_enrollments',
            'manage_diniyyah_master',
            'input_diniyyah_scores',
            'update_own_diniyyah_scores',
            'validate_diniyyah_scores',
            'view_diniyyah_ledger',
            'manage_report_cards',
            'publish_report_cards',
            'view_school_reports',
            'view_own_children',
        ];

        $permissionModels = collect($permissions)->mapWithKeys(fn (string $permission) => [
            $permission => Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]),
        ]);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissionModels->values());

        Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'])->syncPermissions([
            $permissionModels['view_school_reports'],
            $permissionModels['view_diniyyah_ledger'],
        ]);

        Role::firstOrCreate(['name' => 'kabag_diniyyah', 'guard_name' => 'web'])->syncPermissions([
            $permissionModels['manage_diniyyah_master'],
            $permissionModels['validate_diniyyah_scores'],
            $permissionModels['view_diniyyah_ledger'],
            $permissionModels['manage_report_cards'],
        ]);

        Role::firstOrCreate(['name' => 'kabag_tahfidz', 'guard_name' => 'web'])->syncPermissions([]);

        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'])->syncPermissions([
            $permissionModels['input_diniyyah_scores'],
            $permissionModels['update_own_diniyyah_scores'],
        ]);

        Role::firstOrCreate(['name' => 'wali_santri', 'guard_name' => 'web'])->syncPermissions([
            $permissionModels['view_own_children'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
