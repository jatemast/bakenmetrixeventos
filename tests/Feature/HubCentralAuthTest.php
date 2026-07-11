<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Support\CuentaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubCentralAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'central.api_url' => 'https://backendmetrixsc.soymetrix.com/api/v1',
        ]);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/campaigns')->assertUnauthorized();
    }

    public function test_central_jwt_is_accepted_on_protected_routes(): void
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
            ->getJson('/api/campaigns');

        $response->assertOk();
    }

    public function test_cuenta_scope_filters_domain_records(): void
    {
        Campaign::withoutGlobalScopes()->create([
            'name' => 'Other tenant',
            'cuenta_id' => 1,
        ]);

        Campaign::withoutGlobalScopes()->create([
            'name' => 'Same tenant',
            'cuenta_id' => 7,
        ]);

        CuentaContext::set(7);

        $visible = Campaign::query()->pluck('name')->all();

        $this->assertSame(['Same tenant'], $visible);
    }

    public function test_new_records_inherit_active_cuenta_id(): void
    {
        CuentaContext::set(12);

        $campaign = Campaign::create(['name' => 'Scoped campaign']);

        $this->assertSame(12, $campaign->cuenta_id);
    }
}
