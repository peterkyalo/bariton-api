<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Module extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'course_id',
        'title',
        'slug',
        'description',
        'level',
        'order',
        'price_kes',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'price_kes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Module $module) {
            if (empty($module->uuid)) {
                $module->uuid = (string) Str::uuid();
            }
            if (empty($module->slug)) {
                $module->slug = Str::slug($module->title);
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function enrollmentModules(): HasMany
    {
        return $this->hasMany(EnrollmentModule::class);
    }
}
