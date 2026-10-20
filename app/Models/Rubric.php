<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Rubric extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'uuid',
        'instructor_id',
        'title',
        'description',
        'total_points',
        'is_template',
    ];

    protected $casts = [
        'total_points' => 'integer',
        'is_template' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Rubric $rubric) {
            if (empty($rubric->uuid)) {
                $rubric->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'total_points', 'is_template'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
