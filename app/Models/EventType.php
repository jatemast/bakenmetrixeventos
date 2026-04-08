<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventType extends Model
{
    use \App\Traits\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'icon',
        'default_form_schema',
        'default_slot_config',
        'default_qr_config',
        'default_points_config',
        'requires_appointment',
        'has_beneficiaries',
        'beneficiary_label'
    ];

    protected $casts = [
        'default_form_schema' => 'array',
        'default_slot_config' => 'array',
        'default_qr_config' => 'array',
        'default_points_config' => 'array',
        'requires_appointment' => 'boolean',
        'has_beneficiaries' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
