<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentNotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $defaultPreferences = [
            'event_reminders' => true,
            'session_reminders' => true,
            'assignment_graded' => true,
            'marketing' => false,
            // Mandatory preferences (read-only for students)
            'registration' => true,
            'payment_received' => true,
            'receipt_issued' => true,
            'refund_decision' => true,
        ];

        $currentPreferences = array_merge($defaultPreferences, $user->notification_preferences ?? []);

        return response()->json([
            'preferences' => $currentPreferences,
            'mandatory' => ['registration', 'payment_received', 'receipt_issued', 'refund_decision'],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_reminders' => 'sometimes|boolean',
            'session_reminders' => 'sometimes|boolean',
            'assignment_graded' => 'sometimes|boolean',
            'marketing' => 'sometimes|boolean',
        ]);

        $existing = $user->notification_preferences ?? [];
        $updated = array_merge($existing, $validated);

        // Security & payment messages are strictly mandatory
        $updated['registration'] = true;
        $updated['payment_received'] = true;
        $updated['receipt_issued'] = true;
        $updated['refund_decision'] = true;

        $user->update(['notification_preferences' => $updated]);

        return response()->json([
            'message' => 'Notification preferences updated successfully.',
            'preferences' => $updated,
        ]);
    }
}
