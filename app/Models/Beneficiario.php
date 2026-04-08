<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficiario extends Model
{
    use HasFactory, SoftDeletes, \App\Traits\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'persona_id',
        'nombre',
        'tipo',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
}
