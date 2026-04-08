<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSlot extends Model
{
    protected $fillable = [
        'event_id', 'start_time', 'end_time', 'capacity', 'booked_count', 'status'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    // Helper para saber si hay espacio
    public function hasCapacity(): bool
    {
        return $this->booked_count < $this->capacity;
    }
}
