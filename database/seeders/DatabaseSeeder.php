<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            UsersSeeder::class,
            CoursesSeeder::class,
            EventSeeder::class,
            TutoringPackageSeeder::class,
        ]);
    }
}
