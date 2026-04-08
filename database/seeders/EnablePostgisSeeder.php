<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnablePostgisSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis_topology;');
    }
}
