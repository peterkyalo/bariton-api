<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventWaitlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'booking_type',
        'status',
        'offered_at',
        'offer_expires_at',
        'held_booking_id',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventWaitlist $waitlist) {
            if (empty($waitlist->uuid)) {
                $waitlist->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function heldBooking(): BelongsTo
    {
        return $this->belongsTo(EventBooking::class, 'held_booking_id');
    }
}
