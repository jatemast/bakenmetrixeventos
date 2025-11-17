<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mascota extends Model
{
    protected $fillable = [
        'persona_id',
        'reino_animal',
        'edad',
        'nombre',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
}
