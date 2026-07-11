<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Persona;
use App\Models\SyncDevice;
use App\Support\CuentaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    private string $syncToken;

    protected function setUp(): void
    {
        parent::setUp();

        config(['central.api_url' => 'https://backendmetrixsc.soymetrix.com/api/v1']);

        [, $this->syncToken] = SyncDevice::issue(7, 'Test Master');
    }

    public function test_sync_health_is_public(): void
    {
        $this->getJson('/api/sync/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_pull_requires_sync_token(): void
    {
        $this->getJson('/api/sync/v1/pull?tipo=campanas&tenant_id=7')
            ->assertUnauthorized();
    }

    public function test_pull_returns_wire_compatible_campaigns(): void
    {
        Campaign::withoutGlobalScopes()->create([
            'cuenta_id' => 7,
            'name' => 'Summer drive',
            'campaign_manager' => 'Metrix Team',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);

        CuentaContext::set(7);

        $response = $this
            ->withToken($this->syncToken)
            ->getJson('/api/sync/v1/pull?tipo=campanas&tenant_id=7');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.nombre', 'Summer drive')
            ->assertJsonPath('data.0.tenant_id', 7);
    }

    public function test_push_ciudadanos_maps_telefono_to_numero_celular(): void
    {
        $externalId = 'citizen-uuid-001';

        $response = $this
            ->withToken($this->syncToken)
            ->postJson('/api/sync/v1/sync', [
                'tipo' => 'CIUDADANOS',
                'payload' => [[
                    'id' => $externalId,
                    'nombre' => 'Ana',
                    'apellido_paterno' => 'Lopez',
                    'apellido_materno' => 'Ruiz',
                    'telefono' => '5512345678',
                    'tenant_id' => 7,
                ]],
            ]);

        $response->assertOk()->assertJsonPath('data.accepted', 1);

        $persona = Persona::withoutGlobalScopes()
            ->where('external_id', $externalId)
            ->first();

        $this->assertSame('5512345678', $persona->numero_celular);
    }

    public function test_push_tickets_is_idempotent_by_source_key(): void
    {
        $campaign = Campaign::withoutGlobalScopes()->create([
            'cuenta_id' => 7,
            'name' => 'Campaign',
        ]);

        $event = Event::withoutGlobalScopes()->create([
            'cuenta_id' => 7,
            'campaign_id' => $campaign->id,
            'detail' => 'Town hall',
            'date' => '2026-07-10',
            'time' => '18:00:00',
            'responsible' => 'Staff',
            'duration_hours' => 2,
        ]);

        $persona = Persona::withoutGlobalScopes()->create([
            'cuenta_id' => 7,
            'external_id' => 'person-001',
            'cedula' => 'CURP001',
            'nombre' => 'Pedro',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => '',
            'edad' => 30,
            'sexo' => 'H',
            'calle' => 'Main',
            'numero_exterior' => '1',
            'colonia' => 'Centro',
            'codigo_postal' => '00000',
            'municipio' => 'CDMX',
            'estado' => 'CDMX',
        ]);

        $payload = [[
            'id' => 'TKT_'.$event->id.'_person-001',
            'evento_id' => (string) $event->id,
            'ciudadano_id' => 'person-001',
            'estado_entrega' => 1,
            'fecha_hora_recepcion' => '2026-07-10T18:30:00Z',
            'tenant_id' => 7,
        ]];

        $first = $this->withToken($this->syncToken)->postJson('/api/sync/v1/sync', [
            'tipo' => 'TICKETS',
            'payload' => $payload,
        ]);

        $first->assertOk()->assertJsonPath('data.accepted', 1);

        $second = $this->withToken($this->syncToken)->postJson('/api/sync/v1/sync', [
            'tipo' => 'TICKETS',
            'payload' => $payload,
        ]);

        $second->assertOk()->assertJsonPath('data.duplicates', 1);

        $this->assertSame(1, EventAttendee::withoutGlobalScopes()->count());
    }

    public function test_admin_can_issue_sync_device_token(): void
    {
        Http::fake([
            'backendmetrixsc.soymetrix.com/api/v1/auth/me' => Http::response([
                'success' => true,
                'data' => [
                    'email' => 'hub-admin@example.com',
                    'name' => 'Hub Admin',
                    'account_id' => 7,
                    'modules' => ['events'],
                ],
            ]),
        ]);

        $response = $this
            ->withToken('central-jwt-token')
            ->postJson('/api/sync-devices', ['label' => 'Field tablet 2']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'label', 'cuenta_id']]);
    }
}
