<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    /**
     * Seed contextual event types with rich default_form_schema templates.
     * Each type acts as a "blueprint" that pre-fills the event's registration form.
     */
    public function run(): void
    {
        $tenantId = \App\Models\Tenant::first()?->id;

        // Limpiar plantillas anteriores para evitar duplicados/basura
        EventType::query()->delete();

        $types = [
            // ── 1. Reforestación ──────────────────────────────────────────────
            [
                'name' => 'Campaña de Reforestación',
                'slug' => 'reforestacion',
                'icon' => 'pi pi-sun',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'beneficiary_label' => 'Árboles',
                'success_message' => '¡Gracias por unirte a la reforestación! Trae tus guantes y mucha energía. 🌳',
                'default_points_config' => ['attendee' => 15, 'leader' => 10, 'referral' => 5],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Logística de Siembra',
                            'icon' => 'pi pi-map',
                            'fields' => [
                                ['name' => 'trae_herramienta', 'label' => '¿Traes pala o pico?', 'type' => 'select', 'options' => ['Sí', 'No']],
                                ['name' => 'num_personas', 'label' => '¿Cuántas personas te acompañan?', 'type' => 'number', 'min' => 0],
                            ],
                        ],
                    ],
                ],
            ],

            // ── 2. Entrega de Cobijas (Damnificados) ──────────────────────────
            [
                'name' => 'Entrega de Cobijas / Apoyos',
                'slug' => 'entrega-cobijas',
                'icon' => 'pi pi-gift',
                'requires_appointment' => false,
                'has_beneficiaries' => true,
                'beneficiary_label' => 'Beneficiario',
                'success_message' => 'Registro de apoyo confirmado. Por favor presenta tu INE en el módulo. ❄️',
                'default_points_config' => ['attendee' => 5, 'leader' => 10, 'referral' => 5],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Validación de Apoyo',
                            'icon' => 'pi pi-id-card',
                            'fields' => [
                                ['name' => 'ine_clave', 'label' => 'Clave de Elector (INE)', 'type' => 'text', 'required' => true],
                                ['name' => 'situacion_vivienda', 'label' => 'Estado de vivienda', 'type' => 'select', 'options' => ['Damnificado', 'En riesgo', 'Normal']],
                            ],
                        ],
                    ],
                ],
            ],

            // ── 3. Vacunación (Pilot - Con Citas) ─────────────────────────────
            [
                'name' => 'Vacunación de Mascotas (Citas)',
                'slug' => 'vacunacion-mascotas-citas',
                'icon' => 'pi pi-heart',
                'requires_appointment' => true,
                'has_beneficiaries' => true,
                'beneficiary_label' => 'Mascota',
                'success_message' => '¡Cita de vacunación confirmada! Presentate 10 min antes con correa. 🐾',
                'default_points_config' => ['attendee' => 10, 'leader' => 5, 'referral' => 5],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos de la Mascota',
                            'icon' => 'pi pi-heart',
                            'fields' => [
                                ['name' => 'nombre_mascota', 'label' => 'Nombre de la Mascota', 'type' => 'text', 'required' => true],
                                ['name' => 'especie', 'label' => 'Especie', 'type' => 'select', 'options' => ['Perro', 'Gato']],
                                ['name' => 'edad_mascota', 'label' => 'Edad de la mascota', 'type' => 'number'],
                            ],
                        ],
                    ],
                ],
            ],

            // ── 4. Apoyo Político (Militantes) ─────────────────────────────────
            [
                'name' => 'Apoyo Político / Mitin',
                'slug' => 'apoyo-politico',
                'icon' => 'pi pi-megaphone',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'success_message' => '¡Sumamos tu apoyo! Juntos por el cambio. 🗳️',
                'default_points_config' => ['attendee' => 20, 'leader' => 15, 'referral' => 10],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Información Electoral',
                            'icon' => 'pi pi-id-card',
                            'fields' => [
                                ['name' => 'seccion_electoral', 'label' => 'Sección (INE)', 'type' => 'text', 'required' => true],
                                ['name' => 'id_militante', 'label' => 'ID de Militante (Opcional)', 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($types as $typeData) {
            $typeData['tenant_id'] = $tenantId;

            EventType::withoutGlobalScopes()->updateOrCreate(
                ['slug' => $typeData['slug']],
                $typeData
            );
        }

        $this->command->info("✅ Seeded " . count($types) . " contextual event types with form templates.");
    }
}
