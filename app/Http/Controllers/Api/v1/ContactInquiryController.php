<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiryAcknowledgmentMail;
use App\Mail\NewContactInquiryMail;
use App\Models\ContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactInquiryController extends Controller
{
    /**
     * Public submission of contact form inquiry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $inquiry = ContactInquiry::create($validated);

        // 1. Dispatch email notification to admin@bmas.co.ke
        $adminEmail = env('CONTACT_NOTIFICATION_EMAIL', 'admin@bmas.co.ke');
        try {
            Mail::to($adminEmail)->send(new NewContactInquiryMail($inquiry));
        } catch (\Throwable $e) {
            Log::error('Failed to send contact inquiry notification to admin: '.$e->getMessage(), [
                'inquiry_id' => $inquiry->id,
                'exception' => $e,
            ]);
        }

        // 2. Dispatch polite auto-acknowledgment to sender
        try {
            Mail::to($inquiry->email)->send(new ContactInquiryAcknowledgmentMail($inquiry));
        } catch (\Throwable $e) {
            Log::warning('Failed to send contact inquiry acknowledgment to sender: '.$e->getMessage(), [
                'inquiry_id' => $inquiry->id,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Thank you for contacting Bariton Music Academy! Your message has been sent to our admissions team and an counselor will respond within 24 hours.',
            'data' => [
                'uuid' => $inquiry->uuid,
                'created_at' => $inquiry->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
