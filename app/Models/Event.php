<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'type',
        'title',
        'slug',
        'description',
        'venue',
        'start_date',
        'end_date',
        'capacity',
        'training_fee_kes',
        'training_fee_usd',
        'has_accommodation',
        'accommodation_fee_kes',
        'accommodation_fee_usd',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'capacity' => 'integer',
            'training_fee_kes' => 'integer',
            'training_fee_usd' => 'integer',
            'has_accommodation' => 'boolean',
            'accommodation_fee_kes' => 'integer',
            'accommodation_fee_usd' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->title);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'venue', 'capacity', 'training_fee_kes', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventBooking::class);
    }

    public function waitlists(): HasMany
    {
        return $this->hasMany(EventWaitlist::class);
    }

    /**
     * Count currently occupied seats:
     * - Status is 'confirmed' (paid and confirmed)
     * - OR Status is 'held' AND hold has not yet expired
     */
    public function getOccupiedSeatsCountAttribute(): int
    {
        return $this->bookings()
            ->where(function ($query) {
                $query->whereIn('status', ['confirmed', 'attended'])
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'held')
                            ->where('hold_expires_at', '>', now());
                    });
            })
            ->count();
    }

    /**
     * Remaining available physical seats.
     */
    public function getAvailableSeatsCountAttribute(): int
    {
        return max(0, $this->capacity - $this->occupied_seats_count);
    }

    /**
     * Check if the event is at full physical capacity.
     */
    public function isFull(): bool
    {
        return $this->available_seats_count <= 0;
    }
}
