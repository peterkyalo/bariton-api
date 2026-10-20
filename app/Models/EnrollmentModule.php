<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'module_id',
        'amount_paid_kes',
        'unlocked_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid_kes' => 'integer',
            'unlocked_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
