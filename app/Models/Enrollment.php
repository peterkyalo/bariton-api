<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'course_id',
        'path_type',
        'status',
        'progress_percent',
        'assignment_pass_mark_percent',
        'exam_pass_mark_percent',
        'exam_attempt_limit',
        'exam_wait_time_hours',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'decimal:2',
            'assignment_pass_mark_percent' => 'integer',
            'exam_pass_mark_percent' => 'integer',
            'exam_attempt_limit' => 'integer',
            'exam_wait_time_hours' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Enrollment $enrollment) {
            if (empty($enrollment->uuid)) {
                $enrollment->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollmentModules(): HasMany
    {
        return $this->hasMany(EnrollmentModule::class);
    }

    public function unlockedModules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'enrollment_modules')
            ->withPivot('amount_paid_kes', 'unlocked_at', 'completed_at')
            ->withTimestamps();
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    public function totalPaidForModulesKes(): int
    {
        return (int) $this->enrollmentModules()->sum('amount_paid_kes');
    }
}
