<?php

use App\Models\Attachment;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('public');
    Storage::fake(config('filesystems.private_disk', 'local'));

    $this->instructor = User::factory()->create();
    $this->instructor->assignRole('instructor');

    $this->course = Course::create([
        'title' => 'Rich Media Course',
        'slug' => 'rich-media-course',
        'status' => 'published',
    ]);
    $this->course->instructors()->attach($this->instructor->id);

    $this->module = $this->course->modules()->create([
        'title' => 'Module One',
        'level' => 'beginner',
        'order' => 1,
        'price_kes' => 100000,
    ]);
});

test('rich text in course and module descriptions is sanitized but formatting is kept', function () {
    $admin = User::factory()->create([
        'two_factor_secret' => 'TESTSECRETKEY123',
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    $dirty = '<h2>Overview</h2><p onclick="steal()">Hello <strong>class</strong></p><script>alert(1)</script>'
        .'<a href="javascript:alert(1)">bad</a><img src="/storage/editor-images/a.png" onerror="x()">';

    $this->actingAs($admin)
        ->putJson("/api/v1/admin/courses/{$this->course->id}", ['description' => $dirty])
        ->assertOk();

    $description = $this->course->fresh()->description;
    expect($description)->toContain('<h2>Overview</h2>')
        ->toContain('<strong>class</strong>')
        ->toContain('src="/storage/editor-images/a.png"')
        ->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('javascript:');

    $res = $this->actingAs($this->instructor)
        ->postJson("/api/v1/instructor/courses/{$this->course->id}/modules", [
            'title' => 'Module Two',
            'level' => 'intermediate',
            'price_kes' => 200000,
            'description' => '<p>Intro</p><iframe src="https://evil.test"></iframe>',
        ])
        ->assertCreated();

    expect($res->json('module.description'))->toBe('<p>Intro</p>');

    // Markdown / plain text without tags is untouched.
    $this->actingAs($this->instructor)
        ->putJson("/api/v1/instructor/modules/{$this->module->id}", ['description' => "## Heading\n\n- one\n- two"])
        ->assertOk()
        ->assertJsonPath('module.description', "## Heading\n\n- one\n- two");
});

test('instructors can upload images for the rich text editor and invalid files are rejected', function () {
    $res = $this->actingAs($this->instructor)->post('/api/v1/instructor/uploads/images', [
        'image' => UploadedFile::fake()->image('diagram.png', 600, 400),
    ], ['Accept' => 'application/json']);

    $res->assertCreated()->assertJsonStructure(['url', 'path']);
    Storage::disk('public')->assertExists($res->json('path'));

    $this->actingAs($this->instructor)->post('/api/v1/instructor/uploads/images', [
        'image' => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['image']);

    $student = User::factory()->create();
    $student->assignRole('student');
    $this->actingAs($student)->post('/api/v1/instructor/uploads/images', [
        'image' => UploadedFile::fake()->image('x.png'),
    ], ['Accept' => 'application/json'])->assertForbidden();
});

test('course and module cover images can be set replaced and removed', function () {
    $this->actingAs($this->instructor)->post("/api/v1/instructor/courses/{$this->course->id}/cover", [
        'image' => UploadedFile::fake()->image('cover.jpg', 800, 450),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['thumbnail_url']);

    $first = $this->course->fresh()->thumbnail_path;
    Storage::disk('public')->assertExists($first);

    $this->actingAs($this->instructor)->post("/api/v1/instructor/courses/{$this->course->id}/cover", [
        'image' => UploadedFile::fake()->image('cover2.png'),
    ], ['Accept' => 'application/json'])->assertOk();

    Storage::disk('public')->assertMissing($first);

    $this->actingAs($this->instructor)->postJson("/api/v1/instructor/modules/{$this->module->id}/cover", [], [])
        ->assertUnprocessable();

    $this->actingAs($this->instructor)->post("/api/v1/instructor/modules/{$this->module->id}/cover", [
        'image' => UploadedFile::fake()->image('module.webp'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['image_url']);

    $modulePath = $this->module->fresh()->image_path;
    Storage::disk('public')->assertExists($modulePath);

    $this->actingAs($this->instructor)->deleteJson("/api/v1/instructor/modules/{$this->module->id}/cover")->assertOk();
    Storage::disk('public')->assertMissing($modulePath);
    expect($this->module->fresh()->image_url)->toBeNull();

    $this->actingAs($this->instructor)->getJson("/api/v1/instructor/courses/{$this->course->id}")
        ->assertOk()
        ->assertJsonPath('course.thumbnail_url', $this->course->fresh()->thumbnail_url);
});

test('courses and modules accept youtube pdf audio and image attachments', function () {
    foreach (['course' => $this->course, 'module' => $this->module] as $type => $parent) {
        $base = $type === 'course'
            ? "/api/v1/instructor/courses/{$parent->id}/attachments"
            : "/api/v1/instructor/modules/{$parent->id}/attachments";

        $this->actingAs($this->instructor)->postJson($base, [
            'kind' => 'youtube',
            'title' => 'Welcome video',
            'youtube_video_id' => 'https://youtu.be/Mh4f9AYRCZY?si=abc',
        ])->assertCreated()->assertJsonPath('attachment.youtube_video_id', 'Mh4f9AYRCZY');

        $this->actingAs($this->instructor)->post($base, [
            'kind' => 'pdf',
            'title' => 'Syllabus',
            'file' => UploadedFile::fake()->create('syllabus.pdf', 300, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('attachment.kind', 'pdf')
            ->assertJsonPath('attachment.file_name', 'syllabus.pdf');

        $this->actingAs($this->instructor)->post($base, [
            'kind' => 'audio',
            'file' => UploadedFile::fake()->create('sample.mp3', 500, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('attachment.kind', 'audio');

        $this->actingAs($this->instructor)->post($base, [
            'kind' => 'image',
            'file' => UploadedFile::fake()->image('score.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('attachment.kind', 'image');

        expect($parent->attachments()->count())->toBe(4);
    }

    $show = $this->actingAs($this->instructor)->getJson("/api/v1/instructor/courses/{$this->course->id}")->assertOk();
    $show->assertJsonCount(4, 'course.attachments')->assertJsonCount(4, 'course.modules.0.attachments');

    $pdfUrl = collect($show->json('course.attachments'))->firstWhere('kind', 'pdf')['url'];
    expect($pdfUrl)->toContain('/media/attachments/');
});

test('attachment validation rejects wrong file types and malformed youtube ids', function () {
    $url = "/api/v1/instructor/courses/{$this->course->id}/attachments";

    $this->actingAs($this->instructor)->post($url, [
        'kind' => 'pdf',
        'file' => UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['file']);

    $this->actingAs($this->instructor)->post($url, [
        'kind' => 'audio',
        'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['file']);

    $this->actingAs($this->instructor)->postJson($url, ['kind' => 'youtube', 'youtube_video_id' => 'nope'])
        ->assertUnprocessable()->assertJsonValidationErrors(['youtube_video_id']);

    $this->actingAs($this->instructor)->postJson($url, ['kind' => 'video'])
        ->assertUnprocessable()->assertJsonValidationErrors(['kind']);
});

test('only assigned instructors can manage attachments and deleting removes the stored file', function () {
    $other = User::factory()->create();
    $other->assignRole('instructor');

    $this->actingAs($other)->postJson("/api/v1/instructor/courses/{$this->course->id}/attachments", [
        'kind' => 'youtube',
        'youtube_video_id' => 'Mh4f9AYRCZY',
    ])->assertForbidden();

    $this->actingAs($this->instructor)->post("/api/v1/instructor/modules/{$this->module->id}/attachments", [
        'kind' => 'pdf',
        'file' => UploadedFile::fake()->create('score.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $attachment = Attachment::first();
    $disk = Storage::disk($attachment->file_disk);
    $disk->assertExists($attachment->file_path);

    $this->actingAs($other)->deleteJson("/api/v1/instructor/attachments/{$attachment->id}")->assertForbidden();

    $this->actingAs($this->instructor)->deleteJson("/api/v1/instructor/attachments/{$attachment->id}")->assertOk();
    $disk->assertMissing($attachment->file_path);
    expect(Attachment::count())->toBe(0);
});

test('public catalog only exposes promo media and hides paid lesson content', function () {
    $this->course->attachments()->create(['kind' => 'youtube', 'youtube_video_id' => 'Mh4f9AYRCZY', 'order' => 1]);
    $this->course->attachments()->create([
        'kind' => 'pdf', 'file_path' => 'course-materials/secret.pdf', 'file_disk' => 'local', 'file_name' => 'secret.pdf', 'order' => 2,
    ]);
    $this->module->attachments()->create([
        'kind' => 'audio', 'file_path' => 'course-materials/secret.mp3', 'file_disk' => 'local', 'file_name' => 'secret.mp3', 'order' => 1,
    ]);

    $this->module->lessons()->create(['title' => 'Paid text', 'type' => 'richtext', 'content' => '<p>paid body</p>', 'order' => 1]);
    $this->module->lessons()->create(['title' => 'Free text', 'type' => 'richtext', 'content' => '<p>free body</p>', 'order' => 2, 'is_preview' => true]);

    $res = $this->getJson('/api/v1/courses/rich-media-course')->assertOk();

    $res->assertJsonCount(1, 'course.attachments')
        ->assertJsonPath('course.attachments.0.kind', 'youtube')
        ->assertJsonCount(0, 'course.modules.0.attachments')
        ->assertJsonPath('course.modules.0.lessons.0.content', null)
        ->assertJsonPath('course.modules.0.lessons.1.content', '<p>free body</p>');
});
