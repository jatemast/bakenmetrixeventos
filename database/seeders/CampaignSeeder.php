<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        Campaign::create([
            'name' => 'Summer Festival 2025',
            'theme' => 'Music and Fun',
            'campaign_manager' => 'John Manager',
            'email' => 'manager@example.com',
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
            'campaign_number' => 1,
        ]);

        Campaign::create([
            'name' => 'Winter Charity Drive',
            'theme' => 'Charity and Giving',
            'campaign_manager' => 'Jane Manager',
            'email' => 'jane.manager@example.com',
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'campaign_number' => 2,
        ]);
    }
}