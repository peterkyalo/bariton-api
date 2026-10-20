<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExamRubricScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'exam_attempt_answer_id',
        'rubric_criterion_id',
        'rubric_level_id',
        'points_awarded',
        'comments',
    ];

    protected $casts = [
        'points_awarded' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExamRubricScore $score) {
            if (empty($score->uuid)) {
                $score->uuid = (string) Str::uuid();
            }
        });
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(ExamAttemptAnswer::class, 'exam_attempt_answer_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class, 'rubric_criterion_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(RubricLevel::class, 'rubric_level_id');
    }
}
