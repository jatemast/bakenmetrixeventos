<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $fillable = [
        'persona_id',
        'event_id',
        'status',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
