<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Persona;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicRegistrationController extends Controller
{
    /**
     * Check if WhatsApp number exists and return persona data
     */
    public function checkWhatsApp(Request $request)
    {
        $input = $request->all();
        
        // Handle array inputs from n8n (flattening)
        if (isset($input['whatsapp_number']) && is_array($input['whatsapp_number'])) {
            $input['whatsapp_number'] = $input['whatsapp_number'][0] ?? null;
        }
        if (isset($input['whatsapp']) && is_array($input['whatsapp'])) {
            $input['whatsapp'] = $input['whatsapp'][0] ?? null;
        }

        // Allow 'whatsapp' as alias for 'whatsapp_number' for n8n compatibility
        if (!isset($input['whatsapp_number']) && isset($input['whatsapp'])) {
            $input['whatsapp_number'] = $input['whatsapp'];
        }

        $validator = Validator::make($input, [
            'whatsapp_number' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $whatsappNumber = $this->normalizePhoneNumber($input['whatsapp_number']);

        $persona = Persona::where('numero_celular', $whatsappNumber)->first();

        if ($persona) {
            return response()->json([
                'success' => true,
                'exists' => true,
                'persona' => [
                    'id' => $persona->id,
                    'name' => $persona->nombre,
                    'last_name' => $persona->apellido_paterno . ' ' . $persona->apellido_materno,
                    'full_name' => $persona->nombre . ' ' . $persona->apellido_paterno,
                    'whatsapp_number' => $persona->numero_celular,
                    'universe_type' => $persona->universe_type ?? 'U1',
                    'pets' => $persona->mascotas->map(function($pet) {
                        return [
                            'id' => $pet->id,
                            'name' => $pet->nombre,
                            'type' => $pet->tipo,
                            'breed' => $pet->raza
                        ];
                    })
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'exists' => false,
            'message' => 'WhatsApp number not found in system'
        ]);
    }

    /**
     * Public registration for citizens
     */
    public function register(Request $request)
    {
        $input = $request->all();
        if (!isset($input['whatsapp_number']) && isset($input['whatsapp'])) {
            $input['whatsapp_number'] = $input['whatsapp'];
        }

        $validator = Validator::make($input, [
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'maternal_name' => 'nullable|string|max:255',
            'whatsapp_number' => 'required|string',
            'curp' => 'required|string|max:18',
            'clave_elector' => 'nullable|string|max:18',
            'seccion' => 'nullable|string|max:10',
            'vigencia' => 'nullable|string|max:4',
            'tipo_sangre' => 'nullable|string|max:5',
            'category' => 'nullable|string',
            'tarifa' => 'nullable|string',
            'servicios' => 'nullable|string',
            'tags' => 'nullable|array',
            'email' => 'nullable|email',
            'identification_number' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:H,M,O',
            'age' => 'nullable|integer|min:0|max:150',
            'is_leader' => 'nullable|boolean',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'children_under_5_count' => 'nullable|integer|min:0|max:20',
            'children_details' => 'nullable|array',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'leader_id' => 'nullable|exists:personas,id',
            'tenant_id' => 'nullable|exists:tenants,id',
            'beneficiaries' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Normalize phone number
            $whatsappNumber = $this->normalizePhoneNumber($request->whatsapp_number);

            // Check if already registered
            $existingPersona = Persona::where('numero_celular', $whatsappNumber)->first();
            if ($existingPersona) {
                return response()->json([
                    'success' => true,
                    'already_registered' => true,
                    'message' => '¡Ya estás registrado en nuestro sistema!',
                    'persona' => [
                        'id' => $existingPersona->id,
                        'name' => $existingPersona->nombre,
                        'full_name' => $existingPersona->nombre . ' ' . $existingPersona->apellido_paterno,
                        'whatsapp_number' => $existingPersona->numero_celular,
                        'loyalty_points' => $existingPersona->loyalty_balance ?? 0,
                    ]
                ]);
            }

            if ($request->has('tenant_id')) {
                app()->instance('tenant_id', $request->tenant_id);
            }

            // Build tags with extra CRM info
            $tags = [];
            if ($request->boolean('is_pregnant')) {
                $tags[] = 'embarazada';
            }
            if ($request->boolean('has_pets')) {
                $tags[] = 'tiene_mascotas';
            }
            if ($request->boolean('has_children_under_5')) {
                $tags[] = 'tiene_menores_5';
                $tags[] = 'menores_5_cantidad:' . ($request->children_under_5_count ?? 1);
            }
            if ($request->emergency_contact) {
                $tags[] = 'contacto_emergencia:' . $request->emergency_contact;
            }
            if ($request->emergency_phone) {
                $tags[] = 'tel_emergencia:' . $request->emergency_phone;
            }

            $tenantId = $request->tenant_id ?? (app()->bound('tenant_id') ? app('tenant_id') : \App\Models\Tenant::first()?->id);

            $persona = Persona::create([
                'curp' => $request->curp,
                'clave_elector' => $request->clave_elector,
                'seccion' => $request->seccion,
                'vigencia' => $request->vigencia,
                'tipo_sangre' => $request->tipo_sangre,
                'categoria' => $request->category ?? $request->categoria,
                'tarifa' => $request->tarifa,
                'servicios' => $request->servicios,
                'nombre' => $request->name,
                'apellido_paterno' => $request->last_name,
                'apellido_materno' => $request->maternal_name ?? '',
                'edad' => $request->age ?? 0,
                'sexo' => $request->gender ?? 'O',
                'email' => $request->email,
                'numero_celular' => $whatsappNumber,
                'cedula' => $request->identification_number,
                
                'calle' => $request->street ?? '',
                'numero_exterior' => $request->external_number ?? '',
                'numero_interior' => $request->internal_number ?? '',
                'colonia' => $request->neighborhood ?? '',
                'codigo_postal' => $request->postal_code ?? '',
                'municipio' => $request->municipality ?? '',
                'estado' => $request->state ?? 'Querétaro',
                
                'tags' => array_merge(!empty($tags) ? $tags : [], $request->tags ?? []),
                'is_leader' => $request->is_leader ?? false,
                'leader_id' => $request->leader_id,
                'tenant_id' => $tenantId
            ]);

            // 1.2 Registro de Beneficiarios Genéricos (Pilar 1)
            $beneficiariesFromRequest = $request->input('beneficiaries') ?? $request->input('pet_details') ?? [];
            if (is_array($beneficiariesFromRequest) && count($beneficiariesFromRequest) > 0) {
                foreach ($beneficiariesFromRequest as $beneficiary) {
                    $persona->beneficiarios()->create([
                        'nombre' => $beneficiary['name'] ?? $beneficiary['nombre'] ?? 'Sin Nombre',
                        'tipo' => $beneficiary['type'] ?? $beneficiary['tipo'] ?? 'General',
                        'tenant_id' => $persona->tenant_id,
                        'metadata' => [
                            'raza' => $beneficiary['breed'] ?? $beneficiary['raza'] ?? null,
                            'edad' => $beneficiary['age'] ?? $beneficiary['edad'] ?? null,
                            'nota' => $beneficiary['note'] ?? $beneficiary['nota'] ?? null
                        ]
                    ]);
                }
            }

            // Create User account for portal access
            if ($request->email) {
                try {
                    User::create([
                        'name' => $request->name . ' ' . $request->last_name,
                        'email' => $request->email,
                        'password' => Hash::make($whatsappNumber),
                        'role' => 'user',
                        'persona_id' => $persona->id,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Could not create user account: ' . $e->getMessage());
                }
            }

            // Trigger WhatsApp welcome message via n8n FLOW 4
            try {
                $whatsappService = app(WhatsAppNotificationService::class);
                $whatsappService->sendWelcomeMessage($persona);
            } catch (\Exception $e) {
                Log::warning('Could not trigger WhatsApp welcome: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => '¡Registro exitoso! Bienvenido al sistema METRIX.',
                'persona' => [
                    'id' => $persona->id,
                    'name' => $persona->nombre,
                    'full_name' => $persona->nombre . ' ' . $persona->apellido_paterno . ' ' . $persona->apellido_materno,
                    'whatsapp_number' => $persona->numero_celular,
                    'email' => $persona->email,
                    'loyalty_points' => 0,
                    'universe_type' => 'U1',
                    'tags' => $persona->tags,
                    'is_pregnant' => in_array('embarazada', $persona->tags ?? []),
                    'has_pets' => in_array('tiene_mascotas', $persona->tags ?? []),
                    'has_children_under_5' => in_array('tiene_menores_5', $persona->tags ?? []),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Public registration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Register for event via invitation
     */
    public function registerForEvent(Request $request)
    {
        $input = $request->all();
        if (!isset($input['whatsapp_number']) && isset($input['whatsapp'])) {
            $input['whatsapp_number'] = $input['whatsapp'];
        }

        $validator = Validator::make($input, [
            'event_id' => 'required|exists:events,id',
            'persona_id' => 'nullable|exists:personas,id',
            'leader_id' => 'nullable|exists:personas,id',
            'whatsapp_number' => 'required_without:persona_id|string',
            'name' => 'required_without:persona_id|string|max:255',
            'last_name' => 'required_without:persona_id|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $persona = null;

            // If persona_id provided, use existing persona
            if ($request->persona_id) {
                $persona = Persona::find($request->persona_id);
            } else {
                // Check if whatsapp exists
                $whatsappNumber = $this->normalizePhoneNumber($request->whatsapp_number);
                $persona = Persona::where('numero_celular', $whatsappNumber)->first();

                // Create new persona if not found
                if (!$persona) {
                    $persona = Persona::create([
                        'cedula' => 'ID-' . Str::random(10),
                        'nombre' => $request->name,
                        'apellido_paterno' => $request->last_name,
                        'apellido_materno' => $request->maternal_name ?? '',
                        'edad' => 0,
                        'sexo' => 'O',
                        'calle' => '',
                        'numero_exterior' => '',
                        'numero_interior' => '',
                        'colonia' => '',
                        'codigo_postal' => '',
                        'municipio' => '',
                        'numero_celular' => $whatsappNumber,
                        'numero_telefono' => null, // Evitar duplicidad innecesaria
                        'email' => null,
                        'universe_type' => 'U1',
                        'universe_group' => 'I',
                        'is_leader' => false,
                        'loyalty_points' => 0,
                    ]);
                }
            }

            // Register for event
            $event = \App\Models\Event::find($request->event_id);
            
            // Check if already registered
            $existingAttendee = $event->attendees()->where('persona_id', $persona->id)->first();
            
            if ($existingAttendee) {
                return response()->json([
                    'success' => true,
                    'message' => 'Already registered for this event',
                    'attendee' => $existingAttendee
                ]);
            }

            // Create attendee record
            $attendee = $event->attendees()->create([
                'persona_id' => $persona->id,
                'leader_id' => $request->leader_id,
                'check_in_time' => null,
                'check_out_time' => null,
                'bonus_points' => 0,
                'status' => 'registered',
            ]);

            // Check if capacity reached 80% (FLOW 12)
            $this->check80PercentCapacity($event);

            // Trigger WhatsApp confirmation with QRs
            try {
                $whatsappService = app(\App\Services\WhatsAppNotificationService::class);
                $whatsappService->sendEventQrs($persona, $event);
            } catch (\Exception $e) {
                Log::warning('Could not send event QRs via WhatsApp: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully registered for event!',
                'event' => [
                    'id' => $event->id,
                    'name' => $event->detail,
                    'date' => $event->date,
                    'time' => $event->time,
                    'location' => $event->street . ', ' . $event->neighborhood,
                    'checkin_code' => $event->checkin_code,
                    'checkout_code' => $event->checkout_code,
                    'checkin_qr_url' => "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . ($event->checkin_code),
                    'checkout_qr_url' => "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . ($event->checkout_code),
                ],
                'attendee' => $attendee,
                'persona' => [
                    'id' => $persona->id,
                    'name' => $persona->nombre . ' ' . $persona->apellido_paterno,
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Event registration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Event registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get persona's events and loyalty points
     */
    public function getPersonaProfile(Request $request)
    {
        $input = $request->all();
        if (!isset($input['whatsapp_number']) && isset($input['whatsapp'])) {
            $input['whatsapp_number'] = $input['whatsapp'];
        }

        $validator = Validator::make($input, [
            'persona_id' => 'nullable|exists:personas,id',
            'whatsapp_number' => 'required_without:persona_id|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $persona = null;

            if ($request->persona_id) {
                $persona = Persona::find($request->persona_id);
            } else {
                $whatsappNumber = $this->normalizePhoneNumber($request->whatsapp_number);
                $persona = Persona::where('numero_celular', $whatsappNumber)->first();
            }

            if (!$persona) {
                return response()->json([
                    'success' => false,
                    'message' => 'Persona not found'
                ], 404);
            }

            // Get events
            $events = $persona->events()->with(['attendees' => function($query) use ($persona) {
                $query->where('persona_id', $persona->id);
            }])->get();

            // Get bonus points history
            $pointsHistory = $persona->bonusPointsHistory()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'persona' => [
                    'id' => $persona->id,
                    'name' => $persona->nombre,
                    'full_name' => $persona->nombre . ' ' . $persona->apellido_paterno,
                    'email' => $persona->email,
                    'whatsapp_number' => $persona->numero_celular,
                    'loyalty_points' => $persona->loyalty_points,
                    'universe_type' => $persona->universe_type,
                ],
                'events' => $events,
                'points_history' => $pointsHistory,
                'total_events_attended' => $events->count(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Get persona profile error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get event details for dynamic registration form
     */
    public function getEventDetails($id)
    {
        $event = \App\Models\Event::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'name' => $event->detail,
                'date' => $event->date,
                'time' => $event->time,
                'location' => $event->neighborhood . ', ' . $event->municipality,
                'points' => $event->bonus_points_for_attendee ?? 5,
                'form_schema' => $event->form_schema ?? null,
                'unit_name' => $event->slot_unit_name ?? 'Mascota'
            ]
        ]);
    }

    /**
     * Normalize phone number format
     */
    private function normalizePhoneNumber($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle n8n array flattening/concatenation bugs (e.g., repeating numbers)
        // If number is very long and has a repeating pattern at start/end
        if (strlen($phone) >= 20) {
            $half = strlen($phone) / 2;
            $firstHalf = substr($phone, 0, $half);
            $secondHalf = substr($phone, $half);
            if ($firstHalf === $secondHalf) {
                $phone = $firstHalf;
            }
        }
        
        if (strlen($phone) > 15) {
            $phone = substr($phone, 0, 12); // Fallback to standard length
        }

        // Add country code if not present (assuming Mexico +52)
        if (strlen($phone) == 10) {
            $phone = '52' . $phone;
        }
        
        return $phone;
    }

    /**
     * Check if user has an active campaign invitation pending.
     * Used by n8n Flow Unico to route "SÍ" responses to Flow 9.
     */
    public function checkActiveCampaign(Request $request)
    {
        $message = strtolower(trim($request->input('message', '')));
        $whatsappNumber = $this->normalizePhoneNumber($request->input('whatsapp_number', ''));

        $whatsappNumber = $this->normalizePhoneNumber($request->input('whatsapp_number', ''));


        $message = strtolower(trim($request->input('message', '')));
        $isSiResponse = in_array($message, ['si', 'sí', 'yes', 'ok', 'dale', 'va', 'confirmo', 'asistir', 'vale', 'claro', 'acepto']);

        $persona = Persona::where('numero_celular', $whatsappNumber)->first();

        if (!$persona) {
            return response()->json([
                'has_active_campaign' => false,
                'reason' => 'persona_not_found'
            ]);
        }

        // 1. Check for ONGOING interaction (Active Session in Flow 9)
        if ($persona->last_interacted_event_id && $persona->last_interaction_at) {
            $interactionAt = \Carbon\Carbon::parse($persona->last_interaction_at);
            if ($interactionAt->diffInMinutes(now()) < 30) {
                $event = \App\Models\Event::find($persona->last_interacted_event_id);
                if ($event && $event->status !== 'completed' && !\Carbon\Carbon::parse($event->date)->endOfDay()->isPast()) {
                    // Update heartbeat and keep forwarding
                    $persona->update(['last_interaction_at' => now()]);
                    return response()->json([
                        'has_active_campaign' => true,
                        'event_id' => $event->id,
                        'persona_id' => $persona->id,
                        'reason' => 'active_interaction_ongoing'
                    ]);
                }
            }
        }

        // 2. Check for PENDING invitation
        if ($persona->last_invited_event_id && $isSiResponse) {
            $event = \App\Models\Event::find($persona->last_invited_event_id);

            // AUTO-PILOT: If the invited event was deleted, pick the most recent active event automatically
            if (!$event) {
                $event = \App\Models\Event::where('status', 'active')
                    ->where('date', '>=', now()->format('Y-m-d'))
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if ($event && $event->status !== 'completed' && !\Carbon\Carbon::parse($event->date)->endOfDay()->isPast()) {
                // Initialize interaction session
                $persona->update([
                    'last_interacted_event_id' => $event->id,
                    'last_interaction_at' => now(),
                    'last_invited_event_id' => null
                ]);

                return response()->json([
                    'has_active_campaign' => true,
                    'event_id' => $event->id,
                    'persona_id' => $persona->id,
                    'reason' => 'matched_invited_event'
                ]);
            }
        }

        return response()->json([
            'has_active_campaign' => false,
            'reason' => 'no_active_context'
        ]);
    }

    /**
     * Get dynamic AI Agent configuration (Master Prompt)
     */
    public function getAIConfig(Request $request)
    {
        // Sanitation: Strip any non-numeric characters (like dashes '-' found in n8n nodes)
        $eventId = preg_replace('/[^0-9]/', '', (string)$request->input('event_id'));
        $personaId = preg_replace('/[^0-9]/', '', (string)$request->input('persona_id'));

        // Ultra Rescue Mode: Ensure we find an event context no matter what
        if (!$eventId || !is_numeric($eventId)) {
            $persona = Persona::find($personaId);
            if ($persona) {
                $eventId = $persona->last_interacted_event_id ?? $persona->last_invited_event_id;
            }
            
            // Last resort: If still no ID, find the most relevant upcoming/active event
            if (!$eventId) {
                $eventId = \App\Models\Event::where('status', 'active')
                    ->where('date', '>=', now()->format('Y-m-d'))
                    ->orderBy('date', 'asc')
                    ->value('id');
            }
        }

        // Final sanitation
        $eventId = is_numeric($eventId) ? (int)$eventId : null;

        if (!$eventId) {
            return response()->json([
                'success' => false, 
                'message' => 'Missing or invalid event_id'
            ], 400);
        }

        $event = \App\Models\Event::find($eventId);
        $persona = Persona::with('mascotas')->find($personaId);

        if (!$event || !$persona) {
            return response()->json(['success' => false, 'message' => 'Event or Persona not found'], 404);
        }

        $cacheKey = "ai_config_{$eventId}_{$personaId}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($event, $persona, $eventId, $personaId) {
            $eventName = $event->detail; // In the DB it's 'detail'
            $eventTopic = $event->detail;
            $eventLocation = $event->street . ', ' . $event->neighborhood;
            
            $petList = $persona->mascotas->map(fn($p) => "{$p->nombre} ({$p->tipo})")->join(', ');

            $unitLabel = $event->slot_unit_name ?? 'mesa';

            $prompt = "Eres MetrixBot, el asistente de IA VIP para el sistema de eventos. 🚀\n\n"
                    . "--- CONTEXTO DEL EVENTO ---\n"
                    . "EVENTO: {$eventName}\n"
                    . "UBICACIÓN: {$eventLocation}\n"
                    . "--- DATOS DEL CIUDADANO ---\n"
                    . "NOMBRE: {$persona->nombre} {$persona->apellido_paterno}\n"
                    . "UNIVERSO: {$persona->universe_type}\n"
                    . ($petList ? "MASCOTAS: {$petList}\n" : "NO TIENE MASCOTAS REGISTRADAS.\n")
                    . "----------------------------\n\n"
                    . "REGLAS DE ORO:\n"
                    . "1. NUNCA, bajo ninguna circunstancia, empieces tu respuesta con el signo '='.\n"
                    . "2. Sé conciso, amable y usa emojis.\n"
                    . "3. Tu objetivo es ayudar al ciudadano con este evento específico.\n"
                    . "4. Si preguntan por puntos, ganarán " . ($event->bonus_points_for_attendee ?? 5) . " PUNTOS.\n"
                    . "5. Informa que se le asignará una {$unitLabel} para su atención.\n"
                    . "6. Pide confirmación de asistencia si aún no lo han hecho.\n\n"
                    . "TONO: VIP, profesional y servicial.";

            return response()->json([
                'success' => true,
                'master_prompt' => $prompt,
                'event_id' => $eventId,
                'persona_id' => $personaId
            ]);
        });
    }

    /**
     * Store complex Persona data (SuperPersona CRM Universal)
     * Includes PostGIS geolocation and modular JSON universes/tags.
     */
    public function storeSuperPersona(Request $request)
    {
        try {
            // Alias support for CRM standardization
            if (!$request->has('whatsapp') && $request->has('whatsapp_number')) {
                $request->merge(['whatsapp' => $request->whatsapp_number]);
            }

            $tenantIdInput = $request->input('tenant_id');
            // Postgres Fix: Si el ID no es un UUID válido, lo ignoramos para evitar el error 22P02
            if ($tenantIdInput && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenantIdInput)) {
                $request->merge(['tenant_id' => null]);
            }

            $validated = $request->validate([
                'whatsapp' => 'required|string',
                'numero_telefono' => 'nullable|string',
                'curp' => 'nullable|string|max:18',
                'clave_elector' => 'nullable|string|max:18',
                'seccion' => 'nullable|string|max:10',
                'vigencia' => 'nullable|string|max:4',
                'tipo_sangre' => 'nullable|string|max:5',
                'cedula' => 'nullable|string|max:50',
                'nombre' => 'required|string',
                'apellido_paterno' => 'nullable|string|max:255',
                'apellido_materno' => 'nullable|string|max:255',
                'edad' => 'nullable|integer',
                'sexo' => 'nullable|in:H,M,O',
                'email' => 'nullable|email',
                'calle' => 'nullable|string|max:255',
                'numero_exterior' => 'nullable|string|max:50',
                'numero_interior' => 'nullable|string|max:50',
                'colonia' => 'nullable|string|max:255',
                'codigo_postal' => 'nullable|string|max:10',
                'municipio' => 'nullable|string|max:255',
                'estado' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'categoria' => 'nullable|string|max:255',
                'tarifa' => 'nullable|string|max:255',
                'servicios' => 'nullable|string|max:255',
                'tags' => 'nullable|array',
                'universes' => 'nullable|array',
                'metadata' => 'nullable|array',
                'notes' => 'nullable|string',
                'leader_id' => 'nullable|exists:personas,id',
                'tenant_id' => 'nullable', 
                'universe_type' => 'nullable|string|in:U1,U2,U3,U4',
                'sub_type' => 'nullable|string|max:255',
                'universe_group' => 'nullable|string|max:10',
                'region' => 'nullable|string|max:255',
                'cdz_version' => 'nullable|string|max:10',
                'cdz_expires_at' => 'nullable|date',
                'loyalty_balance' => 'nullable|integer'
            ]);

            $whatsapp = $this->normalizePhoneNumber($validated['whatsapp']);

            // Resolve redundant phone number
            $alternatePhone = !empty($validated['numero_telefono']) 
                ? $this->normalizePhoneNumber($validated['numero_telefono']) 
                : null;

            // If same as primary, don't store as alternate
            if ($alternatePhone === $whatsapp) {
                $alternatePhone = null;
            }

            // Robustness: Check for unique constraints across ALL tenants
            $duplicates = [];
            
            if (!empty($validated['curp'])) {
                $dup = Persona::withoutGlobalScopes()->where('curp', $validated['curp'])->where('numero_celular', '!=', $whatsapp)->exists();
                if ($dup) $duplicates[] = 'El CURP ya está registrado con otro número.';
            }

            if (!empty($validated['email'])) {
                $dup = Persona::withoutGlobalScopes()->where('email', $validated['email'])->where('numero_celular', '!=', $whatsapp)->exists();
                if ($dup) $duplicates[] = 'El correo electrónico ya está registrado con otro número.';
            }

            if (count($duplicates) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $duplicates),
                    'error_code' => 'DUPLICATE_ENTRY',
                    'errors' => $duplicates
                ], 422);
            }

            $personaData = [
                'curp' => $validated['curp'] ?? null,
                'clave_elector' => $validated['clave_elector'] ?? null,
                'seccion' => $validated['seccion'] ?? null,
                'cdz_expires_at' => $validated['vigencia'] ?? null,
                'tipo_sangre' => $validated['tipo_sangre'] ?? null,
                'categoria' => $validated['categoria'] ?? null,
                'tarifa' => $validated['tarifa'] ?? null,
                'servicios' => $validated['servicios'] ?? null,
                'cedula' => !empty($validated['cedula']) ? $validated['cedula'] : ('ID-' . \Illuminate\Support\Str::random(10)),
                'nombre' => $validated['nombre'],
                'apellido_paterno' => $validated['apellido_paterno'] ?? '',
                'apellido_materno' => $validated['apellido_materno'] ?? '',
                'edad' => $validated['edad'] ?? 0,
                'region' => $validated['region'] ?? '',
                'universe_group' => $validated['universe_group'] ?? 'I',
                'sub_type' => $validated['sub_type'] ?? null,
                'cdz_version' => $validated['cdz_version'] ?? '1',
            ];

            // Smart Name Splicing safety
            if (!empty($personaData['apellido_paterno']) && empty($personaData['apellido_materno'])) {
                $parts = explode(' ', trim((string)$personaData['apellido_paterno']));
                if (count($parts) >= 2) {
                    $personaData['apellido_paterno'] = $parts[0];
                    $personaData['apellido_materno'] = implode(' ', array_slice($parts, 1));
                }
            }

            $personaUpdateData = array_merge($personaData, [
                'sexo' => $validated['sexo'] ?? 'O',
                'email' => $validated['email'] ?? null,
                'calle' => $validated['calle'] ?? '',
                'numero_exterior' => $validated['numero_exterior'] ?? '',
                'numero_interior' => $validated['numero_interior'] ?? '',
                'colonia' => $validated['colonia'] ?? '',
                'codigo_postal' => $validated['codigo_postal'] ?? '',
                'municipio' => $validated['municipio'] ?? '',
                'estado' => $validated['estado'] ?? '',
                'numero_telefono' => $alternatePhone, // Fix redundancy
                'universe_type' => $validated['universe_type'] ?? 'U1',
                'is_leader' => ($validated['universe_type'] ?? 'U1') === 'U3',
                'tags' => $validated['tags'] ?? [],
                'universes' => $validated['universes'] ?? [$validated['universe_type'] ?? 'U1'], // Sync universes
                'loyalty_balance' => $validated['loyalty_balance'] ?? 0,
                'metadata' => $validated['metadata'] ?? [],
                'notes' => $validated['notes'] ?? null,
                'leader_id' => $validated['leader_id'] ?? null,
                'tenant_id' => $validated['tenant_id'] ?? (app()->bound('tenant_id') ? app('tenant_id') : \App\Models\Tenant::first()?->id)
            ]);

            if (Schema::hasColumn('personas', 'sub_type')) {
                $personaUpdateData['sub_type'] = $validated['sub_type'] ?? null;
            }

            $persona = Persona::withoutGlobalScopes()->updateOrCreate(
                ['numero_celular' => $whatsapp],
                $personaUpdateData
            );

            // Actualizar Geo (Postgres PostGIS / MySQL Spatial)
            if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
                try {
                    $lat = (float)$validated['latitude'];
                    $lng = (float)$validated['longitude'];
                    $driver = DB::connection()->getDriverName();
                    
                    if ($driver === 'pgsql') {
                        // En Postgres intentamos ambos por si la columna se llama geom o location
                        DB::statement("UPDATE personas SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?", [$lng, $lat, $persona->id]);
                    } else {
                        DB::statement("UPDATE personas SET location = ST_GeomFromText(?, 4326) WHERE id = ?", ["POINT($lng $lat)", $persona->id]);
                    }
                } catch (\Throwable $geoEx) {
                    Log::warning('Spatial update ignored: ' . $geoEx->getMessage());
                }
            }

            if ($persona->wasRecentlyCreated) {
                try {
                    $wspservice = app(\App\Services\WhatsAppNotificationService::class);
                    $wspservice->sendGreetingNewCitizen($persona);
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp greeting failed: ' . $e->getMessage());
                }
            }
            return response()->json([
                'success' => true,
                'message' => 'Registro exitoso: ' . $persona->nombre,
                'persona_id' => $persona->id,
                'actual_tenant_id' => $persona->tenant_id, // Devolvemos el GUID real
                'referral_code' => $persona->referral_code
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . implode(' ', \Illuminate\Support\Arr::flatten($e->errors())),
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('SuperPersona CRITICAL ERROR: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error crítico en registro: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * API de Renderizado de Formularios (Pilar 2)
     * Retorna el schema del formulario dinámico para este evento específico.
     * Soporta herencia: Evento -> Tipo de Evento -> Global Default
     */
    public function getRegistrationForm(string $id): \Illuminate\Http\JsonResponse
    {
        $event = \App\Models\Event::with('eventType')->findOrFail($id);
    
        // Herarquía de Schema
        $schema = $event->form_schema 
            ?? $event->eventType?->default_form_schema 
            ?? $this->getDefaultCRMRegistrationSchema();
    
        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'name' => $event->detail,
                'type_id' => $event->event_type_id,
                'type_name' => $event->eventType?->name ?? 'Evento General',
                'type_slug' => $event->eventType?->slug ?? 'general',
                'icon' => $event->eventType?->icon ?? 'pi pi-calendar',
                'requires_appointment' => $event->eventType?->requires_appointment ?? false,
                'has_beneficiaries' => $event->eventType?->has_beneficiaries ?? false,
                'beneficiary_label' => $event->eventType?->beneficiary_label ?? 'Beneficiario',
            ],
            'form' => [
                'schema' => $schema,
                'success_message' => $event->success_message ?? $event->eventType?->success_message ?? "¡Registro exitoso! Te esperamos en el evento.",
                'postal_code_lookup_url' => '/api/public/postal-code/{cp}',
            ],
            'version' => '2.1'
        ]);
    }

    /**
     * Recovery API for unique QR codes (Lideres U3 y Militantes U4)
     */
    public function qrRecovery($whatsapp): \Illuminate\Http\JsonResponse
    {
        $whatsapp = $this->normalizePhoneNumber($whatsapp);
        $persona = Persona::where('numero_celular', $whatsapp)->first();

        if (!$persona) {
            return response()->json([
                'success' => false,
                'message' => 'Ciudadano no encontrado en el sistema.'
            ], 404);
        }

        $results = [];

        // 1. Check for Leader QR (QR2-L) - Latest Active Event
        $leaderQr = \App\Models\QrCode::where('persona_id', $persona->id)
            ->where('type', 'QR2-L')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($leaderQr) {
            $results['leader_qr'] = [
                'code' => $leaderQr->code,
                'event_name' => $leaderQr->event?->detail ?? 'Evento',
                'image_url' => url('api/qr/generate/' . $leaderQr->code)
            ];
        }

        // 2. Check for Militant QR (QR-MILITANT) - Latest Campaign
        $militantQr = \App\Models\QrCode::where('persona_id', $persona->id)
            ->where('type', 'QR-MILITANT')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($militantQr) {
            $results['militant_qr'] = [
                'code' => $militantQr->code,
                'campaign_name' => $militantQr->campaign?->nombre ?? 'Campaña',
                'image_url' => url('api/qr/generate/' . $militantQr->code)
            ];
        }

        if (empty($results)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron QRs únicos activos para este número.',
                'universe' => $persona->universe_type
            ], 404);
        }

        return response()->json([
            'success' => true,
            'persona' => $persona->nombre,
            'universe' => $persona->universe_type,
            'qrs' => $results
        ]);
    }

    /**
     * Fallback default schema for events without specific configuration (CRM Standard)
     */
    private function getDefaultCRMRegistrationSchema(): array
    {
        return [
            ['key' => 'nombre', 'label' => 'Nombre(s)', 'type' => 'text', 'required' => true, 'order' => 1],
            ['key' => 'apellido_paterno', 'label' => 'Apellido Paterno', 'type' => 'text', 'required' => true, 'order' => 2],
            ['key' => 'apellido_materno', 'label' => 'Apellido Materno', 'type' => 'text', 'required' => false, 'order' => 3],
            ['key' => 'whatsapp_number', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true, 'order' => 4],
            ['key' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => false, 'order' => 5],
        ];
    }

    /**
     * Check if event capacity has reached 80% and trigger FLOW 12.
     */
    protected function check80PercentCapacity(Event $event)
    {
        if (!$event->max_capacity) return;

        $count = $event->attendees()->count();
        $percentage = ($count / $event->max_capacity) * 100;

        if ($percentage >= 80 && $percentage < 85) { // Only trigger once around 80%
            $webhookUrl = config('services.n8n.webhook_flow12_capacity_url') ?? 'https://n8n.soymetrix.com/webhook/capacity-alert-80-enterprise';
            
            try {
                \Illuminate\Support\Facades\Http::timeout(5)->post($webhookUrl, [
                    'event_id' => $event->id,
                    'nombre_evento' => $event->detail,
                    'current_count' => $count,
                    'max_capacity' => $event->max_capacity,
                    'percentage' => round($percentage, 1)
                ]);
                \Log::info("FLOW 12 capacity alert triggered for event {$event->id}");
            } catch (\Exception $e) {
                \Log::error('Failed to trigger FLOW 12 capacity alert: ' . $e->getMessage());
            }
        }
    }

    /**
     * Confirma una reservación desde el Bot de n8n
     */
    public function confirmReservation(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'event_id' => 'required|exists:events,id',
            'persona_id' => 'required|exists:personas,id',
            'custom_fields' => 'nullable|array',
            'event_slot_id' => 'nullable|exists:event_slots,id'
        ]);

        try {
            DB::beginTransaction();

            $persona = Persona::findOrFail($data['persona_id']);
            $event = Event::findOrFail($data['event_id']);

            // Crear la cita/reservación
            $appointment = \App\Models\Appointment::create([
                'event_id' => $event->id,
                'persona_id' => $persona->id,
                'event_slot_id' => $data['event_slot_id'] ?? null,
                'status' => 'scheduled',
                'qr_code_token' => bin2hex(random_bytes(16)), // Token único para su QR de cita
                'assigned_location' => trim("{$event->street} {$event->number}, {$event->neighborhood}"),
            ]);

            // Si hay campos personalizados (preguntas del bot), los guardamos como metadatos si fuera necesario
            // Por ahora, registramos al asistente
            \App\Models\EventAttendee::updateOrCreate(
                ['event_id' => $event->id, 'persona_id' => $persona->id],
                ['status' => 'registered']
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reservación confirmada exitosamente',
                'appointment_id' => $appointment->id,
                'qr_token' => $appointment->qr_code_token
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar reservación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene o crea una sesión de WhatsApp para seguimiento
     */
    public function getWhatsAppSession(Request $request): \Illuminate\Http\JsonResponse
    {
        $phone = $request->input('phone');
        $eventId = $request->input('event_id');

        $session = \App\Models\WhatsAppSession::firstOrCreate(
            ['phone_number' => $phone],
            [
                'session_id' => bin2hex(random_bytes(8)),
                'conversation_state' => 'IDLE',
                'current_step' => 0,
                'expires_at' => now()->addHours(24)
            ]
        );

        if ($eventId && $session->event_id != $eventId) {
            $session->update([
                'event_id' => $eventId,
                'conversation_state' => 'RESERVING',
                'current_step' => 0,
                'context_data' => []
            ]);
        }

        return response()->json([
            'success' => true,
            'session' => $session->load('event')
        ]);
    }

    /**
     * Actualiza el progreso de la sesión
     */
    public function updateWhatsAppSession(Request $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->input('id');
        $updates = $request->only(['conversation_state', 'current_step', 'context_data', 'metadata']);

        $session = \App\Models\WhatsAppSession::findOrFail($id);
        $session->update($updates);

        return response()->json(['success' => true]);
    }
}
