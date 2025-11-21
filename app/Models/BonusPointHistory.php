<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusPointHistory extends Model
{
    protected $table = 'bonus_points_history'; // Especificar el nombre de la tabla
    protected $fillable = [
        'persona_id',
        'event_id',
        'points_awarded',
        'type',
        'description',
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
