<?php

namespace Database\Seeders;

use App\Models\EventAttendee;
use Illuminate\Database\Seeder;

class EventAttendeeSeeder extends Seeder
{
    public function run(): void
    {
        EventAttendee::create([
            'event_id' => 1,
            'persona_id' => 1,
            'checkin_at' => '2025-06-15 18:00:00',
            'checkout_at' => '2025-06-15 22:00:00',
        ]);

        EventAttendee::create([
            'event_id' => 2,
            'persona_id' => 2,
            'leader_id' => 2,
            'checkin_at' => '2025-12-10 09:00:00',
            'checkout_at' => '2025-12-10 11:00:00',
        ]);
    }
}