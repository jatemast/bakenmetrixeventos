<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRepository extends Model
{
    protected $fillable = [
        'campaign_id',
        'event_id',
        'name',
        'description',
        'scope',
        'pdf_path',
        'rules_data',
        'faqs',
        'qr_logic',
    ];

    protected $casts = [
        'rules_data' => 'array',
        'faqs' => 'array',
        'qr_logic' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function getFaqs()
    {
        return $this->faqs ?? [];
    }

    public function getRules()
    {
        return $this->rules_data ?? [];
    }

    public function getQrLogic()
    {
        return $this->qr_logic ?? [];
    }
}
