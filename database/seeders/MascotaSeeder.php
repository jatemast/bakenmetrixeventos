<?php

namespace Database\Seeders;

use App\Models\Mascota;
use Illuminate\Database\Seeder;

class MascotaSeeder extends Seeder
{
    public function run(): void
    {
        Mascota::create([
            'persona_id' => 1,
            'reino_animal' => 'Perro',
            'edad' => 3,
            'nombre' => 'Buddy',
        ]);

        Mascota::create([
            'persona_id' => 2,
            'reino_animal' => 'Gato',
            'edad' => 2,
            'nombre' => 'Whiskers',
        ]);
    }
}