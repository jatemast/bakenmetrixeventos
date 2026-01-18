<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PortalSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'persona_id',
        'whatsapp_number',
        'otp_code',
        'otp_expires_at',
        'session_token',
        'session_expires_at',
        'is_verified',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'session_expires_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    protected $hidden = [
        'otp_code',
        'session_token',
    ];

    /**
     * Generate a random 6-digit OTP code
     */
    public static function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a secure session token
     */
    public static function generateSessionToken(): string
    {
        return Str::random(64);
    }

    /**
     * Check if OTP is valid and not expired
     */
    public function isOtpValid(string $otp): bool
    {
        return $this->otp_code === $otp 
            && $this->otp_expires_at 
            && $this->otp_expires_at->isFuture();
    }

    /**
     * Check if session is valid and not expired
     */
    public function isSessionValid(): bool
    {
        return $this->is_verified 
            && $this->session_token 
            && $this->session_expires_at 
            && $this->session_expires_at->isFuture();
    }

    /**
     * Mark session as verified and generate session token
     */
    public function markVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'otp_code' => null,
            'otp_expires_at' => null,
            'session_token' => self::generateSessionToken(),
            'session_expires_at' => now()->addHours(24), // Session valid for 24 hours
        ]);
    }

    /**
     * Relationship to Persona
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Find active session by token
     */
    public static function findValidSession(string $token): ?self
    {
        return self::where('session_token', $token)
            ->where('is_verified', true)
            ->where('session_expires_at', '>', now())
            ->first();
    }

    /**
     * Clean up expired sessions
     */
    public static function cleanupExpired(): int
    {
        return self::where(function ($query) {
            $query->where('session_expires_at', '<', now())
                  ->orWhere(function ($q) {
                      $q->whereNull('session_token')
                        ->where('otp_expires_at', '<', now());
                  });
        })->delete();
    }
}
