<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Persona extends Model
{
    protected $fillable = [
        'cedula',
        'region',
        'universe_group',
        'curp',
        'codigo_ciudadano',
        'clave_elector',
        'seccion',
        'vigencia',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'edad',
        'sexo',
        'email',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'municipio',
        'estado',
        'numero_celular',
        'numero_telefono',
        'tipo_sangre',
        'servicios',
        'tarifa',
        'categoria',
        'is_leader',
        'referral_code',
        'loyalty_balance',
        'universe_type',
        'leader_id',
        'last_interacted_event_id',
        'last_interaction_at',
        'last_invited_event_id',
        'last_invited_at',
        'region',
        'group_id',
        'tenant_id',
        'tags',
        'universes',
        'metadata',
        'notes',
        'geom',
        'location',
        'cdz_expires_at',
        'cdz_version',
    ];

    protected $casts = [
        'is_leader' => 'boolean',
        'tags' => 'array',
        'universes' => 'array',
        'metadata' => 'array',
        'cdz_expires_at' => 'datetime',
        'cdz_version' => 'integer',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Citizen Code Logic: CDZ-{unique}
            if (empty($model->codigo_ciudadano)) {
                $model->codigo_ciudadano = 'CDZ-' . strtoupper(Str::random(10));
                $model->cdz_expires_at = now()->addYear();
                $model->cdz_version = 1;
            }
        });
    }

    public function tags_directory()
    {
        return $this->belongsToMany(Tag::class, 'persona_tags', 'persona_id', 'tag_id');
    }

    /**
     * Universal beneficiaries for any event type (Fase 1.2)
     */
    public function beneficiarios()
    {
        return $this->hasMany(Beneficiario::class);
    }

    /**
     * Legacy mascotas for backward compatibility
     */
    public function mascotas()
    {
        return $this->hasMany(Mascota::class);
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_attendees')
                    ->withPivot('status', 'check_in_time', 'check_out_time')
                    ->withTimestamps();
    }

    public function attendees()
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function bonusPointsHistory()
    {
        return $this->hasMany(BonusPointHistory::class);
    }
}
