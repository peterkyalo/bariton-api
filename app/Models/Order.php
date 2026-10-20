<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'order_number',
        'user_id',
        'status',
        'total_kes',
        'vat_kes',
        'vat_rate_percent',
        'exchange_rate_used',
        'total_usd_cents',
        'currency',
        'payment_method',
        'paid_at',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_kes' => 'integer',
            'vat_kes' => 'integer',
            'vat_rate_percent' => 'decimal:2',
            'exchange_rate_used' => 'float',
            'total_usd_cents' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
            if (empty($order->order_number)) {
                $order->order_number = 'BMAS-ORD-'.now()->format('Ym').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_method', 'paid_at', 'total_kes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'successful')->latestOfMany();
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class)->latestOfMany();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->isPending() && $this->expires_at->isPast());
    }

    public function getTotalKesFormattedAttribute(): string
    {
        return number_format($this->total_kes / 100, 2);
    }

    public function getTotalUsdFormattedAttribute(): string
    {
        return number_format($this->total_usd_cents / 100, 2);
    }

    public function getVatKesFormattedAttribute(): string
    {
        return number_format($this->vat_kes / 100, 2);
    }
}
