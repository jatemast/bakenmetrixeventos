<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAttendee extends Model
{
    protected $fillable = [
        'event_id',
        'persona_id',
        'leader_id',
        'checkin_at',
        'checkout_at',
    ];

    protected $casts = [
        'checkin_at' => 'datetime',
        'checkout_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function leader()
    {
        return $this->belongsTo(Persona::class, 'leader_id');
    }
}
