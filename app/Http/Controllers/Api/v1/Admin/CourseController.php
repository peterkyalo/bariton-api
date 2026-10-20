<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * List all courses with administrative filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Course::class);

        $query = Course::query()
            ->with(['instructors', 'modules.lessons']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('short_description', 'like', $search);
            });
        }

        $courses = $query->latest()->paginate($request->input('per_page', 15));

        return CourseResource::collection($courses);
    }

    /**
     * Create a new course.
     */
    public function store(CreateCourseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $instructorIds = $data['instructor_ids'] ?? [];
        unset($data['instructor_ids']);

        $data['created_by'] = $request->user()->id;

        $course = Course::create($data);

        if (! empty($instructorIds)) {
            $course->instructors()->sync($instructorIds);
        }

        $course->load(['instructors', 'modules']);

        return response()->json([
            'message' => 'Course created successfully.',
            'course' => new CourseResource($course),
        ], 201);
    }

    /**
     * View course administrative details.
     */
    public function show(Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        $course->loadMissing(['instructors', 'modules.lessons']);

        return response()->json([
            'course' => new CourseResource($course),
        ]);
    }

    /**
     * Update an existing course.
     */
    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $request->validated();
        $instructorIds = $data['instructor_ids'] ?? null;
        unset($data['instructor_ids']);

        $course->update($data);

        if (is_array($instructorIds)) {
            $course->instructors()->sync($instructorIds);
        }

        $course->load(['instructors', 'modules.lessons']);

        return response()->json([
            'message' => 'Course updated successfully.',
            'course' => new CourseResource($course),
        ]);
    }

    /**
     * Delete a course.
     */
    public function destroy(Course $course): JsonResponse
    {
        $this->authorize('delete', $course);

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ]);
    }
}
