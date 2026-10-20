<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContactInquiry extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'admin_notes',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ContactInquiry $inquiry) {
            if (empty($inquiry->uuid)) {
                $inquiry->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'subject', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
