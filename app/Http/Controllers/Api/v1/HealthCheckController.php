<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // 1. Database Check
        try {
            DB::connection()->getPdo();
            $checks['database'] = [
                'status' => 'ok',
                'driver' => DB::connection()->getDriverName(),
            ];
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $checks['database'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // 2. Cache Check
        try {
            $testKey = 'health_check_ping_'.time();
            Cache::put($testKey, 'pong', 5);
            $cacheVal = Cache::get($testKey);
            Cache::forget($testKey);

            $checks['cache'] = [
                'status' => $cacheVal === 'pong' ? 'ok' : 'error',
            ];
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $checks['cache'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // 3. Storage Check
        try {
            $isWritable = is_writable(storage_path('app'));
            $checks['storage'] = [
                'status' => $isWritable ? 'ok' : 'error',
                'path' => storage_path('app'),
            ];
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $checks['storage'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        $httpCode = $status === 'healthy' ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'app_version' => '1.0.0',
            'environment' => config('app.env'),
            'checks' => $checks,
        ], $httpCode);
    }
}
