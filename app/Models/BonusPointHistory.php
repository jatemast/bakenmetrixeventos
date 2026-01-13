<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusPointHistory extends Model
{
    protected $table = 'bonus_points_history';
    
    protected $fillable = [
        'persona_id',
        'group_id',
        'event_id',
        'points',
        'points_awarded', // Alias for backward compatibility
        'type',
        'reason',
        'description',
        'metadata',
    ];

    protected $casts = [
        'points' => 'integer',
        'points_awarded' => 'integer',
        'metadata' => 'array',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Accessor for backward compatibility
     */
    public function getPointsAttribute($value)
    {
        return $value ?? $this->attributes['points_awarded'] ?? 0;
    }
}
