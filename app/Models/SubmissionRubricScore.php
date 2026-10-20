<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionRubricScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_submission_id',
        'rubric_criterion_id',
        'rubric_level_id',
        'points_awarded',
        'criterion_feedback',
    ];

    protected $casts = [
        'points_awarded' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'assignment_submission_id');
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
