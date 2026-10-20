<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function __construct(
        protected NotificationTemplateService $templateService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = NotificationTemplate::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        return response()->json([
            'data' => $query->orderBy('id')->get(),
        ]);
    }

    public function show(string $key): JsonResponse
    {
        $template = NotificationTemplate::where('key', $key)->firstOrFail();

        return response()->json([
            'template' => $template,
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $template = NotificationTemplate::where('key', $key)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'channel' => 'sometimes|in:email,sms,both',
            'email_subject' => 'nullable|string|max:255',
            'email_body' => 'nullable|string',
            'sms_body' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'message' => 'Notification template updated successfully.',
            'template' => $template,
        ]);
    }

    public function testSend(Request $request, string $key): JsonResponse
    {
        $template = NotificationTemplate::where('key', $key)->firstOrFail();
        $user = $request->user();

        $sampleVariables = [
            'student_name' => $user->name,
            'amount_formatted' => 'KES 15,000',
            'payment_method' => 'M-Pesa STK Push',
            'payment_reference' => 'TEST-PAY-'.strtoupper(bin2hex(random_bytes(4))),
            'order_reference' => 'ORD-TEST-999',
            'receipt_number' => 'BMAS-2026-TEST',
            'vat_amount' => 'KES 2,068.97',
            'decision_status' => 'approved',
            'review_notes' => 'Medical certificate verified by admin.',
            'credit_note_number' => 'CN-TEST-001',
            'assignment_title' => 'Grade 3 Vocal Arpeggio Demonstration',
            'instructor_name' => 'Dr. Catherine Mutua',
            'score' => 92,
            'max_score' => 100,
            'percentage' => 92,
            'feedback' => 'Exceptional pitch stability and vocal timbre.',
            'course_title' => 'Classical Voice & Bel Canto Mastery',
            'certificate_number' => 'BMAS-CERT-TEST-2026',
            'verification_code' => 'BMAS-V-TEST99',
            'verification_url' => config('app.url').'/verify/BMAS-V-TEST99',
            'event_title' => 'Nairobi Contemporary Jazz Bootcamp',
            'venue' => 'Bariton Main Concert Hall, Nairobi',
            'start_datetime' => now()->addDays(3)->format('Y-m-d H:i').' EAT',
            'end_datetime' => now()->addDays(5)->format('Y-m-d H:i').' EAT',
            'accommodation_status' => 'Executive Campus Suite',
            'session_datetime' => now()->addDay()->format('Y-m-d H:i').' EAT',
            'session_mode' => 'Zoom Virtual Studio',
            'meeting_info' => 'https://zoom.us/j/9876543210 (Passcode: 123456)',
        ];

        $result = $this->templateService->send($key, $user, $sampleVariables);

        return response()->json([
            'message' => 'Test notification processed.',
            'result' => $result,
        ]);
    }
}
