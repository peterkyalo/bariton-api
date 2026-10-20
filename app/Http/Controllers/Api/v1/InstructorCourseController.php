<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateLessonRequest;
use App\Http\Requests\CreateModuleRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\ModuleResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstructorCourseController extends Controller
{
    /**
     * List all courses assigned to the instructor.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $courses = $request->user()->coursesTeaching()
            ->with(['instructors', 'modules.lessons'])
            ->latest()
            ->paginate($request->input('per_page', 10));

        return CourseResource::collection($courses);
    }

    /**
     * View instructor course syllabus.
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $course->loadMissing(['instructors', 'modules.lessons']);

        return response()->json([
            'course' => new CourseResource($course),
        ]);
    }

    /**
     * Add a module to the course.
     */
    public function createModule(CreateModuleRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $request->validated();
        if (empty($data['order'])) {
            $data['order'] = ($course->modules()->max('order') ?? 0) + 1;
        }

        $module = $course->modules()->create($data);

        return response()->json([
            'message' => 'Module created successfully.',
            'module' => new ModuleResource($module),
        ], 201);
    }

    /**
     * Update an existing module.
     */
    public function updateModule(UpdateModuleRequest $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        $module->update($request->validated());

        return response()->json([
            'message' => 'Module updated successfully.',
            'module' => new ModuleResource($module),
        ]);
    }

    /**
     * Delete a module.
     */
    public function deleteModule(Request $request, Module $module): JsonResponse
    {
        $this->authorize('delete', $module);

        $module->delete();

        return response()->json([
            'message' => 'Module removed successfully.',
        ]);
    }

    /**
     * Add a lesson to a module (YouTube, Audio, or PDF).
     */
    public function createLesson(CreateLessonRequest $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        $data = $request->validated();
        unset($data['file']);

        if (empty($data['order'])) {
            $data['order'] = ($module->lessons()->max('order') ?? 0) + 1;
        }

        $lessonUuid = (string) Str::uuid();
        $data['uuid'] = $lessonUuid;

        if ($request->hasFile('file') && in_array($request->input('type'), ['audio', 'pdf'])) {
            $uploadedFile = $request->file('file');
            $disk = config('filesystems.private_disk', 'local');
            $extension = $uploadedFile->getClientOriginalExtension() ?: ($request->input('type') === 'pdf' ? 'pdf' : 'mp3');
            $fileName = "lessons/{$lessonUuid}.{$extension}";

            $path = $uploadedFile->storeAs('lessons', "{$lessonUuid}.{$extension}", $disk);

            $data['file_path'] = $path;
            $data['file_disk'] = $disk;
            $data['file_name'] = $uploadedFile->getClientOriginalName();
            $data['mime_type'] = $uploadedFile->getMimeType();
            $data['file_size_bytes'] = $uploadedFile->getSize();
        }

        $lesson = $module->lessons()->create($data);

        return response()->json([
            'message' => 'Lesson created successfully.',
            'lesson' => new LessonResource($lesson),
        ], 201);
    }

    /**
     * Update an existing lesson.
     */
    public function updateLesson(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        $data = $request->validated();
        unset($data['file']);

        if ($request->hasFile('file') && in_array($request->input('type', $lesson->type), ['audio', 'pdf'])) {
            $uploadedFile = $request->file('file');
            $disk = config('filesystems.private_disk', 'local');
            $extension = $uploadedFile->getClientOriginalExtension() ?: 'bin';

            // Delete old file if present
            if ($lesson->file_path && Storage::disk($lesson->file_disk)->exists($lesson->file_path)) {
                Storage::disk($lesson->file_disk)->delete($lesson->file_path);
            }

            $path = $uploadedFile->storeAs('lessons', "{$lesson->uuid}.{$extension}", $disk);

            $data['file_path'] = $path;
            $data['file_disk'] = $disk;
            $data['file_name'] = $uploadedFile->getClientOriginalName();
            $data['mime_type'] = $uploadedFile->getMimeType();
            $data['file_size_bytes'] = $uploadedFile->getSize();
        }

        $lesson->update($data);

        return response()->json([
            'message' => 'Lesson updated successfully.',
            'lesson' => new LessonResource($lesson),
        ]);
    }

    /**
     * Delete a lesson.
     */
    public function deleteLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('delete', $lesson);

        if ($lesson->file_path && Storage::disk($lesson->file_disk)->exists($lesson->file_path)) {
            Storage::disk($lesson->file_disk)->delete($lesson->file_path);
        }

        $lesson->delete();

        return response()->json([
            'message' => 'Lesson removed successfully.',
        ]);
    }
}
