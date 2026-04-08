<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventType;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Define common groups
        $groups = [
            ['key' => 'general', 'label' => 'Datos Personales', 'icon' => '👤', 'order' => 1],
            ['key' => 'ubicacion', 'label' => 'Ubicación', 'icon' => '📍', 'order' => 2],
            ['key' => 'beneficiario', 'label' => 'Beneficiario', 'icon' => '🐾', 'order' => 3],
            ['key' => 'electoral', 'label' => 'Datos Electorales', 'icon' => '🗳️', 'order' => 4],
            ['key' => 'salud', 'label' => 'Datos de Salud', 'icon' => '🏥', 'order' => 5],
        ];

        // 1. Vacunación Animal
        EventType::updateOrCreate(['slug' => 'vacunacion'], [
            'name' => 'Vacunación Animal',
            'icon' => '🐾',
            'requires_appointment' => true,
            'has_beneficiaries' => true,
            'beneficiary_label' => 'Mascota',
            'default_slot_config' => ['interval_minutes' => 20, 'capacity_per_slot' => 4],
            'default_points_config' => ['attendee' => 5, 'leader' => 3, 'referral' => 2],
            'default_form_schema' => [
                'fields' => [
                    ['key' => 'nombre', 'label' => 'Nombre completo', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 1],
                    ['key' => 'pet_name', 'label' => 'Nombre de la Mascota', 'type' => 'text', 'required' => true, 'group' => 'beneficiario', 'order' => 2],
                    ['key' => 'pet_type', 'label' => 'Tipo de Mascota', 'type' => 'select', 'options' => ['Perro', 'Gato', 'Otro'], 'required' => true, 'group' => 'beneficiario', 'order' => 3],
                    ['key' => 'codigo_postal', 'label' => 'Código Postal', 'type' => 'number', 'required' => true, 'group' => 'ubicacion', 'order' => 4],
                ],
                'groups' => $groups
            ]
        ]);

        // 2. Registro Electoral
        EventType::updateOrCreate(['slug' => 'registro_electoral'], [
            'name' => 'Registro Electoral',
            'icon' => '🗳️',
            'requires_appointment' => true,
            'has_beneficiaries' => false,
            'default_slot_config' => ['interval_minutes' => 15, 'capacity_per_slot' => 8],
            'default_points_config' => ['attendee' => 3, 'leader' => 1, 'referral' => 0],
            'default_form_schema' => [
                'fields' => [
                    ['key' => 'nombre', 'label' => 'Nombre completo', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 1],
                    ['key' => 'clave_elector', 'label' => 'Clave de Elector', 'type' => 'text', 'required' => true, 'group' => 'electoral', 'order' => 2],
                    ['key' => 'seccion', 'label' => 'Sección Electoral', 'type' => 'number', 'required' => true, 'group' => 'electoral', 'order' => 3],
                    ['key' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 4],
                ],
                'groups' => $groups
            ]
        ]);

        // 3. Jornada de Salud
        EventType::updateOrCreate(['slug' => 'jornada_salud'], [
            'name' => 'Jornada de Salud',
            'icon' => '🏥',
            'requires_appointment' => true,
            'has_beneficiaries' => false,
            'default_slot_config' => ['interval_minutes' => 30, 'capacity_per_slot' => 6],
            'default_points_config' => ['attendee' => 5, 'leader' => 2, 'referral' => 1],
            'default_form_schema' => [
                'fields' => [
                    ['key' => 'nombre', 'label' => 'Nombre completo', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 1],
                    ['key' => 'tipo_sangre', 'label' => 'Tipo de Sangre', 'type' => 'select', 'options' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], 'required' => true, 'group' => 'salud', 'order' => 2],
                    ['key' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 3],
                    ['key' => 'alergias', 'label' => 'Alergias Conocidas', 'type' => 'textarea', 'required' => false, 'group' => 'salud', 'order' => 4],
                ],
                'groups' => $groups
            ]
        ]);

        // 4. Concierto/Festival
        EventType::updateOrCreate(['slug' => 'concierto'], [
            'name' => 'Concierto/Festival',
            'icon' => '🎵',
            'requires_appointment' => false,
            'has_beneficiaries' => false,
            'default_points_config' => ['attendee' => 10, 'leader' => 5, 'referral' => 0],
            'default_form_schema' => [
                'fields' => [
                    ['key' => 'nombre', 'label' => 'Nombre del Asistente', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 1],
                    ['key' => 'ticket_code', 'label' => 'Código de Boleto', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 2],
                ],
                'groups' => [['key' => 'general', 'label' => 'Acceso al Concierto', 'icon' => '🎟️', 'order' => 1]]
            ]
        ]);

        // 5. Reforestación
        EventType::updateOrCreate(['slug' => 'reforestacion'], [
            'name' => 'Reforestación',
            'icon' => '🌳',
            'requires_appointment' => false,
            'has_beneficiaries' => false,
            'default_points_config' => ['attendee' => 8, 'leader' => 5, 'referral' => 3],
            'default_form_schema' => [
                'fields' => [
                    ['key' => 'nombre', 'label' => 'Nombre del Voluntario', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 1],
                    ['key' => 'colonia', 'label' => 'Colonia que Representa', 'type' => 'text', 'required' => true, 'group' => 'ubicacion', 'order' => 2],
                ],
                'groups' => $groups
            ]
        ]);

        // 6. Conferencia/Taller
        EventType::updateOrCreate(['slug' => 'conferencia'], [
            'name' => 'Conferencia/Taller',
            'icon' => '🎓',
            'requires_appointment' => false,
            'has_beneficiaries' => false,
            'default_points_config' => ['attendee' => 5, 'leader' => 0, 'referral' => 0],
            'default_form_schema' => [
                'fields' => [
                    ['key' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true, 'group' => 'general', 'order' => 1],
                    ['key' => 'email', 'label' => 'Correo Electrónico Corp.', 'type' => 'email', 'required' => true, 'group' => 'general', 'order' => 2],
                    ['key' => 'empresa', 'label' => 'Empresa / Institución', 'type' => 'text', 'required' => false, 'group' => 'general', 'order' => 3],
                ],
                'groups' => [['key' => 'general', 'label' => 'Registro Académico', 'icon' => '📘', 'order' => 1]]
            ]
        ]);
    }
}
