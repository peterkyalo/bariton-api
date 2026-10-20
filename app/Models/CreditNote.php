<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CreditNote extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'uuid',
        'credit_note_number',
        'refund_request_id',
        'order_id',
        'user_id',
        'amount_kes',
        'vat_kes',
        'currency',
        'pdf_path',
        'pdf_disk',
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
        static::creating(function (CreditNote $creditNote) {
            if (empty($creditNote->uuid)) {
                $creditNote->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['credit_note_number', 'amount_kes', 'vat_kes'])
            ->logOnlyDirty();
    }

    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
