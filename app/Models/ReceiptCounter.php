<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptCounter extends Model
{
    protected $fillable = [
        'key',
        'last_number',
        'prefix',
    ];

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
