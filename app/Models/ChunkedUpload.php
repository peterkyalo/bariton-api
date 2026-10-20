<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChunkedUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'chunk_size_bytes',
        'total_chunks',
        'chunks_completed',
        'status',
        'storage_disk',
        'temp_directory',
        'final_path',
        'expires_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'chunk_size_bytes' => 'integer',
        'total_chunks' => 'integer',
        'chunks_completed' => 'array',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChunkedUpload $upload) {
            if (empty($upload->uuid)) {
                $upload->uuid = (string) Str::uuid();
            }
            if (empty($upload->chunks_completed)) {
                $upload->chunks_completed = [];
            }
            if (empty($upload->temp_directory)) {
                $upload->temp_directory = 'chunks/'.$upload->uuid;
            }
            if (empty($upload->expires_at)) {
                $upload->expires_at = now()->addHours(24);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasChunk(int $index): bool
    {
        return in_array($index, $this->chunks_completed ?? [], true);
    }

    public function recordChunk(int $index): void
    {
        $chunks = $this->chunks_completed ?? [];
        if (! in_array($index, $chunks, true)) {
            $chunks[] = $index;
            sort($chunks);
            $this->chunks_completed = $chunks;
            $this->save();
        }
    }

    public function isComplete(): bool
    {
        return count($this->chunks_completed ?? []) >= $this->total_chunks;
    }
}
