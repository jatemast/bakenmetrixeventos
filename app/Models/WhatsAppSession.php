<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSession extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'session_id',
        'persona_id',
        'phone_number',
        'current_event_id',
        'conversation_state',
        'context_data',
        'last_message_at',
        'expires_at',
    ];

    protected $casts = [
        'context_data' => 'array',
        'last_message_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the persona associated with this session
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Get the current event associated with this session
     */
    public function currentEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'current_event_id');
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Extend session expiration
     */
    public function extend(int $hours = 24): void
    {
        $this->update([
            'expires_at' => now()->addHours($hours),
            'last_message_at' => now()
        ]);
    }

    /**
     * Scope to get active sessions only
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired sessions
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}
