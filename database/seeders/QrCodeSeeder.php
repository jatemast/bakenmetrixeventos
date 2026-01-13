<?php

namespace Database\Seeders;

use App\Models\QrCode;
use Illuminate\Database\Seeder;

class QrCodeSeeder extends Seeder
{
    public function run(): void
    {
        QrCode::create([
            'campaign_id' => 1,
            'event_id' => 1,
            'type' => 'QR1',
            'code' => 'QR123456',
            'persona_id' => 1,
            'is_active' => false,
            'scan_count' => 1,
        ]);

        QrCode::create([
            'campaign_id' => 2,
            'event_id' => 2,
            'type' => 'QR2',
            'code' => 'QR789012',
            'persona_id' => 2,
            'leader_id' => 2,
            'is_active' => true,
            'scan_count' => 0,
        ]);
    }
}