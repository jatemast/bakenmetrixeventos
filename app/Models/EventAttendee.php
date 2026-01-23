<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAttendee extends Model
{
    protected $fillable = [
        'event_id',
        'persona_id',
        'leader_id',
        'registered_at',
        'registration_qr_code',
        'checkin_at',
        'checkin_qr_code',
        'checkout_at',
        'checkout_qr_code',
        'referred_by',
        'referral_qr_code',
        'attendance_status',
        'entry_timestamp',
        'exit_timestamp',
        'entry_qr_id',
        'exit_qr_id',
        'last_qr_scan_type',
        'last_qr_scan_at',
        'attendance_duration_minutes',
        'points_earned',
        'points_distributed',
        'exit_time',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'checkin_at' => 'datetime',
        'checkout_at' => 'datetime',
        'entry_timestamp' => 'datetime',
        'exit_timestamp' => 'datetime',
        'last_qr_scan_at' => 'datetime',
        'points_distributed' => 'boolean',
    ];

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

    public function referrer()
    {
        return $this->belongsTo(Persona::class, 'referred_by');
    }
}
