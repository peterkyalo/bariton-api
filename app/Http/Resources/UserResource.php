<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'status' => $this->status,
            'email_verified' => $this->hasVerifiedEmail(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'two_factor_enabled' => $this->hasConfirmedTwoFactor(),
            'two_factor_required' => $this->requiresTwoFactor(),
            'is_minor' => $this->isMinor(),
            'locked_until' => $this->locked_until?->toISOString(),
            'failed_login_count' => $this->failed_login_count,
            'privacy_consent_at' => $this->privacy_consent_at?->toISOString(),
            'privacy_policy_version' => $this->privacy_policy_version,
            'deletion_requested_at' => $this->deletion_requested_at?->toISOString(),
            'deletion_reason' => $this->deletion_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
