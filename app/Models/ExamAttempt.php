<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExamAttempt extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'uuid',
        'exam_id',
        'user_id',
        'enrollment_id',
        'attempt_number',
        'status',
        'started_at',
        'expires_at',
        'recording_stopped_at',
        'upload_grace_period_expires_at',
        'submitted_at',
        'total_score',
        'max_score',
        'score_percent',
        'passed',
        'overall_feedback',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'recording_stopped_at' => 'datetime',
        'upload_grace_period_expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'total_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'score_percent' => 'decimal:2',
        'passed' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExamAttempt $attempt) {
            if (empty($attempt->uuid)) {
                $attempt->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['exam_id', 'user_id', 'attempt_number', 'status', 'score_percent', 'passed'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAttemptAnswer::class);
    }

    public function gradedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
