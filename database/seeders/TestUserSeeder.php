<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Admin Metrix',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'admin'
            ]
        );
    }
}
