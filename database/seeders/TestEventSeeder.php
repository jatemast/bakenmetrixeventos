<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Event;
use App\Models\EventSlot;
use Illuminate\Database\Seeder;

class TestEventSeeder extends Seeder
{
    public function run(): void
    {
        $campaign = Campaign::updateOrCreate(['name' => 'Campaña Metrix Test']);
        
        $event = Event::updateOrCreate(
            ['detail' => 'Vacunación y Registro de Mascotas'],
            [
                'campaign_id' => $campaign->id,
                'street' => 'Centro de Convenciones',
                'responsible' => 'Admin Metrix',
                'date' => '2026-03-25',
                'checkin_code' => 'TEST-IN-001',
                'checkout_code' => 'TEST-OUT-001',
                'is_checkout_active' => true,
                'neighborhood' => 'Centro',
                'municipality' => 'Querétaro',
                'target_audience_filters' => ['has_pets' => true]
            ]
        );

        EventSlot::updateOrCreate(
            ['event_id' => $event->id, 'start_time' => '10:00:00'],
            [
                'end_time' => '11:00:00',
                'capacity' => 20
            ]
        );
    }
}
