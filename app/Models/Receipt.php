<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'receipt_number',
        'order_id',
        'payment_id',
        'user_id',
        'amount_kes',
        'vat_kes',
        'currency',
        'pdf_path',
        'pdf_disk',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_kes' => 'integer',
            'vat_kes' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Receipt $receipt) {
            if (empty($receipt->uuid)) {
                $receipt->uuid = (string) Str::uuid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAmountKesFormattedAttribute(): string
    {
        return number_format($this->amount_kes / 100, 2);
    }

    public function getVatKesFormattedAttribute(): string
    {
        return number_format($this->vat_kes / 100, 2);
    }
}
