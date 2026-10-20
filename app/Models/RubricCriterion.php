<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricCriterion extends Model
{
    use HasFactory;

    protected $table = 'rubric_criteria';

    protected $fillable = [
        'rubric_id',
        'title',
        'description',
        'max_points',
        'order',
    ];

    protected $casts = [
        'max_points' => 'integer',
        'order' => 'integer',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(RubricLevel::class)->orderBy('order');
    }
}
