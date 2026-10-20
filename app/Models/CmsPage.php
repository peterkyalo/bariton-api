<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CmsPage extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'uuid',
        'slug',
        'title',
        'category',
        'meta_title',
        'meta_description',
        'hero_badge',
        'hero_title',
        'hero_subtitle',
        'hero_cta_text',
        'hero_cta_link',
        'hero_image_url',
        'content',
        'sections',
        'is_published',
        'order',
    ];

    protected $casts = [
        'sections' => 'array',
        'is_published' => 'boolean',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CmsPage $page) {
            if (empty($page->uuid)) {
                $page->uuid = (string) Str::uuid();
            }
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'category', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
