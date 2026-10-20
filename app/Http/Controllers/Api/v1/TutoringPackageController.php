<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutoringPackageResource;
use App\Models\TutoringPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class TutoringPackageController extends Controller
{
    /**
     * Public list of active tutoring packages.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TutoringPackage::with(['course', 'instructor'])
            ->where('is_active', true);

        if ($request->has('course_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('course_id', $request->query('course_id'))
                    ->orWhereNull('course_id');
            });
        }

        if ($request->has('instructor_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('instructor_id', $request->query('instructor_id'))
                    ->orWhereNull('instructor_id');
            });
        }

        $packages = $query->orderBy('price_kes', 'asc')->get();

        return TutoringPackageResource::collection($packages);
    }

    /**
     * Public package detail by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $package = TutoringPackage::with(['course', 'instructor'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'package' => new TutoringPackageResource($package),
        ]);
    }

    /**
     * Admin: Create a tutoring package.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
            'instructor_id' => 'nullable|exists:users,id',
            'session_count' => 'required|integer|min:1|max:50',
            'price_kes' => 'required|integer|min:0',
            'price_usd_cents' => 'nullable|integer|min:0',
            'validity_days' => 'required|integer|min:7|max:365',
            'is_active' => 'sometimes|boolean',
        ]);

        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (TutoringPackage::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $package = TutoringPackage::create(array_merge($validated, ['slug' => $slug]));

        return response()->json([
            'message' => 'Tutoring package created successfully.',
            'package' => new TutoringPackageResource($package),
        ], 201);
    }

    /**
     * Admin: Update tutoring package.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $package = TutoringPackage::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
            'instructor_id' => 'nullable|exists:users,id',
            'session_count' => 'sometimes|integer|min:1|max:50',
            'price_kes' => 'sometimes|integer|min:0',
            'price_usd_cents' => 'nullable|integer|min:0',
            'validity_days' => 'sometimes|integer|min:7|max:365',
            'is_active' => 'sometimes|boolean',
        ]);

        $package->update($validated);

        return response()->json([
            'message' => 'Tutoring package updated successfully.',
            'package' => new TutoringPackageResource($package->fresh(['course', 'instructor'])),
        ]);
    }
}
