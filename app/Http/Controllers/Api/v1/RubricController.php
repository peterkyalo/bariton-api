<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Rubric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RubricController extends Controller
{
    /**
     * List reusable rubric templates.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeInstructor();

        $rubrics = Rubric::query()
            ->with(['criteria.levels', 'instructor'])
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json($rubrics);
    }

    /**
     * Create a new rubric template with criteria and scoring levels.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeInstructor();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.title' => ['required', 'string', 'max:255'],
            'criteria.*.description' => ['nullable', 'string'],
            'criteria.*.max_points' => ['required', 'integer', 'min:1'],
            'criteria.*.levels' => ['required', 'array', 'min:1'],
            'criteria.*.levels.*.title' => ['required', 'string', 'max:255'],
            'criteria.*.levels.*.points' => ['required', 'integer', 'min:0'],
            'criteria.*.levels.*.description' => ['nullable', 'string'],
        ]);

        $rubric = DB::transaction(function () use ($request, $validated) {
            $totalPoints = 0;
            foreach ($validated['criteria'] as $crit) {
                $totalPoints += (int) $crit['max_points'];
            }

            $rubric = Rubric::create([
                'instructor_id' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'total_points' => $totalPoints,
                'is_template' => true,
            ]);

            foreach ($validated['criteria'] as $cIndex => $critData) {
                $criterion = $rubric->criteria()->create([
                    'title' => $critData['title'],
                    'description' => $critData['description'] ?? null,
                    'max_points' => $critData['max_points'],
                    'order' => $cIndex + 1,
                ]);

                foreach ($critData['levels'] as $lIndex => $lvlData) {
                    $criterion->levels()->create([
                        'title' => $lvlData['title'],
                        'points' => $lvlData['points'],
                        'description' => $lvlData['description'] ?? null,
                        'order' => $lIndex + 1,
                    ]);
                }
            }

            return $rubric->load('criteria.levels');
        });

        return response()->json([
            'message' => 'Rubric template created successfully.',
            'rubric' => $rubric,
        ], 201);
    }

    /**
     * Show a specific rubric template.
     */
    public function show(string $uuid): JsonResponse
    {
        $this->authorizeInstructor();

        $rubric = Rubric::where('uuid', $uuid)
            ->with(['criteria.levels', 'instructor'])
            ->firstOrFail();

        return response()->json(['rubric' => $rubric]);
    }

    /**
     * Delete a rubric template if not assigned to active assignments.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $this->authorizeInstructor();

        $rubric = Rubric::where('uuid', $uuid)->firstOrFail();

        if ($rubric->assignments()->exists()) {
            return response()->json(['message' => 'Cannot delete rubric that is assigned to active course assignments.'], 400);
        }

        $rubric->delete();

        return response()->json(['message' => 'Rubric template deleted successfully.']);
    }

    protected function authorizeInstructor(): void
    {
        if (! request()->user() || (! request()->user()->can('courses.teach') && ! request()->user()->can('courses.manage-all'))) {
            abort(403, 'Unauthorized. Instructor access required.');
        }
    }
}
