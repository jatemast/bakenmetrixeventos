<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SyncDevice extends Model
{
    protected $fillable = [
        'cuenta_id',
        'label',
        'token_hash',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function issue(int $cuentaId, string $label): array
    {
        $plainToken = Str::random(64);

        $device = static::create([
            'cuenta_id' => $cuentaId,
            'label' => $label,
            'token_hash' => static::hashToken($plainToken),
        ]);

        return [$device, $plainToken];
    }

    public function touchLastSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
