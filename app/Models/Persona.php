<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Persona extends Model
{
    use \App\Traits\BelongsToTenant;

    // ── Universes (Arquitectura Senior) ────────────────────────────
    public const UNIVERSE_U1 = 'U1'; // Ya registrado en el CRM
    public const UNIVERSE_U2 = 'U2'; // No registrado aún (transitorio → pasa a U1 al registrarse)
    public const UNIVERSE_U3 = 'U3'; // Líder (QR específico de líder)
    public const UNIVERSE_U4 = 'U4'; // Militante (QR específico de militante)

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
        'sub_type',
        'last_interacted_event_id',
        'last_interaction_at',
        'last_invited_event_id',
        'last_invited_at',
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

    protected $appends = ['relational_tags'];

    protected $casts = [
        'is_leader' => 'boolean',
        'tags' => 'array',
        'universes' => 'array',
        'metadata' => 'array',
        'cdz_expires_at' => 'datetime',
        'cdz_version' => 'integer',
        'location' => 'array',
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

            // Default Universe Assignment
            if (empty($model->universe_type)) {
                $model->universe_type = self::UNIVERSE_U1;
            }

            // --- REFERRAL CODE FOR LEADERS ---
            if ($model->universe_type === self::UNIVERSE_U3 && empty($model->referral_code)) {
                $model->referral_code = 'LDR-' . strtoupper(Str::random(6));
            }

        // Auto-tagging based on Universe
            $model->syncUniverseTags();
        });

        static::created(function ($model) {
            $model->syncRelationalTags();
        });

        static::updating(function ($model) {
            if ($model->isDirty('universe_type')) {
                $model->syncUniverseTags();
            }
        });

        static::updated(function ($model) {
            if ($model->isDirty('tags')) {
                $model->syncRelationalTags();
            }
        });
    }

    /**
     * Promote a Prospect (U2) to Registered CRM (U1)
     */
    public function promoteToU1(): bool
    {
        if ($this->universe_type === self::UNIVERSE_U2) {
            return $this->update([
                'universe_type' => self::UNIVERSE_U1,
                'metadata' => array_merge($this->metadata ?? [], [
                    'promoted_at' => now()->toDateTimeString(),
                    'promotion_reason' => 'registration_completed'
                ])
            ]);
        }
        return false;
    }

    /**
     * Automatic Identification Tags based on current status (SaaS Tags)
     */
    public function syncUniverseTags(): void
    {
        $currentTags = $this->tags ?? [];
        $universeMapping = [
            self::UNIVERSE_U1 => 'CITIZEN_REG',
            self::UNIVERSE_U2 => 'PROSPECT_NEW',
            self::UNIVERSE_U3 => 'LEADER_AUTH',
            self::UNIVERSE_U4 => 'MILITANT_VAL',
        ];

        // Remove old universe tags
        $currentTags = array_diff($currentTags, array_values($universeMapping));

        // Add new universe tag
        if (isset($universeMapping[$this->universe_type])) {
            $currentTags[] = $universeMapping[$this->universe_type];
        }

        // --- AUTOMATIC AGE-BASED TAGGING ---
        if ($this->edad > 0) {
            if ($this->edad >= 18 && $this->edad < 30) {
                $currentTags[] = 'JOVEN (>18)';
            } elseif ($this->edad >= 30 && $this->edad < 60) {
                $currentTags[] = 'ADULTO';
            } elseif ($this->edad >= 60) {
                $currentTags[] = 'ADULTO MAYOR';
            } else {
                $currentTags[] = 'MENOR DE EDAD';
            }
        }

        $this->tags = array_values(array_unique($currentTags));
        
        // Also ensure universes array contains the type for legacy filter support
        $this->universes = [$this->universe_type];
    }

    public function tags(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Sync the relational tags table with the JSON 'tags' column
     * This ensures no redundancy and centralizes the tagging system.
     */
    public function syncRelationalTags(): void
    {
        $tagNames = $this->tags ?? [];
        if (empty($tagNames)) {
            $this->tags()->detach();
            return;
        }

        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = Tag::firstOrCreate(
                ['name' => $name],
                ['slug' => \Illuminate\Support\Str::slug($name), 'type' => 'general']
            );
            $tagIds[] = $tag->id;
        }

        $this->tags()->sync($tagIds);
    }

    public function getRelationalTagsAttribute()
    {
        return $this->tags()->get();
    }

    public function beneficiarios()
    {
        return $this->hasMany(Beneficiario::class);
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

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class, 'persona_id');
    }

    public function leader()
    {
        return $this->belongsTo(Persona::class, 'leader_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Persona::class, 'leader_id');
    }
}
