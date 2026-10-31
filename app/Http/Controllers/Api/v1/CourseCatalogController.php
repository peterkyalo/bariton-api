<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseCatalogController extends Controller
{
    /**
     * Public course catalog listing.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Course::query()
            ->where('status', 'published')
            ->with(['instructors', 'modules' => fn ($q) => $q->orderBy('order')]);

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('short_description', 'like', $search);
            });
        }

        if ($request->filled('level')) {
            $level = $request->input('level');
            $query->whereHas('modules', function ($q) use ($level) {
                $q->where('level', $level);
            });
        }

        $courses = $query->latest()->paginate($request->input('per_page', 12));

        $request->attributes->set('hide_lesson_content', true);

        return CourseResource::collection($courses);
    }

    /**
     * Public course detail with full syllabus and upgrade price if logged in.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        // Public catalog only exposes promotional media (images, YouTube) - never PDFs/audio or lesson bodies.
        $promoOnly = fn ($q) => $q->whereIn('kind', ['image', 'youtube'])->orderBy('order');

        $course = Course::query()
            ->where('slug', $slug)
            ->with([
                'instructors',
                'attachments' => $promoOnly,
                'modules' => fn ($q) => $q->orderBy('order'),
                'modules.attachments' => $promoOnly,
                'modules.lessons' => fn ($q) => $q->orderBy('order'),
            ])
            ->firstOrFail();

        $this->authorize('view', $course);

        $request->attributes->set('hide_lesson_content', true);

        return response()->json([
            'course' => new CourseResource($course),
        ]);
    }

    /**
     * Retrieve free preview lesson data for marketing/catalog.
     */
    public function preview(string $slug, string $lessonUuid): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $lesson = Lesson::query()
            ->where('uuid', $lessonUuid)
            ->where('is_preview', true)
            ->whereHas('module', fn ($q) => $q->where('course_id', $course->id))
            ->firstOrFail();

        return response()->json([
            'course_title' => $course->title,
            'lesson' => new LessonResource($lesson),
        ]);
    }
}
