<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class PublicSettingController extends Controller
{
    public function __construct(
        protected SettingsService $settingsService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingsService->getPublic(),
        ]);
    }
}
