<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(
        protected SettingsService $settingsService
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::all();
        $grouped = $settings->groupBy('group')->map(function ($items, $group) {
            return [
                'group' => $group,
                'items' => SettingResource::collection($items),
            ];
        })->values();

        return response()->json([
            'data' => $grouped,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $updates = $request->input('settings', []);
        $this->settingsService->setMany($updates);

        activity()
            ->causedBy($request->user())
            ->log('Updated system settings: '.implode(', ', array_keys($updates)));

        return $this->index();
    }
}
