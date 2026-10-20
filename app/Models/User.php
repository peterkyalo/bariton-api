<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'date_of_birth',
        'status',
        'privacy_consent_at',
        'privacy_policy_version',
        'locked_until',
        'failed_login_count',
        'deletion_requested_at',
        'deletion_reason',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'privacy_consent_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'failed_login_count' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'status', 'locked_until'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function guardianConfirmation(): HasOne
    {
        return $this->hasOne(GuardianConfirmation::class)->latestOfMany();
    }

    public function guardianConfirmations(): HasMany
    {
        return $this->hasMany(GuardianConfirmation::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'notifiable_id')
            ->where('notifiable_type', self::class);
    }

    public function dataExportRequests(): HasMany
    {
        return $this->hasMany(DataExportRequest::class);
    }

    public function isMinor(): bool
    {
        if (! $this->date_of_birth) {
            return false;
        }

        return $this->date_of_birth->diffInYears(now()) < 18;
    }

    public function isPendingGuardian(): bool
    {
        return $this->status === 'pending_guardian';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Roles that strictly require TOTP two-factor authentication.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(['admin', 'finance_manager', 'finance_staff']);
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return ! empty($this->two_factor_secret) && ! empty($this->two_factor_confirmed_at);
    }

    /**
     * Reset login failure attempts on successful authentication.
     */
    public function resetLoginAttempts(): void
    {
        $this->update([
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Record a failed login attempt and lock for 15 minutes after 5 failures.
     */
    public function recordFailedLogin(): void
    {
        $attempts = $this->failed_login_count + 1;
        $updates = ['failed_login_count' => $attempts];

        if ($attempts >= 5) {
            $updates['locked_until'] = now()->addMinutes(15);
        }

        $this->update($updates);
    }

    public function coursesTeaching(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
