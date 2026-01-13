<?php

namespace Database\Seeders;

use App\Models\BonusPointHistory;
use Illuminate\Database\Seeder;

class BonusPointHistorySeeder extends Seeder
{
    public function run(): void
    {
        BonusPointHistory::create([
            'persona_id' => 1,
            'event_id' => 1,
            'points_awarded' => 100,
            'type' => 'attendance',
            'description' => 'Attended Concert Night',
        ]);

        BonusPointHistory::create([
            'persona_id' => 2,
            'event_id' => 2,
            'points_awarded' => 50,
            'type' => 'participation',
            'description' => 'Participated in Charity Run',
        ]);
    }
}