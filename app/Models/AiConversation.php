<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversation extends Model
{
    protected $fillable = [
        'persona_id',
        'event_id',
        'session_id',
        'user_query',
        'ai_response',
        'context_source',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the persona associated with this conversation
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Get the event associated with this conversation
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Scope to get conversations for a specific event
     */
    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    /**
     * Scope to get conversations for a specific persona
     */
    public function scopeForPersona($query, int $personaId)
    {
        return $query->where('persona_id', $personaId);
    }

    /**
     * Scope to get recent conversations
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
