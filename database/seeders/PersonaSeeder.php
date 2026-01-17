<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;

class PersonaSeeder extends Seeder
{
    public function run(): void
    {
        Persona::create([
            'cedula' => '123456789',
            'nombre' => 'John',
            'apellido_paterno' => 'Doe',
            'apellido_materno' => 'Smith',
            'edad' => 30,
            'sexo' => 'H',
            'calle' => 'Main St',
            'numero_exterior' => '123',
            'numero_interior' => 'A',
            'colonia' => 'Downtown',
            'codigo_postal' => '12345',
            'municipio' => 'City',
            'estado' => 'State',
            'numero_celular' => '1234567890',
            'numero_telefono' => '0987654321',
            'is_leader' => false,
            'loyalty_balance' => 100,
            'universe_type' => 'U1',
        ]);

        Persona::create([
            'cedula' => '987654321',
            'nombre' => 'Jane',
            'apellido_paterno' => 'Smith',
            'apellido_materno' => 'Johnson',
            'edad' => 25,
            'sexo' => 'M',
            'calle' => 'Second St',
            'numero_exterior' => '456',
            'numero_interior' => 'B',
            'colonia' => 'Uptown',
            'codigo_postal' => '67890',
            'municipio' => 'Town',
            'estado' => 'Province',
            'numero_celular' => '0987654321',
            'numero_telefono' => '1234567890',
            'is_leader' => true,
            'referral_code' => 'LEADER123',
            'loyalty_balance' => 500,
            'universe_type' => 'U3',
        ]);
    }
}