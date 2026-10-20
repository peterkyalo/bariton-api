<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;

class CertificateVerificationController extends Controller
{
    /**
     * Publicly verify a certificate by unique code.
     * Rate limited and reveals strictly no personal data except the frozen holder name.
     */
    public function verify(string $code): JsonResponse
    {
        $code = trim($code);

        $certificate = Certificate::where('certificate_code', $code)->first();

        if (! $certificate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate not found. The verification code is invalid.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'certificate_code' => $certificate->certificate_code,
                'holder_name' => $certificate->holder_name,
                'course_title' => $certificate->course_title,
                'path_type' => $certificate->path_type,
                'is_validation_path' => $certificate->path_type === 'validation',
                'certificate_type_title' => $certificate->path_type === 'validation' ? 'Certificate of Skills Validation' : 'Certificate of Mastery',
                'assessment_note' => $certificate->path_type === 'validation' ? 'The holder completed assessment examination only.' : 'The holder fulfilled all instructional curriculum requirements and examinations.',
                'status' => $certificate->status, // 'issued', 'revoked', 'replaced'
                'issued_at' => $certificate->issued_at?->toIso8601String(),
                'revoked_at' => $certificate->revoked_at?->toIso8601String(),
                'revocation_reason' => $certificate->revocation_reason,
                'replaced_by_code' => $certificate->reissue_replaced_by_code,
                'replaces_code' => $certificate->reissue_replaces_code,
            ],
        ]);
    }
}
