<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        Event::create([
            'campaign_id' => 1,
            'detail' => 'Concert Night',
            'date' => '2025-06-15',
            'responsible' => 'Event Organizer',
            'email' => 'organizer@example.com',
            'street' => 'Central Park',
            'checkin_code' => 'CHECKIN123',
            'checkout_code' => 'CHECKOUT123',
            'bonus_points_for_attendee' => 10,
            'bonus_points_for_leader' => 5,
        ]);

        Event::create([
            'campaign_id' => 2,
            'detail' => 'Charity Run',
            'date' => '2025-12-10',
            'responsible' => 'Charity Organizer',
            'email' => 'charity@example.com',
            'street' => 'Downtown Square',
            'checkin_code' => 'CHECKIN456',
            'checkout_code' => 'CHECKOUT456',
            'bonus_points_for_attendee' => 20,
            'bonus_points_for_leader' => 10,
        ]);
    }
}