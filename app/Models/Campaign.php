<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'theme',
        'objective',
        'target_citizen',
        'target_universes',
        'special_observations',
        'citizen_segmentation_file',
        'leader_segmentation_file',
        'militant_segmentation_file',
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
        'target_universes' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class, 'campaign_event');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
