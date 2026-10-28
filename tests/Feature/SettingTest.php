<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

test('settings service setMany properly updates integer and string settings through eloquent', function () {
    $service = app(SettingsService::class);

    $service->setMany([
        'max_video_upload_mb' => 200,
        'brand_primary_color' => '#123456',
    ]);

    expect($service->get('max_video_upload_mb'))->toBe(200);
    expect($service->get('brand_primary_color'))->toBe('#123456');

    $rawSetting = Setting::where('key', 'max_video_upload_mb')->first();
    expect($rawSetting->value)->toBe(200);
    expect($rawSetting->typed_value)->toBe(200);
});

test('admin can update settings via API', function () {
    $admin = User::factory()->create([
        'two_factor_secret' => 'SECRET123',
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
        'settings' => [
            'max_video_upload_mb' => 300,
            'brand_primary_color' => '#654321',
        ],
    ]);

    $response->assertOk();
    expect(app(SettingsService::class)->get('max_video_upload_mb'))->toBe(300);
});
