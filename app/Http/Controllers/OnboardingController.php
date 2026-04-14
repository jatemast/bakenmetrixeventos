<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Senior Onboarding Controller (PURE CRM GROWTH)
 * 
 * 3 QRs de Registro - Solo para dar de alta en la base de datos:
 * 
 * QR Ciudadano  → Se registra → Entra como U1 (ya está en el CRM)
 * QR Militante  → Se registra → Entra como U4
 * QR Líder      → Se registra → Entra como U3
 * 
 * U2 es transitorio: una persona "no registrada" escanea el QR ciudadano
 * y al completar el registro pasa directo a U1.
 * 
 * NADA de eventos aquí. Solo CRM.
 */
class OnboardingController extends Controller
{
    /**
     * Registra una nueva persona desde uno de los 3 portales QR.
     */
    public function handleRegistration(Request $request)
    {
        $validatedData = $request->validate([
            'tenant_id'        => 'required|exists:tenants,id',
            'type'             => 'required|in:citizen,militant,leader',
            'nombre'           => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'numero_celular'   => 'required|string',
            'curp'             => 'nullable|string',
            'clave_elector'    => 'nullable|string',
            'email'            => 'nullable|email',
            'calle'            => 'nullable|string',
            'colonia'          => 'nullable|string',
            'codigo_postal'    => 'nullable|string',
            'municipio'        => 'nullable|string',
            'estado'           => 'nullable|string',
            'seccion'          => 'nullable|string',
            'region'           => 'nullable|string',
            'ref'              => 'nullable|string', // El código del líder (ej: LDR-X82J)
        ]);

        try {
            DB::beginTransaction();

            // ── Determinar Universo ──────────────────────────────────
            // U2 no se almacena: el ciudadano que se registra YA es U1.
            $universeType = match ($validatedData['type']) {
                'leader'   => Persona::UNIVERSE_U3,
                'militant' => Persona::UNIVERSE_U4,
                default    => Persona::UNIVERSE_U1, // Ciudadano → directo a U1
            };

            // ── Duplicado por Tenant (Aislamiento estricto) ──────────
            $existing = Persona::withoutGlobalScopes()
                ->where('tenant_id', $validatedData['tenant_id'])
                ->where(function ($q) use ($validatedData) {
                    $q->where('numero_celular', $validatedData['numero_celular']);
                    if (!empty($validatedData['curp'])) {
                        $q->orWhere('curp', $validatedData['curp']);
                    }
                })->first();

            if ($existing) {
                return response()->json([
                    'success'            => false,
                    'message'            => 'Ya te encuentras registrado en esta institución.',
                    'already_registered' => true,
                    'universe'           => $existing->universe_type,
                ], 409);
            }

            // ── Crear Persona ────────────────────────────────────────
            $persona = Persona::create([
                'tenant_id'        => $validatedData['tenant_id'],
                'universe_type'    => $universeType,
                'is_leader'        => $universeType === Persona::UNIVERSE_U3,
                
                // Atribución de Líder
                'leader_id'        => isset($validatedData['ref']) 
                    ? Persona::where('referral_code', $validatedData['ref'])->value('id') 
                    : null,

                'nombre'           => $validatedData['nombre'],
                'apellido_paterno' => $validatedData['apellido_paterno'],
                'apellido_materno' => $validatedData['apellido_materno'] ?? null,
                'numero_celular'   => $validatedData['numero_celular'],
                'curp'             => $validatedData['curp'] ?? null,
                'clave_elector'    => $validatedData['clave_elector'] ?? null,
                'email'            => $validatedData['email'] ?? null,
                'calle'            => $validatedData['calle'] ?? null,
                'colonia'          => $validatedData['colonia'] ?? null,
                'codigo_postal'    => $validatedData['codigo_postal'] ?? null,
                'municipio'        => $validatedData['municipio'] ?? null,
                'estado'           => $validatedData['estado'] ?? null,
                'seccion'          => $validatedData['seccion'] ?? null,
                'region'           => $validatedData['region'] ?? null,
                'metadata'         => [
                    'onboarding_source' => 'qr_portal',
                    'onboarding_type'   => $validatedData['type'],
                    'onboarding_date'   => now()->toDateTimeString(),
                ],
            ]);

            DB::commit();

            $labels = [
                Persona::UNIVERSE_U1 => 'Ciudadano Registrado',
                Persona::UNIVERSE_U3 => 'Líder',
                Persona::UNIVERSE_U4 => 'Militante',
            ];

            return response()->json([
                'success' => true,
                'message' => '¡Registro exitoso! Ya formas parte de nuestra base de datos.',
                'data'    => [
                    'codigo_ciudadano' => $persona->codigo_ciudadano,
                    'universo'         => $universeType,
                    'perfil'           => $labels[$universeType] ?? 'Registrado',
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Onboarding Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en el registro. Intente más tarde.',
            ], 500);
        }
    }

    /**
     * Devuelve las 3 URLs maestras de registro para que el Admin
     * las descargue como QR e imprima en lonas, volantes, etc.
     */
    public function getOnboardingUrls(Request $request)
    {
        // Forzamos un ID para pruebas si no viene ninguno
        $tenant_id = $request->user()->tenant_id 
                   ?? $request->header('X-Tenant-Id') 
                   ?? 1; // Fallback al tenant 1

        Log::info("DEBUG: Fetching onboarding URLs. Tenant ID found: " . $tenant_id);

        $baseUrl = config('app.frontend_url', 'https://eventos2.soymetrix.com'); // Usamos tu URL real del frontend

        return response()->json([
            'citizen' => [
                'label'    => 'Registro Ciudadano',
                'universe' => 'U1',
                'url'      => "{$baseUrl}/registro/ciudadano?tid={$tenant_id}",
                'tag'      => 'CITIZEN_REG',
            ],
            'militant' => [
                'label'    => 'Alta de Militante',
                'universe' => 'U4',
                'url'      => "{$baseUrl}/registro/militante?tid={$tenant_id}",
                'tag'      => 'MILITANT_VAL',
            ],
            'leader' => [
                'label'    => 'Activación de Líder',
                'universe' => 'U3',
                'url'      => "{$baseUrl}/registro/lider?tid={$tenant_id}",
                'tag'      => 'LEADER_AUTH',
            ],
        ]);
    }
}
