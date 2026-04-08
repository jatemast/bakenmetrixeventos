<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'event_id', 
        'event_slot_id', 
        'persona_id', 
        'target_id',
        'target_type',
        'qr_code_token', 
        'assigned_location', 
        'started_at',
        'completed_at',
        'service_duration_minutes',
        'status'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * El "sujeto" de la cita (puede ser Mascota, Tramite, Despensa, etc.)
     */
    public function target()
    {
        return $this->morphTo();
    }
}
