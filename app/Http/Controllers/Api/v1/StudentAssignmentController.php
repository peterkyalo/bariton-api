<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ChunkedUpload;
use App\Models\Enrollment;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentAssignmentController extends Controller
{
    /**
     * List all assignments for a module the student is enrolled in and has unlocked.
     */
    public function moduleAssignments(Request $request, string $moduleUuid): JsonResponse
    {
        $module = Module::where('uuid', $moduleUuid)->firstOrFail();
        $user = $request->user();

        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $module->course_id)
            ->where('status', 'active')
            ->firstOrFail();

        $isUnlocked = $enrollment->unlockedModules()->where('modules.id', $module->id)->exists();
        if (! $isUnlocked) {
            abort(403, 'This module is locked. Complete previous requirements or purchase enrollment to unlock.');
        }

        $assignments = $module->assignments()
            ->where('is_published', true)
            ->with(['rubric.criteria.levels'])
            ->get();

        $data = $assignments->map(function ($assignment) use ($user, $enrollment) {
            $latestSub = $assignment->latestSubmissionFor($user, $enrollment);

            return [
                'id' => $assignment->id,
                'uuid' => $assignment->uuid,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'submission_type' => $assignment->submission_type,
                'accepted_formats' => $assignment->accepted_formats,
                'max_file_size_mb' => $assignment->max_file_size_mb,
                'rubric' => $assignment->rubric,
                'latest_submission' => $latestSub ? [
                    'uuid' => $latestSub->uuid,
                    'attempt_number' => $latestSub->attempt_number,
                    'status' => $latestSub->status,
                    'score_percent' => $latestSub->score_percent,
                    'passed' => $latestSub->passed,
                    'feedback' => $latestSub->feedback,
                    'created_at' => $latestSub->created_at,
                    'can_resubmit' => $latestSub->canResubmit(),
                ] : null,
            ];
        });

        return response()->json([
            'module' => [
                'id' => $module->id,
                'uuid' => $module->uuid,
                'title' => $module->title,
            ],
            'assignments' => $data,
        ]);
    }

    /**
     * Get detailed assignment info with rubric and full submission attempt history.
     */
    public function show(Request $request, string $assignmentUuid): JsonResponse
    {
        $assignment = Assignment::where('uuid', $assignmentUuid)
            ->with(['module.course', 'rubric.criteria.levels'])
            ->firstOrFail();

        $user = $request->user();
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $assignment->module->course_id)
            ->firstOrFail();

        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->with(['rubricScores.criterion', 'rubricScores.level', 'grader'])
            ->orderBy('attempt_number', 'desc')
            ->get();

        $latest = $submissions->first();

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'uuid' => $assignment->uuid,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'submission_type' => $assignment->submission_type,
                'accepted_formats' => $assignment->accepted_formats,
                'max_file_size_mb' => $assignment->max_file_size_mb,
                'rubric' => $assignment->rubric,
                'course' => [
                    'id' => $assignment->module->course->id,
                    'title' => $assignment->module->course->title,
                ],
                'module' => [
                    'id' => $assignment->module->id,
                    'title' => $assignment->module->title,
                ],
            ],
            'submissions' => $submissions,
            'can_submit' => ! $latest || $latest->canResubmit(),
            'pass_mark_percent' => $enrollment->assignment_pass_mark_percent ?? 70,
        ]);
    }

    /**
     * Submit an assignment response (via completed chunked upload or direct upload).
     */
    public function submit(Request $request, string $assignmentUuid): JsonResponse
    {
        $assignment = Assignment::where('uuid', $assignmentUuid)
            ->with('module.course')
            ->firstOrFail();

        $user = $request->user();
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $assignment->module->course_id)
            ->where('status', 'active')
            ->firstOrFail();

        // Check unlocked module
        $isUnlocked = $enrollment->unlockedModules()->where('modules.id', $assignment->module_id)->exists();
        if (! $isUnlocked) {
            abort(403, 'Module must be unlocked before submitting assignments.');
        }

        // Check if student has already passed or has pending submission
        $latest = $assignment->latestSubmissionFor($user, $enrollment);
        if ($latest) {
            if ($latest->passed) {
                return response()->json(['message' => 'You have already passed this assignment.'], 400);
            }
            if ($latest->isPendingGrading()) {
                return response()->json(['message' => 'Your submission is currently awaiting grading by your instructor.'], 400);
            }
            if (! $latest->canResubmit()) {
                return response()->json(['message' => 'Resubmission is not permitted for this assignment.'], 400);
            }
        }

        $attemptNumber = $latest ? ($latest->attempt_number + 1) : 1;

        $filePath = null;
        $originalFilename = null;
        $fileSizeBytes = 0;
        $mediaType = 'video';

        if ($request->filled('chunked_upload_uuid')) {
            $chunkedUpload = ChunkedUpload::where('uuid', $request->input('chunked_upload_uuid'))
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->firstOrFail();

            $filePath = $chunkedUpload->final_path;
            $originalFilename = $chunkedUpload->original_filename;
            $fileSizeBytes = $chunkedUpload->file_size_bytes;
            $mediaType = str_starts_with($chunkedUpload->mime_type, 'audio') ? 'audio' : 'video';
        } elseif ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $fileSizeBytes = $file->getSize();
            $mime = $file->getMimeType();
            $mediaType = str_starts_with($mime, 'audio') ? 'audio' : 'video';

            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalFilename);
            $filePath = Storage::disk('local')->putFileAs("submissions/{$user->id}", $file, Str::uuid()."_{$safeName}");
        } else {
            throw ValidationException::withMessages([
                'file' => ['Please provide either a completed chunked_upload_uuid or an uploaded file.'],
            ]);
        }

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $user->id,
            'attempt_number' => $attemptNumber,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'file_size_bytes' => $fileSizeBytes,
            'media_type' => $mediaType,
            'student_notes' => $request->input('notes'),
            'status' => 'submitted',
        ]);

        activity('assessment')
            ->performedOn($submission)
            ->causedBy($user)
            ->withProperties([
                'assignment' => $assignment->title,
                'attempt' => $attemptNumber,
                'filename' => $originalFilename,
            ])
            ->log("Submitted attempt #{$attemptNumber} for assignment '{$assignment->title}'.");

        return response()->json([
            'message' => 'Assignment submitted successfully. Your instructor will grade your work shortly.',
            'submission' => $submission->fresh(),
        ], 201);
    }
}
