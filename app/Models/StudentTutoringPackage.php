<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentTutoringPackage extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'uuid',
        'user_id',
        'order_id',
        'tutoring_package_id',
        'instructor_id',
        'course_id',
        'total_credits',
        'used_credits',
        'remaining_credits',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'total_credits' => 'integer',
        'used_credits' => 'integer',
        'remaining_credits' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tutoringPackage(): BelongsTo
    {
        return $this->belongsTo(TutoringPackage::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function tutoringSessions(): HasMany
    {
        return $this->hasMany(TutoringSession::class);
    }

    public function hasAvailableCredits(): bool
    {
        return $this->status === 'active' && $this->remaining_credits > 0 && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->greaterThan($this->expires_at);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
