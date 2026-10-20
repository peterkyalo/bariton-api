<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EventBooking extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'order_id',
        'booking_type',
        'training_fee_kes',
        'accommodation_fee_kes',
        'total_fee_kes',
        'status',
        'hold_type',
        'hold_expires_at',
        'held_by',
        'confirmed_at',
        'checked_in_at',
        'checked_in_by',
        'unseated_at',
        'unseated_reason',
    ];

    protected function casts(): array
    {
        return [
            'training_fee_kes' => 'integer',
            'accommodation_fee_kes' => 'integer',
            'total_fee_kes' => 'integer',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'unseated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventBooking $booking) {
            if (empty($booking->uuid)) {
                $booking->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'hold_expires_at', 'confirmed_at', 'checked_in_at', 'unseated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function refundRequest(): HasOne
    {
        return $this->hasOne(RefundRequest::class);
    }

    /**
     * Check if seat hold is currently active.
     */
    public function isHoldActive(): bool
    {
        return $this->status === 'held' && $this->hold_expires_at && now()->lessThanOrEqualTo($this->hold_expires_at);
    }

    /**
     * Check if seat hold has expired.
     */
    public function isHoldExpired(): bool
    {
        return $this->status === 'held' && $this->hold_expires_at && now()->greaterThan($this->hold_expires_at);
    }
}
