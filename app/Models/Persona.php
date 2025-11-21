<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $fillable = [
        'cedula',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'edad',
        'sexo',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'municipio',
        'estado',
        'numero_celular',
        'numero_telefono',
        'is_leader',
        'referral_code',
        'bonus_points',
    ];

    protected $casts = [
        'is_leader' => 'boolean',
    ];

    public function mascotas()
    {
        return $this->hasMany(Mascota::class);
    }

    public function eventsAttended()
    {
        return $this->hasMany(EventAttendee::class, 'persona_id');
    }

    public function referredAttendees()
    {
        return $this->hasMany(EventAttendee::class, 'leader_id');
    }

    public function bonusPointsHistory()
    {
        return $this->hasMany(BonusPointHistory::class, 'persona_id');
    }
}
