<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Course extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'description',
        'short_description',
        'thumbnail_path',
        'thumbnail_disk',
        'status',
        'bundle_discount_type',
        'bundle_discount_value',
        'requires_sequential_levels',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'bundle_discount_value' => 'integer',
            'requires_sequential_levels' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (empty($course->uuid)) {
                $course->uuid = (string) Str::uuid();
            }
            if (empty($course->slug)) {
                $course->slug = Str::slug($course->title);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'bundle_discount_type', 'bundle_discount_value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function exam(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Exam::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Total sum of individual module prices in KES minor units.
     */
    public function getTotalModulesPriceKesAttribute(): int
    {
        return (int) $this->modules->sum('price_kes');
    }

    /**
     * Calculated bundle price after applying course bundle discount.
     */
    public function getBundlePriceKesAttribute(): int
    {
        $sum = $this->total_modules_price_kes;

        if ($sum === 0) {
            return 0;
        }

        if ($this->bundle_discount_type === 'percent') {
            $discount = (int) round($sum * ($this->bundle_discount_value / 100));

            return max(0, $sum - $discount);
        }

        if ($this->bundle_discount_type === 'fixed_kes') {
            return max(0, $sum - (int) $this->bundle_discount_value);
        }

        return $sum;
    }

    /**
     * Total saving in KES minor units when buying the course bundle.
     */
    public function getBundleSavingsKesAttribute(): int
    {
        return max(0, $this->total_modules_price_kes - $this->bundle_price_kes);
    }
}
