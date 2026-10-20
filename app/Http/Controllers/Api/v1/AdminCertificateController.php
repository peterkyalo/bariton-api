<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCertificateController extends Controller
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    /**
     * List all issued, revoked, and replaced certificates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Certificate::with(['user', 'course'])->orderByDesc('issued_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('certificate_code', 'like', "%{$search}%")
                    ->orWhere('holder_name', 'like', "%{$search}%")
                    ->orWhere('course_title', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $certificates = $query->paginate(20)->through(function ($cert) {
            return [
                'uuid' => $cert->uuid,
                'certificate_code' => $cert->certificate_code,
                'holder_name' => $cert->holder_name,
                'course_title' => $cert->course_title,
                'path_type' => $cert->path_type,
                'status' => $cert->status,
                'issued_at' => $cert->issued_at?->toIso8601String(),
                'revoked_at' => $cert->revoked_at?->toIso8601String(),
                'revocation_reason' => $cert->revocation_reason,
                'reissue_replaced_by_code' => $cert->reissue_replaced_by_code,
                'reissue_replaces_code' => $cert->reissue_replaces_code,
                'user' => [
                    'name' => $cert->user->name,
                    'email' => $cert->user->email,
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $certificates,
        ]);
    }

    /**
     * Revoke an issued certificate with mandatory reason.
     */
    public function revoke(string $uuid, Request $request): JsonResponse
    {
        $admin = $request->user();
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $revokedCert = $this->certificateService->revoke(
            $certificate,
            $admin,
            $request->input('reason')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Certificate revoked successfully.',
            'data' => [
                'uuid' => $revokedCert->uuid,
                'certificate_code' => $revokedCert->certificate_code,
                'status' => $revokedCert->status,
                'revoked_at' => $revokedCert->revoked_at?->toIso8601String(),
                'revocation_reason' => $revokedCert->revocation_reason,
            ],
        ]);
    }

    /**
     * Reissue a certificate after a holder name correction.
     */
    public function reissue(string $uuid, Request $request): JsonResponse
    {
        $admin = $request->user();
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'new_holder_name' => ['required', 'string', 'min:2', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $newCert = $this->certificateService->reissue(
            $certificate,
            $admin,
            $request->input('new_holder_name'),
            $request->input('reason')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Certificate reissued with corrected holder name. Old certificate marked as replaced.',
            'data' => [
                'new_certificate' => [
                    'uuid' => $newCert->uuid,
                    'certificate_code' => $newCert->certificate_code,
                    'holder_name' => $newCert->holder_name,
                    'replaces_code' => $newCert->reissue_replaces_code,
                    'status' => $newCert->status,
                ],
                'old_certificate' => [
                    'certificate_code' => $certificate->certificate_code,
                    'status' => 'replaced',
                    'replaced_by_code' => $newCert->certificate_code,
                ],
            ],
        ]);
    }
}
