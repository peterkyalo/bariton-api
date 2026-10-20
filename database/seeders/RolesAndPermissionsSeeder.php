<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.assign-roles',
            'settings.view',
            'settings.update',
            'activity-log.view',
            'notifications.view',
            'dashboard.admin',
            'dashboard.instructor',
            'dashboard.student',
            'dashboard.finance',
            'dashboard.coordinator',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);

        $instructor = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        $instructor->syncPermissions(['dashboard.instructor']);

        $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student->syncPermissions(['dashboard.student']);

        $financeManager = Role::firstOrCreate(['name' => 'finance_manager', 'guard_name' => 'web']);
        $financeManager->syncPermissions(['dashboard.finance', 'settings.view']);

        $financeStaff = Role::firstOrCreate(['name' => 'finance_staff', 'guard_name' => 'web']);
        $financeStaff->syncPermissions(['dashboard.finance']);

        $coordinator = Role::firstOrCreate(['name' => 'workshop_coordinator', 'guard_name' => 'web']);
        $coordinator->syncPermissions(['dashboard.coordinator']);
    }
}
