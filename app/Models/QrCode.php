<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrCode extends Model
{
    protected $fillable = [
        'campaign_id',
        'event_id',
        'type',
        'code',
        'persona_id',
        'leader_id',
        'expires_at',
        'is_active',
        'scan_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function leader()
    {
        return $this->belongsTo(Persona::class, 'leader_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }
        
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        
        return true;
    }

    public function incrementScan()
    {
        $this->increment('scan_count');
    }
}
