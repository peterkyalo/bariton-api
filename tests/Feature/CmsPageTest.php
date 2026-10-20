<?php

use App\Models\CmsPage;
use App\Models\ContactInquiry;
use App\Models\User;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->seed(CmsPageSeeder::class);
});

test('public visitor can retrieve published pages and landing page details with sections', function () {
    // 1. List published pages
    $listRes = $this->getJson('/api/v1/pages');
    $listRes->assertStatus(200)
        ->assertJsonPath('status', 'success');

    $slugs = collect($listRes->json('data'))->pluck('slug')->toArray();
    expect($slugs)->toContain('home', 'about', 'contact', 'terms', 'privacy');

    // 2. Fetch 'home' landing page details
    $homeRes = $this->getJson('/api/v1/pages/home');
    $homeRes->assertStatus(200)
        ->assertJsonPath('data.slug', 'home')
        ->assertJsonPath('data.hero_title', 'The Best Online Music Learning Platform');

    $sections = $homeRes->json('data.sections');
    expect($sections)->toHaveKeys(['hero_slides', 'services', 'about_block', 'categories', 'instructors_spotlight', 'testimonials']);
    expect($sections['hero_slides'])->toHaveCount(3);
    expect($sections['services'])->toHaveCount(4);
});

test('public visitor can submit a contact inquiry', function () {
    $payload = [
        'name' => 'Wanjiku Kamau',
        'email' => 'wanjiku.kamau@example.com',
        'phone' => '+254712345678',
        'subject' => 'Conservatory Piano Audition Dates 2026',
        'message' => 'I would like to know the preparation requirements for the 2026 conservatory piano auditions.',
    ];

    $response = $this->postJson('/api/v1/contact', $payload);
    $response->assertStatus(201)
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('contact_inquiries', [
        'email' => 'wanjiku.kamau@example.com',
        'subject' => 'Conservatory Piano Audition Dates 2026',
        'status' => 'new',
    ]);
});

test('admin can manage CMS pages: create, update, list, and delete custom pages', function () {
    $admin = User::factory()->create([
        'two_factor_secret' => 'ADMIN2FA',
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    Sanctum::actingAs($admin);

    // 1. List pages via Admin API
    $listRes = $this->getJson('/api/v1/admin/cms/pages');
    $listRes->assertStatus(200);

    // 2. Create custom page
    $createRes = $this->postJson('/api/v1/admin/cms/pages', [
        'title' => 'Talent Scholarships 2026',
        'slug' => 'scholarships',
        'category' => 'company',
        'hero_badge' => 'Financial Aid & Excellence Grants',
        'hero_title' => 'Bariton Talent Scholarships',
        'hero_subtitle' => 'Tuition grants for exceptional emerging African instrumentalists.',
        'content' => '## Application Guidelines\nWe offer full and partial tuition scholarships.',
        'is_published' => true,
    ]);
    $createRes->assertStatus(201);
    $pageId = $createRes->json('data.id');

    // 3. Update custom page
    $updateRes = $this->putJson("/api/v1/admin/cms/pages/{$pageId}", [
        'title' => 'Bariton Talent Scholarships 2026',
        'hero_badge' => 'Updated Grant Scheme',
    ]);
    $updateRes->assertStatus(200)
        ->assertJsonPath('data.title', 'Bariton Talent Scholarships 2026');

    // 4. Root 'home' page protected from deletion
    $homePage = CmsPage::where('slug', 'home')->firstOrFail();
    $deleteHomeRes = $this->deleteJson("/api/v1/admin/cms/pages/{$homePage->id}");
    $deleteHomeRes->assertStatus(422)
        ->assertJsonPath('status', 'error');

    // 5. Delete custom page
    $deleteCustomRes = $this->deleteJson("/api/v1/admin/cms/pages/{$pageId}");
    $deleteCustomRes->assertStatus(200);
    expect(CmsPage::find($pageId))->toBeNull();
});

test('admin can view and update status of contact inquiries', function () {
    $inquiry = ContactInquiry::create([
        'name' => 'John Ouma',
        'email' => 'john.ouma@example.com',
        'phone' => '+254700000000',
        'subject' => 'Voice Lessons Inquiry',
        'message' => 'Are online lessons available on Saturday afternoons?',
        'status' => 'new',
    ]);

    $admin = User::factory()->create([
        'two_factor_secret' => 'ADMIN2FA',
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    Sanctum::actingAs($admin);

    // List inquiries
    $listRes = $this->getJson('/api/v1/admin/cms/contact-inquiries');
    $listRes->assertStatus(200);
    expect($listRes->json('data.data'))->toHaveCount(1);

    // Update status to resolved
    $updateRes = $this->putJson("/api/v1/admin/cms/contact-inquiries/{$inquiry->uuid}", [
        'status' => 'resolved',
        'admin_notes' => 'Called client on WhatsApp and scheduled trial lesson.',
    ]);
    $updateRes->assertStatus(200)
        ->assertJsonPath('data.status', 'resolved');

    $inquiry->refresh();
    expect($inquiry->status)->toBe('resolved');
    expect($inquiry->responded_at)->not->toBeNull();
});

test('unauthorized users cannot access admin CMS endpoints', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    Sanctum::actingAs($student);

    $this->getJson('/api/v1/admin/cms/pages')->assertStatus(403);
    $this->postJson('/api/v1/admin/cms/pages', ['title' => 'Test'])->assertStatus(403);
    $this->getJson('/api/v1/admin/cms/contact-inquiries')->assertStatus(403);
});
