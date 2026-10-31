<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake(config('filesystems.private_disk', 'local'));
});

test('instructor can upload all 4 course learning materials: PDF, Richtext, Audio, and YouTube Video ID', function () {
    $instructor = User::factory()->create(['email' => 'instructor-materials@bariton.test']);
    $instructor->assignRole('instructor');

    $course = Course::create([
        'title' => 'Mastering Orchestration & Harmony',
        'slug' => 'mastering-orchestration-harmony',
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id);

    $module = $course->modules()->create([
        'title' => 'Woodwinds & Strings Ensemble',
        'level' => 'intermediate',
        'order' => 1,
        'price_kes' => 450000,
    ]);

    // 1. YouTube lesson with full URL (verifying ID extraction)
    $resYoutube = $this->actingAs($instructor)->postJson("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Intro to Woodwind Orchestration',
        'type' => 'youtube',
        'youtube_video_id' => 'https://www.youtube.com/watch?v=Mh4f9AYRCZY',
        'duration_seconds' => 720,
        'is_preview' => true,
    ]);
    $resYoutube->assertCreated()
        ->assertJsonPath('lesson.type', 'youtube')
        ->assertJsonPath('lesson.youtube_video_id', 'Mh4f9AYRCZY');

    // 2. Richtext lesson with rich formatting content
    $richContent = "<h2>Overview of Orchestral Notation</h2><p>Observe the transposition rules for French Horns and Clarinet in B-flat.</p><ul><li>Transposition: Major 2nd down</li><li>Range: D3 to F6</li></ul>";
    $resRichtext = $this->actingAs($instructor)->postJson("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Transposition Guides & Notation Manual',
        'type' => 'richtext',
        'content' => $richContent,
        'summary' => 'Essential transpositions for ensemble composition.',
        'duration_seconds' => 450,
        'is_preview' => false,
    ]);
    $resRichtext->assertCreated()
        ->assertJsonPath('lesson.type', 'richtext')
        ->assertJsonPath('lesson.content', $richContent);

    // 3. Audio lesson with audio upload
    $audioFile = UploadedFile::fake()->create('woodwind_tuning.mp3', 2048, 'audio/mpeg');
    $resAudio = $this->actingAs($instructor)->post("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Concert A Tuning & Harmonic Pitch Reference',
        'type' => 'audio',
        'duration_seconds' => 180,
        'file' => $audioFile,
    ]);
    $resAudio->assertCreated()
        ->assertJsonPath('lesson.type', 'audio')
        ->assertJsonPath('lesson.file_name', 'woodwind_tuning.mp3');

    // 4. PDF lesson with PDF score upload
    $pdfFile = UploadedFile::fake()->create('orchestral_score.pdf', 3072, 'application/pdf');
    $resPdf = $this->actingAs($instructor)->post("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Full Symphony Score - Allegro con Brio',
        'type' => 'pdf',
        'file' => $pdfFile,
    ]);
    $resPdf->assertCreated()
        ->assertJsonPath('lesson.type', 'pdf')
        ->assertJsonPath('lesson.file_name', 'orchestral_score.pdf');

    expect($module->lessons()->count())->toBe(4);
});

test('validation rejects invalid uploads and empty rich text', function () {
    $instructor = User::factory()->create(['email' => 'instructor-validation@bariton.test']);
    $instructor->assignRole('instructor');

    $course = Course::create(['title' => 'Music Theory', 'slug' => 'music-theory', 'status' => 'published']);
    $course->instructors()->attach($instructor->id);
    $module = $course->modules()->create(['title' => 'Basics', 'level' => 'beginner', 'price_kes' => 100000]);

    // Missing content for richtext
    $resEmptyText = $this->actingAs($instructor)->postJson("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Empty Guide',
        'type' => 'richtext',
    ]);
    $resEmptyText->assertUnprocessable()->assertJsonValidationErrors(['content']);

    // Invalid file for audio (passing pdf to audio)
    $wrongFile = UploadedFile::fake()->create('not_audio.pdf', 500, 'application/pdf');
    $resWrongAudio = $this->actingAs($instructor)->post("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Invalid Audio',
        'type' => 'audio',
        'file' => $wrongFile,
    ]);
    $resWrongAudio->assertUnprocessable()->assertJsonValidationErrors(['file']);
});

test('student can view richtext lesson and mark complete to progress course', function () {
    $student = User::factory()->create(['email' => 'student-materials@bariton.test']);
    $student->assignRole('student');

    $course = Course::create(['title' => 'Counterpoint Mastery', 'slug' => 'counterpoint-mastery', 'status' => 'published']);
    $module = $course->modules()->create(['title' => 'Species Counterpoint', 'level' => 'intermediate', 'price_kes' => 200000]);

    $lesson = $module->lessons()->create([
        'title' => 'First Species Counterpoint Principles',
        'slug' => 'first-species-counterpoint-principles',
        'type' => 'richtext',
        'content' => '<h1>First Species Guidelines</h1><p>Note against note: consonances only (3rds, 5ths, 6ths, octaves).</p>',
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'path_type' => 'standard',
        'progress_percent' => 0,
    ]);

    EnrollmentModule::create([
        'enrollment_id' => $enrollment->id,
        'module_id' => $module->id,
        'is_unlocked' => true,
    ]);

    // Student fetches lesson detail
    $detailRes = $this->actingAs($student)->getJson("/api/v1/student/lessons/{$lesson->uuid}");
    $detailRes->assertOk()
        ->assertJsonPath('lesson.type', 'richtext')
        ->assertJsonPath('lesson.content', '<h1>First Species Guidelines</h1><p>Note against note: consonances only (3rds, 5ths, 6ths, octaves).</p>');

    // Student records completion
    $progRes = $this->actingAs($student)->postJson("/api/v1/student/lessons/{$lesson->uuid}/progress", [
        'progress_percent' => 100,
        'mark_complete' => true,
    ]);
    $progRes->assertOk()
        ->assertJsonPath('lesson_status', 'completed')
        ->assertJsonPath('course_progress_percent', 100);
});
