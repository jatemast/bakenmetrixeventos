<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            // Deportes
            ['name' => 'Fútbol', 'type' => 'interest', 'color' => '#3b82f6'],
            ['name' => 'Ciclismo', 'type' => 'interest', 'color' => '#10b981'],
            ['name' => 'Correr / Running', 'type' => 'interest', 'color' => '#f59e0b'],
            ['name' => 'Gimnasio', 'type' => 'interest', 'color' => '#ef4444'],
            ['name' => 'Béisbol', 'type' => 'interest', 'color' => '#8b5cf6'],
            ['name' => 'Natación', 'type' => 'interest', 'color' => '#06b6d4'],

            // Mascotas
            ['name' => 'Dueño de Perro', 'type' => 'interest', 'color' => '#d97706'],
            ['name' => 'Dueño de Gato', 'type' => 'interest', 'color' => '#7c3aed'],
            ['name' => 'Rescatista', 'type' => 'interest', 'color' => '#ec4899'],
            ['name' => 'Vacunación Animal', 'type' => 'interest', 'color' => '#10b981'],

            // Familia
            ['name' => 'Madre/Padre Cabeza de Familia', 'type' => 'demographic', 'color' => '#f43f5e'],
            ['name' => 'Hijos menores de 5', 'type' => 'demographic', 'color' => '#fbbf24'],
            ['name' => 'Hijos en Edad Escolar', 'type' => 'demographic', 'color' => '#22c55e'],
            ['name' => 'Adulto Mayor (60+)', 'type' => 'demographic', 'color' => '#64748b'],

            // Salud
            ['name' => 'Discapacidad Motriz', 'type' => 'health', 'color' => '#6366f1'],
            ['name' => 'Enfermedad Crónica', 'type' => 'health', 'color' => '#ef4444'],

            // Empleo
            ['name' => 'Busca Empleo', 'type' => 'employment', 'color' => '#ea580c'],
            ['name' => 'Emprendedor', 'type' => 'employment', 'color' => '#0891b2'],

            // Político
            ['name' => 'Militante', 'type' => 'political', 'color' => '#cc0000'],
            ['name' => 'Líder de Colonia', 'type' => 'political', 'color' => '#8b5cf6'],
            ['name' => 'Voluntario', 'type' => 'political', 'color' => '#10b981'],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['name' => $tag['name']],
                [
                    'slug' => \Illuminate\Support\Str::slug($tag['name']),
                    'type' => $tag['type'],
                    'color' => $tag['color']
                ]
            );
        }
    }
}
