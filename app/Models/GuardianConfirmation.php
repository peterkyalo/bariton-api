<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuardianConfirmation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guardian_name',
        'guardian_email',
        'token_hash',
        'confirmed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function createForUser(User $user, string $name, string $email): array
    {
        $plainToken = Str::random(40);

        $confirmation = self::create([
            'user_id' => $user->id,
            'guardian_name' => $name,
            'guardian_email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        return [$confirmation, $plainToken];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
