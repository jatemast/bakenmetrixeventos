<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'campaign_id',
        'detail',
        'date',
        'responsible',
        'email',
        'whatsapp',
        'dynamic',
        'ai_agent_info_file',
        'street',
        'number',
        'neighborhood',
        'postal_code',
        'municipality',
        'state',
        'country',
        'checkin_code',
        'checkout_code',
        'bonus_points_for_attendee',
        'bonus_points_for_leader',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
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
