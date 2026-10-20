<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssignmentSubmission extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'uuid',
        'assignment_id',
        'enrollment_id',
        'user_id',
        'attempt_number',
        'file_path',
        'original_filename',
        'file_size_bytes',
        'media_type',
        'duration_seconds',
        'student_notes',
        'status',
        'score_percent',
        'total_points_earned',
        'total_points_possible',
        'passed',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'file_size_bytes' => 'integer',
        'duration_seconds' => 'integer',
        'score_percent' => 'decimal:2',
        'total_points_earned' => 'integer',
        'total_points_possible' => 'integer',
        'passed' => 'boolean',
        'graded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AssignmentSubmission $sub) {
            if (empty($sub->uuid)) {
                $sub->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'score_percent', 'passed', 'graded_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function rubricScores(): HasMany
    {
        return $this->hasMany(SubmissionRubricScore::class);
    }

    public function isGraded(): bool
    {
        return $this->status === 'graded';
    }

    public function isPendingGrading(): bool
    {
        return in_array($this->status, ['submitted', 'grading'], true);
    }

    public function canResubmit(): bool
    {
        return $this->status === 'resubmission_required';
    }
}
