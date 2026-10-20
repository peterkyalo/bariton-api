<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'status' => 'success',
            'message' => 'Thank you for contacting Bariton Music Academy! An admissions counselor will respond within 24 hours.',
            'data' => [
                'uuid' => $inquiry->uuid,
                'created_at' => $inquiry->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
