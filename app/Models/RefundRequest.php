<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RefundRequest extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'uuid',
        'order_id',
        'order_item_id',
        'user_id',
        'amount_kes',
        'amount_usd_cents',
        'currency',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'refund_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount_kes' => 'integer',
            'amount_usd_cents' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RefundRequest $refund) {
            if (empty($refund->uuid)) {
                $refund->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reviewed_by', 'reviewed_at', 'refund_reference', 'amount_kes'])
            ->logOnlyDirty();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(CreditNote::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getAmountKesFormattedAttribute(): string
    {
        return number_format($this->amount_kes / 100, 2);
    }

    public function getAmountUsdFormattedAttribute(): string
    {
        return number_format($this->amount_usd_cents / 100, 2);
    }
}
