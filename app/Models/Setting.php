<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Setting extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_public' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value'])
            ->logOnlyDirty();
    }

    /**
     * Get typed value according to the type column.
     */
    public function getTypedValueAttribute(): mixed
    {
        $raw = $this->value;

        return match ($this->type) {
            'integer' => (int) $raw,
            'float' => (float) $raw,
            'boolean' => (bool) $raw,
            'json' => is_array($raw) ? $raw : json_decode((string) $raw, true),
            default => (string) $raw,
        };
    }
}
