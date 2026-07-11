<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncBatchLog extends Model
{
    protected $fillable = [
        'cuenta_id',
        'sync_device_id',
        'tipo',
        'batch_id',
        'accepted',
        'duplicates',
        'rejected',
        'duration_ms',
    ];
}
