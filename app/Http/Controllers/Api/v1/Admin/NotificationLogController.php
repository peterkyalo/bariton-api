<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationLogResource;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\AfricasTalkingSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationLogController extends Controller
{
    public function __construct(
        protected AfricasTalkingSmsService $smsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = NotificationLog::latest();

        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $logs = $query->paginate(20);

        return response()->json([
            'data' => NotificationLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function testSms(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $request->validate([
            'phone' => ['required', 'string'],
            'message' => ['nullable', 'string', 'max:160'],
        ]);

        $phone = $request->input('phone');
        $text = $request->input('message') ?: 'Bariton Music Academy test SMS notification.';

        $log = $this->smsService->send(
            to: $phone,
            message: $text,
            notifiableType: get_class($request->user()),
            notifiableId: $request->user()->id,
            type: 'TestSmsNotification'
        );

        return response()->json([
            'message' => 'Test SMS dispatched.',
            'data' => new NotificationLogResource($log),
        ], Response::HTTP_CREATED);
    }
}
