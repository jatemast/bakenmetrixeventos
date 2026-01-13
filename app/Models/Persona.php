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
        'region',
        'numero_celular',
        'numero_telefono',
        'is_leader',
        'referral_code',
        'loyalty_balance',
        'universe_type',
        'leader_id',
        'last_interacted_event_id',
        'last_interaction_at',
        'last_invited_event_id',
    ];

    protected $casts = [
        'is_leader' => 'boolean',
        'last_interaction_at' => 'datetime',
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

    public function leader()
    {
        return $this->belongsTo(Persona::class, 'leader_id');
    }

    public function guests()
    {
        return $this->hasMany(Persona::class, 'leader_id');
    }

    public function referrals()
    {
        return $this->hasMany(EventAttendee::class, 'referred_by');
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }

    public function lastInteractedEvent()
    {
        return $this->belongsTo(Event::class, 'last_interacted_event_id');
    }

    public function lastInvitedEvent()
    {
        return $this->belongsTo(Event::class, 'last_invited_event_id');
    }
}
