<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use \App\Traits\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'theme',
        'requesting_dependency',
        'campaign_manager',
        'email',
        'whatsapp',
        'start_date',
        'end_date',
        'campaign_number',
        'number_of_events',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::deleting(function ($campaign) {
            $campaign->events()->delete();
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
