<?php

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Rubric;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('local');
});

function setupCourseAndEnrollment(): array
{
    $instructor = User::factory()->create();
    $instructor->assignRole('instructor');

    $student = User::factory()->create();
    $student->assignRole('student');

    $course = Course::create([
        'title' => 'Conservatory Cello Performance',
        'slug' => 'conservatory-cello-performance',
        'description' => 'Advanced conservatory cello repertoire and technique.',
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['is_primary' => true]);

    $module = $course->modules()->create([
        'title' => 'Bach Cello Suite No. 1 Prelude',
        'level' => 'advanced',
        'order' => 1,
        'price_kes' => 600000,
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'path_type' => 'standard',
        'status' => 'active',
        'assignment_pass_mark_percent' => 70,
    ]);

    EnrollmentModule::create([
        'enrollment_id' => $enrollment->id,
        'module_id' => $module->id,
        'amount_paid_kes' => 600000,
        'unlocked_at' => now(),
    ]);

    // Create a Rubric template
    $rubric = Rubric::create([
        'instructor_id' => $instructor->id,
        'title' => 'Strings Performance Assessment Rubric',
        'description' => 'Rubric assessing rhythm, intonation, and tone quality.',
        'total_points' => 100,
        'is_template' => true,
    ]);

    $c1 = $rubric->criteria()->create([
        'title' => 'Intonation & Pitch Accuracy',
        'max_points' => 50,
        'order' => 1,
    ]);
    $c1->levels()->create(['title' => 'Exceptional', 'points' => 50, 'order' => 1]);
    $c1->levels()->create(['title' => 'Proficient', 'points' => 40, 'order' => 2]);
    $c1->levels()->create(['title' => 'Developing', 'points' => 25, 'order' => 3]);
    $c1->levels()->create(['title' => 'Inaccurate', 'points' => 10, 'order' => 4]);

    $c2 = $rubric->criteria()->create([
        'title' => 'Tone Quality & Bow Technique',
        'max_points' => 50,
        'order' => 2,
    ]);
    $c2->levels()->create(['title' => 'Warm & Resonant', 'points' => 50, 'order' => 1]);
    $c2->levels()->create(['title' => 'Clear & Controlled', 'points' => 40, 'order' => 2]);
    $c2->levels()->create(['title' => 'Occasional Scratch', 'points' => 25, 'order' => 3]);
    $c2->levels()->create(['title' => 'Weak Projection', 'points' => 10, 'order' => 4]);

    $assignment = Assignment::create([
        'module_id' => $module->id,
        'rubric_id' => $rubric->id,
        'title' => 'Record Suite No. 1 Prelude (Measures 1-42)',
        'instructions' => 'Record a continuous unedited video performance with full instrument view.',
        'submission_type' => 'video',
        'accepted_formats' => ['mp4', 'webm'],
        'max_file_size_mb' => 200,
        'is_published' => true,
    ]);

    return compact('instructor', 'student', 'course', 'module', 'enrollment', 'rubric', 'assignment', 'c1', 'c2');
}

test('student uploads video with resumable chunks, resumes after interruption, and submits assignment', function () {
    $data = setupCourseAndEnrollment();
    $student = $data['student'];
    $assignment = $data['assignment'];

    // 1. Initiate 150 MB video chunked upload (simulated chunk size 100 KB across 3 test chunks)
    $fileSizeBytes = 300 * 1024;
    $chunkSizeBytes = 100 * 1024;

    $initRes = $this->actingAs($student)->postJson('/api/v1/uploads/chunked/initiate', [
        'filename' => 'bach_prelude_performance.mp4',
        'file_size_bytes' => $fileSizeBytes,
        'chunk_size_bytes' => $chunkSizeBytes,
        'mime_type' => 'video/mp4',
    ]);

    $initRes->assertCreated();
    $uploadUuid = $initRes->json('upload.uuid');
    expect($initRes->json('upload.total_chunks'))->toBe(3);

    // 2. Upload Chunk 0
    $chunk0Data = str_repeat('A', $chunkSizeBytes);
    $chunk0File = UploadedFile::fake()->createWithContent('chunk_0.part', $chunk0Data);

    $chunk0Res = $this->actingAs($student)->postJson("/api/v1/uploads/chunked/{$uploadUuid}/chunk", [
        'chunk_index' => 0,
        'chunk' => $chunk0File,
    ]);
    $chunk0Res->assertOk();
    expect($chunk0Res->json('upload.chunks_completed'))->toContain(0);

    // 3. Network interruption: student checks upload status to resume
    $statusRes = $this->actingAs($student)->getJson("/api/v1/uploads/chunked/{$uploadUuid}/status");
    $statusRes->assertOk();
    expect($statusRes->json('upload.chunks_completed'))->toEqual([0]);
    expect($statusRes->json('upload.is_complete'))->toBeFalse();

    // 4. Resume upload: upload remaining chunks 1 and 2
    $chunk1File = UploadedFile::fake()->createWithContent('chunk_1.part', str_repeat('B', $chunkSizeBytes));
    $this->actingAs($student)->postJson("/api/v1/uploads/chunked/{$uploadUuid}/chunk", [
        'chunk_index' => 1,
        'chunk' => $chunk1File,
    ])->assertOk();

    $chunk2File = UploadedFile::fake()->createWithContent('chunk_2.part', str_repeat('C', $chunkSizeBytes));
    $this->actingAs($student)->postJson("/api/v1/uploads/chunked/{$uploadUuid}/chunk", [
        'chunk_index' => 2,
        'chunk' => $chunk2File,
    ])->assertOk();

    // 5. Complete and assemble upload
    $completeRes = $this->actingAs($student)->postJson("/api/v1/uploads/chunked/{$uploadUuid}/complete");
    $completeRes->assertOk();
    $finalPath = $completeRes->json('file_path');
    expect($finalPath)->not()->toBeEmpty();
    expect(Storage::disk('local')->exists($finalPath))->toBeTrue();

    // 6. Student submits assignment using the completed chunked upload
    $submitRes = $this->actingAs($student)->postJson("/api/v1/student/assignments/{$assignment->uuid}/submit", [
        'chunked_upload_uuid' => $uploadUuid,
        'notes' => 'Recorded using a Larsen A string and carbon fiber bow.',
    ]);

    $submitRes->assertCreated();
    $submissionUuid = $submitRes->json('submission.uuid');

    $submission = AssignmentSubmission::where('uuid', $submissionUuid)->firstOrFail();
    expect($submission->status)->toBe('submitted');
    expect($submission->attempt_number)->toBe(1);
    expect($submission->file_path)->toBe($finalPath);
});

test('instructor grades assignment with rubric template and student sees score and feedback', function () {
    $data = setupCourseAndEnrollment();
    $instructor = $data['instructor'];
    $student = $data['student'];
    $assignment = $data['assignment'];
    $c1 = $data['c1'];
    $c2 = $data['c2'];

    // Create a student submission
    $submission = AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'enrollment_id' => $data['enrollment']->id,
        'user_id' => $student->id,
        'attempt_number' => 1,
        'file_path' => 'submissions/test/video.mp4',
        'original_filename' => 'video.mp4',
        'file_size_bytes' => 150000000,
        'media_type' => 'video',
        'status' => 'submitted',
    ]);
    Storage::disk('local')->put('submissions/test/video.mp4', 'dummy_video_stream');

    // 1. Instructor checks grading queue
    $queueRes = $this->actingAs($instructor)->getJson('/api/v1/instructor/grading');
    $queueRes->assertOk();
    expect($queueRes->json('data'))->toHaveCount(1);
    expect($queueRes->json('data.0.uuid'))->toBe($submission->uuid);

    // 2. Instructor opens submission detail (returns signed stream URL and rubric)
    $detailRes = $this->actingAs($instructor)->getJson("/api/v1/instructor/grading/{$submission->uuid}");
    $detailRes->assertOk()
        ->assertJsonStructure(['submission', 'stream_url', 'rubric', 'pass_mark_percent']);

    // 3. Instructor grades: Intonation Proficient (40/50), Tone Warm & Resonant (50/50) -> 90 / 100 = 90% (Pass >= 70%)
    $proficientLevel = $c1->levels()->where('title', 'Proficient')->firstOrFail();
    $warmLevel = $c2->levels()->where('title', 'Warm & Resonant')->firstOrFail();

    $gradeRes = $this->actingAs($instructor)->postJson("/api/v1/instructor/grading/{$submission->uuid}/grade", [
        'rubric_scores' => [
            [
                'criterion_id' => $c1->id,
                'level_id' => $proficientLevel->id,
                'comments' => 'Solid shifting in third position, minor sharp in measure 24.',
            ],
            [
                'criterion_id' => $c2->id,
                'level_id' => $warmLevel->id,
                'comments' => 'Magnificent bow distribution and rich tone.',
            ],
        ],
        'feedback' => 'Exceptional rendition of the Bach Prelude. Very musical phrasing throughout.',
    ]);

    $gradeRes->assertOk();
    $submission->refresh();

    expect((float) $submission->score_percent)->toBe(90.00);
    expect($submission->passed)->toBeTrue();
    expect($submission->status)->toBe('graded');
    expect($submission->graded_by)->toBe($instructor->id);

    // 4. Student views assignment detail and sees score, rubric breakdown and feedback
    $studentViewRes = $this->actingAs($student)->getJson("/api/v1/student/assignments/{$assignment->uuid}");
    $studentViewRes->assertOk();

    $latestSub = $studentViewRes->json('submissions.0');
    expect((float) $latestSub['score_percent'])->toBe(90.00);
    expect($latestSub['passed'])->toBeTrue();
    expect($latestSub['feedback'])->toContain('Exceptional rendition of the Bach Prelude');
    expect($latestSub['rubric_scores'])->toHaveCount(2);
    expect($studentViewRes->json('can_submit'))->toBeFalse(); // Already passed
});

test('student resubmits failed assignment when score falls below enrollment pass mark', function () {
    $data = setupCourseAndEnrollment();
    $instructor = $data['instructor'];
    $student = $data['student'];
    $assignment = $data['assignment'];
    $c1 = $data['c1'];
    $c2 = $data['c2'];

    $submission = AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'enrollment_id' => $data['enrollment']->id,
        'user_id' => $student->id,
        'attempt_number' => 1,
        'file_path' => 'submissions/test/video1.mp4',
        'original_filename' => 'video1.mp4',
        'file_size_bytes' => 1000000,
        'media_type' => 'video',
        'status' => 'submitted',
    ]);

    // Instructor grades with Inaccurate (10/50) and Weak Projection (10/50) -> 20% < 70%
    $inaccurateLevel = $c1->levels()->where('title', 'Inaccurate')->firstOrFail();
    $weakLevel = $c2->levels()->where('title', 'Weak Projection')->firstOrFail();

    $this->actingAs($instructor)->postJson("/api/v1/instructor/grading/{$submission->uuid}/grade", [
        'rubric_scores' => [
            ['criterion_id' => $c1->id, 'level_id' => $inaccurateLevel->id],
            ['criterion_id' => $c2->id, 'level_id' => $weakLevel->id],
        ],
        'feedback' => 'Please practice with a metronome and retune your C string before resubmitting.',
    ])->assertOk();

    $submission->refresh();
    expect($submission->passed)->toBeFalse();
    expect($submission->status)->toBe('resubmission_required');

    // Student checks assignment: can_submit is true
    $checkRes = $this->actingAs($student)->getJson("/api/v1/student/assignments/{$assignment->uuid}");
    expect($checkRes->json('can_submit'))->toBeTrue();

    // Student resubmits attempt #2
    $file = UploadedFile::fake()->create('attempt2_performance.mp4', 500, 'video/mp4');

    $resubmitRes = $this->actingAs($student)->postJson("/api/v1/student/assignments/{$assignment->uuid}/submit", [
        'file' => $file,
        'notes' => 'Attempt 2 with corrected tuning and slower tempo.',
    ]);

    $resubmitRes->assertCreated();
    expect($resubmitRes->json('submission.attempt_number'))->toBe(2);
    expect($resubmitRes->json('submission.status'))->toBe('submitted');
});
