<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TutoringSession extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'uuid',
        'student_tutoring_package_id',
        'student_id',
        'instructor_id',
        'course_id',
        'scheduled_start',
        'scheduled_end',
        'mode',
        'meeting_link',
        'location',
        'status',
        'cancellation_reason',
        'rescheduled_from_session_id',
        'notice_waived',
        'waiver_reason',
        'instructor_notes',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'notice_waived' => 'boolean',
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

    public function studentTutoringPackage(): BelongsTo
    {
        return $this->belongsTo(StudentTutoringPackage::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function rescheduledFromSession(): BelongsTo
    {
        return $this->belongsTo(TutoringSession::class, 'rescheduled_from_session_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
