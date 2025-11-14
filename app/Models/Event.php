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
        'qr_code_data',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
