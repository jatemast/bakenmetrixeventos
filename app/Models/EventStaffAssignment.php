<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCuenta;
use Illuminate\Database\Eloquent\Model;

class EventStaffAssignment extends Model
{
    use BelongsToCuenta;

    protected $fillable = [
        'cuenta_id',
        'event_id',
        'user_id',
        'es_entrega',
    ];

    protected $casts = [
        'es_entrega' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
