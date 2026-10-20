<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExamAttemptAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'exam_attempt_id',
        'exam_question_id',
        'selected_option',
        'text_answer',
        'media_path',
        'media_disk',
        'media_mime',
        'is_auto_graded',
        'score_awarded',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'is_auto_graded' => 'boolean',
        'score_awarded' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExamAttemptAnswer $answer) {
            if (empty($answer->uuid)) {
                $answer->uuid = (string) Str::uuid();
            }
        });
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'exam_question_id');
    }

    public function rubricScores(): HasMany
    {
        return $this->hasMany(ExamRubricScore::class);
    }

    public function gradedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
