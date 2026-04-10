<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\EventType;

class EventTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'metrix-enterprise'],
            [
                'name' => 'Metrix Enterprise',
                'domain' => 'metrix.com'
            ]
        );

        $types = [
            [
                'name' => 'Censo de Salud',
                'slug' => 'censo-salud',
                'icon' => 'pi pi-heart',
                'requires_appointment' => true,
                'has_beneficiaries' => true,
                'beneficiary_label' => 'Dependiente',
                'default_slot_config' => ['interval_minutes' => 15, 'capacity_per_slot' => 2]
            ],
            [
                'name' => 'Entrega de Apoyos',
                'slug' => 'entrega-apoyos',
                'icon' => 'pi pi-shopping-bag',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'default_slot_config' => ['interval_minutes' => 10, 'capacity_per_slot' => 10]
            ],
            [
                'name' => 'Evento Social',
                'slug' => 'evento-social',
                'icon' => 'pi pi-users',
                'requires_appointment' => false,
                'has_beneficiaries' => false
            ],
            [
                'name' => 'Campaña Política',
                'slug' => 'campana-politica',
                'icon' => 'pi pi-flag',
                'requires_appointment' => false,
                'has_beneficiaries' => false
            ]
        ];

        foreach ($types as $type) {
            EventType::updateOrCreate(
                ['slug' => $type['slug']],
                array_merge($type, ['tenant_id' => $tenant->id])
            );
        }
    }
}
