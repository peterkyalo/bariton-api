<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\DataExportRequest;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->only('name', 'phone', 'date_of_birth'));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Start two-factor authentication setup.
     */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $google2fa = new Google2FA;

        if (empty($user->two_factor_secret)) {
            $secret = $google2fa->generateSecretKey();
            $user->forceFill([
                'two_factor_secret' => encrypt($secret),
            ])->save();
        }

        return response()->json([
            'message' => 'Two-factor authentication setup initiated.',
        ]);
    }

    /**
     * Get two-factor QR code SVG.
     */
    public function twoFactorQrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->two_factor_secret)) {
            return response()->json(['message' => 'Two-factor authentication is not being set up.'], Response::HTTP_BAD_REQUEST);
        }

        $secret = decrypt($user->two_factor_secret);
        $company = config('app.name', 'Bariton Music Academy');
        $google2fa = new Google2FA;
        $qrCodeUrl = $google2fa->getQRCodeUrl($company, $user->email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'svg' => $svg,
            'url' => $qrCodeUrl,
            'secret' => $secret,
        ]);
    }

    public function twoFactorSecret(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->two_factor_secret)) {
            return response()->json(['secretKey' => null]);
        }

        return response()->json([
            'secretKey' => decrypt($user->two_factor_secret),
        ]);
    }

    /**
     * Confirm two-factor code and generate recovery codes.
     */
    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();
        if (empty($user->two_factor_secret)) {
            throw ValidationException::withMessages(['code' => ['Two factor setup has not been initiated.']]);
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($secret, $request->input('code'), 1)) {
            throw ValidationException::withMessages(['code' => ['The two-factor authentication code is invalid.']]);
        }

        $recoveryCodes = Collection::times(8, function () {
            return Str::random(10).'-'.Str::random(10);
        })->all();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        return response()->json([
            'message' => 'Two-factor authentication successfully enabled.',
            'recovery_codes' => $recoveryCodes,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * View two-factor recovery codes.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->two_factor_recovery_codes)) {
            return response()->json([]);
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];

        return response()->json($codes);
    }

    /**
     * Disable two-factor authentication.
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->requiresTwoFactor()) {
            return response()->json([
                'message' => 'Two-factor authentication cannot be disabled because it is strictly mandatory for your role.',
            ], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Two-factor authentication disabled.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Request GDPR / Kenya Data Protection Act personal data export.
     */
    public function requestDataExport(Request $request): JsonResponse
    {
        $user = $request->user();

        $exportData = [
            'exported_at' => now()->toIso8601String(),
            'platform' => 'Bariton Music Academy',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'status' => $user->status,
                'privacy_consent_at' => $user->privacy_consent_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'roles' => $user->getRoleNames(),
        ];

        $filename = 'exports/user-'.$user->id.'-'.now()->timestamp.'.json';
        Storage::disk('local')->put($filename, json_encode($exportData, JSON_PRETTY_PRINT));

        $export = DataExportRequest::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'file_path' => $filename,
            'expires_at' => now()->addDays(3),
        ]);

        return response()->json([
            'message' => 'Your personal data export is ready for download.',
            'data' => $export,
        ], Response::HTTP_CREATED);
    }

    public function dataExports(Request $request): JsonResponse
    {
        $exports = $request->user()->dataExportRequests()->latest()->get();

        return response()->json(['data' => $exports]);
    }

    public function downloadDataExport(Request $request, int $id): mixed
    {
        $export = $request->user()->dataExportRequests()->findOrFail($id);

        if (! Storage::disk('local')->exists($export->file_path)) {
            return response()->json(['message' => 'Export file no longer exists or has expired.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk('local')->download($export->file_path, 'bariton-user-export.json');
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $user = $request->user();
        $user->update([
            'deletion_requested_at' => now(),
            'deletion_reason' => $request->input('reason'),
        ]);

        return response()->json([
            'message' => 'Account deletion request submitted. An academy administrator will process your request in accordance with the Kenya Data Protection Act 2019.',
            'data' => new UserResource($user),
        ]);
    }

    public function cancelDeletion(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'deletion_requested_at' => null,
            'deletion_reason' => null,
        ]);

        return response()->json([
            'message' => 'Account deletion request has been cancelled.',
            'data' => new UserResource($user),
        ]);
    }
}
