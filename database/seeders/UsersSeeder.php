<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('Password123!');

        // 1. Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@bmas.com'],
            [
                'name' => 'Bariton Admin',
                'phone' => '+254711000001',
                'date_of_birth' => '1990-05-15',
                'status' => 'active',
                'email_verified_at' => now(),
                'privacy_consent_at' => now(),
                'privacy_policy_version' => '1.0',
                'password' => $defaultPassword,
            ]
        );
        $admin->syncRoles(['admin']);

        // 2. Instructor
        $instructor = User::firstOrCreate(
            ['email' => 'instructor@bmas.com'],
            [
                'name' => 'Wanjiku Kamau (Instructor)',
                'phone' => '+254711000002',
                'date_of_birth' => '1992-08-20',
                'status' => 'active',
                'email_verified_at' => now(),
                'privacy_consent_at' => now(),
                'privacy_policy_version' => '1.0',
                'password' => $defaultPassword,
            ]
        );
        $instructor->syncRoles(['instructor']);

        // 3. Student (Adult)
        $student = User::firstOrCreate(
            ['email' => 'student@bmas.com'],
            [
                'name' => 'Brian Ochieng (Student)',
                'phone' => '+254711000003',
                'date_of_birth' => '2001-03-12',
                'status' => 'active',
                'email_verified_at' => now(),
                'privacy_consent_at' => now(),
                'privacy_policy_version' => '1.0',
                'password' => $defaultPassword,
            ]
        );
        $student->syncRoles(['student']);

        // 4. Finance Manager
        $financeManager = User::firstOrCreate(
            ['email' => 'financemanager@bmas.com'],
            [
                'name' => 'Grace Muthoni (Finance Manager)',
                'phone' => '+254711000004',
                'date_of_birth' => '1988-11-04',
                'status' => 'active',
                'email_verified_at' => now(),
                'privacy_consent_at' => now(),
                'privacy_policy_version' => '1.0',
                'password' => $defaultPassword,
            ]
        );
        $financeManager->syncRoles(['finance_manager']);

        // 5. Finance Staff
        $financeStaff = User::firstOrCreate(
            ['email' => 'financestaff@bmas.com'],
            [
                'name' => 'David Kiprono (Finance Staff)',
                'phone' => '+254711000005',
                'date_of_birth' => '1996-02-18',
                'status' => 'active',
                'email_verified_at' => now(),
                'privacy_consent_at' => now(),
                'privacy_policy_version' => '1.0',
                'password' => $defaultPassword,
            ]
        );
        $financeStaff->syncRoles(['finance_staff']);

        // 6. Workshop Coordinator
        $coordinator = User::firstOrCreate(
            ['email' => 'coordinator@bmas.com'],
            [
                'name' => 'Amina Hassan (Workshop Coordinator)',
                'phone' => '+254711000006',
                'date_of_birth' => '1994-07-25',
                'status' => 'active',
                'email_verified_at' => now(),
                'privacy_consent_at' => now(),
                'privacy_policy_version' => '1.0',
                'password' => $defaultPassword,
            ]
        );
        $coordinator->syncRoles(['workshop_coordinator']);
    }
}
