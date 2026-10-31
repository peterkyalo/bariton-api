<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
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
        'image_path',
        'image_disk',
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

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('order');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('order');
    }

    /**
     * Public URL of the module image, when one has been uploaded to the public disk or set to an external URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        if ($this->image_disk !== 'public') {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
