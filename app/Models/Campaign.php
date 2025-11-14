<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'theme',
        'target_citizen',
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
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
