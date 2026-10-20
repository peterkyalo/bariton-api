<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\ContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCmsController extends Controller
{
    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized. Admin access required.');
        }
    }

    /**
     * List all CMS pages with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = CmsPage::query()->orderBy('order', 'asc')->latest('updated_at');

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%")
                    ->orWhere('category', 'like', "%{$s}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->has('is_published') && $request->query('is_published') !== '') {
            $query->where('is_published', filter_var($request->query('is_published'), FILTER_VALIDATE_BOOLEAN));
        }

        $pages = $query->paginate($request->query('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $pages,
        ]);
    }

    /**
     * Create a new CMS page.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:150', 'alpha_dash', 'unique:cms_pages,slug'],
            'category' => ['nullable', 'string', 'max:50'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'hero_badge' => ['nullable', 'string', 'max:150'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:1000'],
            'hero_cta_text' => ['nullable', 'string', 'max:100'],
            'hero_cta_link' => ['nullable', 'string', 'max:255'],
            'hero_image_url' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'is_published' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $page = CmsPage::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'CMS Page created successfully.',
            'data' => $page,
        ], 201);
    }

    /**
     * Get a specific CMS page by ID or slug for editing.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $page = is_numeric($id)
            ? CmsPage::findOrFail($id)
            : CmsPage::where('slug', $id)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $page,
        ]);
    }

    /**
     * Update an existing CMS page.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $page = is_numeric($id)
            ? CmsPage::findOrFail($id)
            : CmsPage::where('slug', $id)->firstOrFail();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:150', 'alpha_dash', Rule::unique('cms_pages', 'slug')->ignore($page->id)],
            'category' => ['nullable', 'string', 'max:50'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'hero_badge' => ['nullable', 'string', 'max:150'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:1000'],
            'hero_cta_text' => ['nullable', 'string', 'max:100'],
            'hero_cta_link' => ['nullable', 'string', 'max:255'],
            'hero_image_url' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'is_published' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer'],
        ]);

        // Do not change slug for the core 'home' page
        if ($page->slug === 'home' && isset($validated['slug']) && $validated['slug'] !== 'home') {
            unset($validated['slug']);
        }

        $page->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'CMS Page updated successfully.',
            'data' => $page,
        ]);
    }

    /**
     * Delete a CMS page.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $page = is_numeric($id)
            ? CmsPage::findOrFail($id)
            : CmsPage::where('slug', $id)->firstOrFail();

        if ($page->slug === 'home') {
            return response()->json([
                'status' => 'error',
                'message' => 'The root home landing page cannot be deleted.',
            ], 422);
        }

        $page->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Page '{$page->title}' deleted successfully.",
        ]);
    }

    /**
     * List prospective student contact inquiries.
     */
    public function inquiries(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = ContactInquiry::query()->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('subject', 'like', "%{$s}%");
            });
        }

        $inquiries = $query->paginate($request->query('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $inquiries,
        ]);
    }

    /**
     * Update contact inquiry status and admin notes.
     */
    public function updateInquiry(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $inquiry = is_numeric($id)
            ? ContactInquiry::findOrFail($id)
            : ContactInquiry::where('uuid', $id)->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,in_progress,resolved,archived'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === 'resolved' && ! $inquiry->responded_at) {
            $validated['responded_at'] = now();
        }

        $inquiry->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Inquiry updated successfully.',
            'data' => $inquiry,
        ]);
    }
}
