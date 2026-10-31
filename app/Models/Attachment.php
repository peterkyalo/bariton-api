<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A learning material (YouTube video, PDF, audio or image) attached to a course or module.
 */
class Attachment extends Model
{
    public const KINDS = ['youtube', 'pdf', 'audio', 'image'];

    protected $fillable = [
        'uuid',
        'attachable_type',
        'attachable_id',
        'kind',
        'title',
        'youtube_video_id',
        'file_path',
        'file_disk',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Attachment $attachment) {
            if (empty($attachment->uuid)) {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
