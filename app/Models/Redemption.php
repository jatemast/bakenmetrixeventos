<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Redemption extends Model
{
    protected $fillable = [
        'voucher_code',
        'persona_id',
        'points_redeemed',
        'reward_description',
        'qr_code_path',
        'status',
        'redeemed_at',
        'validated_at',
        'validated_by',
        'expires_at',
        'metadata'
    ];

    protected $casts = [
        'points_redeemed' => 'decimal:2',
        'redeemed_at' => 'datetime',
        'validated_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the persona who redeemed points
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Get the user who validated the voucher
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Check if voucher is valid
     */
    public function isValid(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }
        
        if ($this->expires_at && Carbon::now()->isAfter($this->expires_at)) {
            $this->markAsExpired();
            return false;
        }
        
        return true;
    }

    /**
     * Validate the voucher
     */
    public function validate(int $userId): bool
    {
        if (!$this->isValid()) {
            return false;
        }
        
        $this->update([
            'status' => 'validated',
            'validated_at' => now(),
            'validated_by' => $userId
        ]);
        
        return true;
    }

    /**
     * Mark voucher as expired
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Cancel redemption and refund points
     */
    public function cancel(): void
    {
        if ($this->status === 'pending') {
            // Refund points
            $this->persona->increment('loyalty_balance', $this->points_redeemed);
            
            // Update status
            $this->update(['status' => 'cancelled']);
            
            // Create refund history
            BonusPointHistory::create([
                'persona_id' => $this->persona_id,
                'points_awarded' => $this->points_redeemed,
                'type' => 'refund',
                'description' => "Reembolso por cancelación de voucher {$this->voucher_code}"
            ]);
        }
    }

    /**
     * Generate unique voucher code
     */
    public static function generateVoucherCode(int $personaId): string
    {
        $prefix = 'VCHR';
        $personaPart = 'P' . $personaId;
        $randomPart = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        return "{$prefix}-{$personaPart}-{$randomPart}";
    }

    /**
     * Scope: Get pending redemptions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get validated redemptions
     */
    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    /**
     * Scope: Get non-expired redemptions
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
