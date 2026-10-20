<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPageController extends Controller
{
    /**
     * Public list of published CMS pages (for navigation menus, footer links, etc.)
     */
    public function index(Request $request): JsonResponse
    {
        $query = CmsPage::where('is_published', true)->orderBy('order', 'asc');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        $pages = $query->select([
            'id',
            'uuid',
            'slug',
            'title',
            'category',
            'hero_badge',
            'hero_title',
            'hero_subtitle',
            'order',
        ])->get();

        return response()->json([
            'status' => 'success',
            'data' => $pages,
        ]);
    }

    /**
     * Public retrieval of a single published CMS page by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (! $page) {
            return response()->json([
                'status' => 'error',
                'message' => 'Page not found or is currently not published.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $page,
        ]);
    }
}
