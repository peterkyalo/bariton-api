<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\MediaStreamService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

test('a student sees a course with three levels and prices in both currencies', function () {
    $course = Course::create([
        'title' => 'Concert Violin Fundamentals',
        'slug' => 'concert-violin-fundamentals',
        'description' => 'Comprehensive violin program.',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 10,
    ]);

    $module1 = $course->modules()->create([
        'title' => 'Beginner Violin Foundations',
        'level' => 'beginner',
        'order' => 1,
        'price_kes' => 300000, // KES 3,000.00 -> at 130 rate = $23.08 (2308 cents)
    ]);

    $module2 = $course->modules()->create([
        'title' => 'Intermediate Bowing Articulations',
        'level' => 'intermediate',
        'order' => 2,
        'price_kes' => 500000, // KES 5,000.00 -> at 130 rate = $38.47 (3847 cents)
    ]);

    $module3 = $course->modules()->create([
        'title' => 'Advanced Paganini Caprices',
        'level' => 'advanced',
        'order' => 3,
        'price_kes' => 800000, // KES 8,000.00 -> at 130 rate = $61.54 (6154 cents)
    ]);

    $response = $this->getJson('/api/v1/courses/concert-violin-fundamentals');

    $response->assertOk()
        ->assertJsonPath('course.title', 'Concert Violin Fundamentals')
        ->assertJsonPath('course.modules_count', 3)
        ->assertJsonCount(3, 'course.modules')
        ->assertJsonPath('course.modules.0.level', 'beginner')
        ->assertJsonPath('course.modules.0.price_kes', 300000)
        ->assertJsonPath('course.modules.0.price_formatted', '3,000.00')
        ->assertJsonPath('course.modules.0.price_usd_cents', 2308)
        ->assertJsonPath('course.modules.0.price_usd_formatted', '23.08')
        ->assertJsonPath('course.modules.1.level', 'intermediate')
        ->assertJsonPath('course.modules.1.price_kes', 500000)
        ->assertJsonPath('course.modules.2.level', 'advanced')
        ->assertJsonPath('course.modules.2.price_kes', 800000);
});

test('the bundle price shows below the module total with savings calculation', function () {
    $course = Course::create([
        'title' => 'Music Theory & Composition',
        'slug' => 'music-theory-composition',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 20, // 20% bundle discount
    ]);

    $course->modules()->create([
        'title' => 'Harmony Level 1',
        'level' => 'beginner',
        'order' => 1,
        'price_kes' => 400000,
    ]);

    $course->modules()->create([
        'title' => 'Harmony Level 2',
        'level' => 'intermediate',
        'order' => 2,
        'price_kes' => 600000,
    ]);

    // Total modules: 4,000 + 6,000 = 10,000 KES (1,000,000 minor units)
    // 20% discount = 2,000 KES (200,000 minor units)
    // Bundle price = 8,000 KES (800,000 minor units)
    // USD rate 130:
    // Total modules USD: ceil(1000000 / 130) = 7693 cents ($76.93)
    // Bundle USD: ceil(800000 / 130) = 6154 cents ($61.54)
    // Savings USD: ceil(200000 / 130) = 1539 cents ($15.39)

    $response = $this->getJson('/api/v1/courses/music-theory-composition');

    $response->assertOk()
        ->assertJsonPath('course.pricing.total_modules_kes', 1000000)
        ->assertJsonPath('course.pricing.total_modules_formatted', '10,000.00')
        ->assertJsonPath('course.pricing.total_modules_usd_cents', 7693)
        ->assertJsonPath('course.pricing.bundle_price_kes', 800000)
        ->assertJsonPath('course.pricing.bundle_price_formatted', '8,000.00')
        ->assertJsonPath('course.pricing.bundle_price_usd_cents', 6154)
        ->assertJsonPath('course.pricing.bundle_savings_kes', 200000)
        ->assertJsonPath('course.pricing.bundle_savings_formatted', '2,000.00')
        ->assertJsonPath('course.pricing.bundle_savings_usd_cents', 1539);
});

test('it rejects any discount that would make the bundle price zero or higher than the sum of single modules', function () {
    $admin = User::factory()->create([
        'two_factor_secret' => 'TESTSECRETKEY123',
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    $course = Course::create([
        'title' => 'Flute Essentials',
        'slug' => 'flute-essentials',
        'status' => 'published',
        'bundle_discount_type' => 'none',
        'bundle_discount_value' => 0,
    ]);

    $course->modules()->create([
        'title' => 'Flute Breathing',
        'level' => 'beginner',
        'order' => 1,
        'price_kes' => 200000,
    ]);

    // 1. Attempting 100% discount (which makes bundle price zero) -> must fail validation
    $responseZeroPercent = $this->actingAs($admin)
        ->putJson("/api/v1/admin/courses/{$course->id}", [
            'bundle_discount_type' => 'percent',
            'bundle_discount_value' => 100,
        ]);

    $responseZeroPercent->assertStatus(422)
        ->assertJsonValidationErrors(['bundle_discount_value']);

    // 2. Attempting fixed discount equal to total price (200000 KES, making bundle zero) -> must fail
    $responseZeroFixed = $this->actingAs($admin)
        ->putJson("/api/v1/admin/courses/{$course->id}", [
            'bundle_discount_type' => 'fixed_kes',
            'bundle_discount_value' => 200000,
        ]);

    $responseZeroFixed->assertStatus(422)
        ->assertJsonValidationErrors(['bundle_discount_value']);

    // 3. Attempting fixed discount exceeding total price (300000 KES, making bundle negative) -> must fail
    $responseNegative = $this->actingAs($admin)
        ->putJson("/api/v1/admin/courses/{$course->id}", [
            'bundle_discount_type' => 'fixed_kes',
            'bundle_discount_value' => 300000,
        ]);

    $responseNegative->assertStatus(422)
        ->assertJsonValidationErrors(['bundle_discount_value']);
});

test('a student who owns one module sees the correct upgrade price', function () {
    $student = User::factory()->create(['email' => 'upgrade.student@bmas.com']);
    $student->assignRole('student');

    $course = Course::create([
        'title' => 'Orchestral Conducting',
        'slug' => 'orchestral-conducting',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 15,
    ]);

    $module1 = $course->modules()->create([
        'title' => 'Baton Technique',
        'level' => 'beginner',
        'order' => 1,
        'price_kes' => 500000, // 5,000 KES
    ]);

    $module2 = $course->modules()->create([
        'title' => 'Score Reading',
        'level' => 'intermediate',
        'order' => 2,
        'price_kes' => 700000, // 7,000 KES
    ]);

    // Total modules = 12,000 KES (1,200,000 minor units)
    // 15% discount = 1,800 KES (180,000 minor units)
    // Bundle price = 10,200 KES (1,020,000 minor units)

    // Student owns module 1 (paid 5,000 KES / 500,000 minor units)
    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'path_type' => 'standard',
        'status' => 'active',
    ]);

    EnrollmentModule::create([
        'enrollment_id' => $enrollment->id,
        'module_id' => $module1->id,
        'amount_paid_kes' => 500000,
    ]);

    // Upgrade price = 10,200 - 5,000 = 5,200 KES (520,000 minor units)
    // USD rate 130: ceil(520000 / 130) = 4000 cents ($40.00)

    $response = $this->actingAs($student)->getJson('/api/v1/courses/orchestral-conducting');

    $response->assertOk()
        ->assertJsonPath('course.upgrade_pricing.has_owned_modules', true)
        ->assertJsonPath('course.upgrade_pricing.total_paid_kes', 500000)
        ->assertJsonPath('course.upgrade_pricing.upgrade_price_kes', 520000)
        ->assertJsonPath('course.upgrade_pricing.upgrade_price_formatted', '5,200.00')
        ->assertJsonPath('course.upgrade_pricing.upgrade_price_usd_cents', 4000)
        ->assertJsonPath('course.upgrade_pricing.upgrade_price_usd_formatted', '40.00');
});

test('an instructor adds a YouTube, audio and PDF lesson and uploads are validated', function () {
    Storage::fake(config('filesystems.private_disk', 'local'));

    $instructor = User::factory()->create();
    $instructor->assignRole('instructor');

    $course = Course::create([
        'title' => 'Acoustic Guitar Mastery',
        'slug' => 'acoustic-guitar-mastery',
        'status' => 'draft',
    ]);

    $course->instructors()->attach($instructor->id, ['is_primary' => true]);

    $module = $course->modules()->create([
        'title' => 'Fingerpicking Techniques',
        'level' => 'beginner',
        'order' => 1,
        'price_kes' => 350000,
    ]);

    // 1. Add YouTube Lesson
    $ytResponse = $this->actingAs($instructor)->postJson("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Travis Picking Pattern 1',
        'type' => 'youtube',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'duration_seconds' => 450,
        'is_preview' => true,
    ]);

    $ytResponse->assertCreated()
        ->assertJsonPath('lesson.title', 'Travis Picking Pattern 1')
        ->assertJsonPath('lesson.type', 'youtube')
        ->assertJsonPath('lesson.youtube_video_id', 'dQw4w9WgXcQ')
        ->assertJsonPath('lesson.is_preview', true);

    // 2. Add Audio Lesson with real audio file
    $fakeAudio = UploadedFile::fake()->create('picking_exercise.mp3', 2048, 'audio/mpeg');

    $audioResponse = $this->actingAs($instructor)->postJson("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Slow Tempo Audio Playalong',
        'type' => 'audio',
        'file' => $fakeAudio,
        'duration_seconds' => 180,
    ]);

    $audioResponse->assertCreated()
        ->assertJsonPath('lesson.title', 'Slow Tempo Audio Playalong')
        ->assertJsonPath('lesson.type', 'audio')
        ->assertJsonPath('lesson.mime_type', 'audio/mpeg')
        ->assertJsonPath('lesson.file_name', 'picking_exercise.mp3');

    // 3. Add PDF Lesson with valid PDF
    $fakePdf = UploadedFile::fake()->create('sheet_tab_notation.pdf', 512, 'application/pdf');

    $pdfResponse = $this->actingAs($instructor)->postJson("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Fingerstyle Tabs & Standard Notation',
        'type' => 'pdf',
        'file' => $fakePdf,
    ]);

    $pdfResponse->assertCreated()
        ->assertJsonPath('lesson.title', 'Fingerstyle Tabs & Standard Notation')
        ->assertJsonPath('lesson.type', 'pdf')
        ->assertJsonPath('lesson.mime_type', 'application/pdf');

    // 4. Invalid MIME check: attempt to upload text file as PDF
    $invalidFile = UploadedFile::fake()->create('notes.txt', 100, 'text/plain');

    $invalidResponse = $this->actingAs($instructor)->postJson("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Invalid Text Lesson',
        'type' => 'pdf',
        'file' => $invalidFile,
    ]);

    $invalidResponse->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('progress saves when YouTube is 90% watched, Audio is 90% played, and PDF is marked complete', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $course = Course::create([
        'title' => 'Cello Sonatas Study',
        'slug' => 'cello-sonatas-study',
        'status' => 'published',
    ]);

    $module = $course->modules()->create([
        'title' => 'Prelude in G Major',
        'level' => 'intermediate',
        'order' => 1,
        'price_kes' => 400000,
    ]);

    $lessonYt = $module->lessons()->create([
        'title' => 'Bowing the Prelude',
        'type' => 'youtube',
        'youtube_video_id' => '1prweT95OD0',
        'duration_seconds' => 600,
    ]);

    $lessonAudio = $module->lessons()->create([
        'title' => 'Piano Accompaniment Track',
        'type' => 'audio',
        'file_path' => 'lessons/sample.mp3',
        'duration_seconds' => 300,
    ]);

    $lessonPdf = $module->lessons()->create([
        'title' => 'Urtext Score PDF',
        'type' => 'pdf',
        'file_path' => 'lessons/sample.pdf',
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'path_type' => 'standard',
        'status' => 'active',
        'progress_percent' => 0.00,
    ]);

    EnrollmentModule::create([
        'enrollment_id' => $enrollment->id,
        'module_id' => $module->id,
        'amount_paid_kes' => 400000,
    ]);

    // 1. YouTube lesson reported at 80% watched -> should be in_progress
    $ytProgress1 = $this->actingAs($student)
        ->postJson("/api/v1/student/lessons/{$lessonYt->uuid}/progress", [
            'progress_percent' => 80,
            'position_seconds' => 480,
        ]);

    $ytProgress1->assertOk()
        ->assertJsonPath('lesson_status', 'in_progress')
        ->assertJsonPath('lesson_progress_percent', 80);

    // 2. YouTube lesson reaches 90% watched -> auto-completes!
    $ytProgress2 = $this->actingAs($student)
        ->postJson("/api/v1/student/lessons/{$lessonYt->uuid}/progress", [
            'progress_percent' => 90,
            'position_seconds' => 540,
        ]);

    $ytProgress2->assertOk()
        ->assertJsonPath('lesson_status', 'completed')
        ->assertJsonPath('lesson_progress_percent', 100);

    // Course progress now: 1 of 3 lessons completed = 33.33%
    expect((float) $ytProgress2->json('course_progress_percent'))->toEqual(33.33);

    // 3. Audio lesson reaches 92% played -> auto-completes!
    $audioProgress = $this->actingAs($student)
        ->postJson("/api/v1/student/lessons/{$lessonAudio->uuid}/progress", [
            'progress_percent' => 92,
            'position_seconds' => 276,
        ]);

    $audioProgress->assertOk()
        ->assertJsonPath('lesson_status', 'completed')
        ->assertJsonPath('lesson_progress_percent', 100);

    // Course progress now: 2 of 3 lessons completed = 66.67%
    expect((float) $audioProgress->json('course_progress_percent'))->toEqual(66.67);

    // 4. PDF lesson: student opens and clicks Mark Complete
    $pdfProgress = $this->actingAs($student)
        ->postJson("/api/v1/student/lessons/{$lessonPdf->uuid}/progress", [
            'progress_percent' => 100,
            'mark_complete' => true,
        ]);

    $pdfProgress->assertOk()
        ->assertJsonPath('lesson_status', 'completed')
        ->assertJsonPath('course_progress_percent', 100)
        ->assertJsonPath('course_status', 'completed');

    $enrollment->refresh();
    expect((float) $enrollment->progress_percent)->toEqual(100.0)
        ->and($enrollment->status)->toEqual('completed')
        ->and($enrollment->completed_at)->not->toBeNull();
});

test('signed media streaming endpoint serves audio with HTTP 206 range requests', function () {
    $privateDisk = config('filesystems.private_disk', 'local');
    $audioContent = str_repeat('A', 1000); // 1000 byte mock audio file
    $filePath = 'lessons/test_stream_audio.mp3';

    Storage::disk($privateDisk)->put($filePath, $audioContent);

    $lesson = Lesson::create([
        'module_id' => Module::create([
            'course_id' => Course::create(['title' => 'Test Stream Course', 'slug' => 'test-stream-course'])->id,
            'title' => 'Test Stream Module',
            'level' => 'beginner',
            'price_kes' => 100000,
        ])->id,
        'title' => 'Test Audio Stream Lesson',
        'type' => 'audio',
        'file_path' => $filePath,
        'file_disk' => $privateDisk,
        'file_name' => 'test_stream_audio.mp3',
        'mime_type' => 'audio/mpeg',
        'file_size_bytes' => 1000,
    ]);

    $signedUrl = app(MediaStreamService::class)->generateSignedStreamUrl($lesson, 30);
    $parsed = parse_url($signedUrl);

    // 1. Unsigned request is forbidden/unauthorized
    $unsignedResponse = $this->get('/api/v1/media/lessons/'.$lesson->uuid);
    $unsignedResponse->assertStatus(401);

    // 2. Request first 200 bytes (Range: bytes=0-199)
    $rangeResponse = $this->withHeaders(['Range' => 'bytes=0-199'])
        ->get($parsed['path'].'?'.$parsed['query']);

    $rangeResponse->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-199/1000')
        ->assertHeader('Content-Length', '200')
        ->assertHeader('Accept-Ranges', 'bytes');
});
