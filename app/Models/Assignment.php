<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Assignment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'uuid',
        'module_id',
        'rubric_id',
        'title',
        'slug',
        'instructions',
        'order',
        'submission_type',
        'accepted_formats',
        'max_file_size_mb',
        'is_published',
    ];

    protected $casts = [
        'accepted_formats' => 'array',
        'max_file_size_mb' => 'integer',
        'is_published' => 'boolean',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Assignment $assignment) {
            if (empty($assignment->uuid)) {
                $assignment->uuid = (string) Str::uuid();
            }
            if (empty($assignment->slug)) {
                $assignment->slug = Str::slug($assignment->title);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'module_id', 'submission_type', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function latestSubmissionFor(User $user, Enrollment $enrollment): ?AssignmentSubmission
    {
        return $this->submissions()
            ->where('user_id', $user->id)
            ->where('enrollment_id', $enrollment->id)
            ->latest('attempt_number')
            ->first();
    }
}
