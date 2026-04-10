<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
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

        $whatsappNumber = $this->normalizePhoneNumber($request->whatsapp_number);

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
            'children_details.*.name' => 'nullable|string|max:100',
            'children_details.*.age' => 'nullable|integer|min:0|max:5',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'leader_id' => 'nullable|exists:personas,id',
            'tenant_id' => 'nullable|exists:tenants,id',
            // Beneficiaries (legacy)
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

            $tenantId = $request->tenant_id ?? \App\Models\Tenant::first()?->id;

            // Create Persona with ALL fields for CRM
            $persona = Persona::create([
                'curp' => $request->curp,
                'clave_elector' => $request->clave_elector,
                'seccion' => $request->seccion,
                'vigencia' => $request->vigencia,
                'tipo_sangre' => $request->tipo_sangre,
                'categoria' => $request->category,
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
            $this->sendRegistrationConfirmation($persona);

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
     * Send registration confirmation via WhatsApp (n8n FLOW 4)
     */
    private function sendRegistrationConfirmation(Persona $persona): void
    {
        $webhookUrl = config('services.n8n.webhook_flow4_url') ?? 'https://n8n.soymetrix.com/webhook/enviar-mensaje';
        $metaToken = config('services.meta.token');
        $metaPhoneId = config('services.meta.phone_id');

        try {
            Http::timeout(10)->post($webhookUrl, [
                'token' => $metaToken,
                'phone_number_id' => $metaPhoneId,
                'destinatario' => $persona->numero_celular,
                'tipo' => 'text',
                'mensaje' => "🎉 *¡Bienvenido a METRIX, {$persona->nombre}!*\n\n"
                    . "Tu registro en nuestro CRM ha sido exitoso.\n\n"
                    . "📋 *Datos registrados:*\n"
                    . "• Nombre: {$persona->nombre} {$persona->apellido_paterno}\n"
                    . "• Cédula: {$persona->cedula}\n"
                    . "• WhatsApp: {$persona->numero_celular}\n\n"
                    . "🏆 Ahora puedes acumular puntos asistiendo a nuestros eventos.\n\n"
                    . "¡Gracias por registrarte!",
            ]);

            Log::info("Registration confirmation sent to {$persona->numero_celular}");
        } catch (\Exception $e) {
            Log::error("Failed to send registration confirmation: " . $e->getMessage());
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
                        'estado' => '',
                        'numero_celular' => $whatsappNumber,
                        'numero_telefono' => $whatsappNumber,
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
                'check_in_time' => null,
                'check_out_time' => null,
                'bonus_points' => 0,
                'status' => 'registered',
            ]);

            // Check if capacity reached 80% (FLOW 12)
            $this->check80PercentCapacity($event);

            return response()->json([
                'success' => true,
                'message' => 'Successfully registered for event!',
                'event' => [
                    'id' => $event->id,
                    'name' => $event->nombre,
                    'date' => $event->fecha,
                    'location' => $event->ubicacion,
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
        $eventId = $request->input('event_id');
        $personaId = $request->input('persona_id');

        $event = \App\Models\Event::find($eventId);
        $persona = Persona::with('mascotas')->find($personaId);

        if (!$event || !$persona) {
            return response()->json(['success' => false, 'message' => 'Event or Persona not found'], 404);
        }

        $cacheKey = "ai_config_{$eventId}_{$personaId}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($event, $persona, $eventId, $personaId) {
            $eventName = $event->nombre;
            $eventTopic = $event->detail;
            $eventLocation = $event->ubicacion ?? ($event->calle . ', ' . $event->colonia);
            
            $petList = $persona->mascotas->map(fn($p) => "{$p->nombre} ({$p->tipo})")->join(', ');

            $unitLabel = $event->slot_unit_name ?? 'mesa';

            $prompt = "Eres MetrixBot, el asistente de IA VIP para el sistema de eventos de la Municipalidad. 🚀\n\n"
                    . "--- CONTEXTO DEL EVENTO ---\n"
                    . "NOMBRE: {$eventName}\n"
                    . "TEMÁTICA: {$eventTopic}\n"
                    . "UBICACIÓN: {$eventLocation}\n"
                    . "--- DATOS DEL CIUDADANO ---\n"
                    . "NOMBRE: {$persona->nombre} {$persona->apellido_paterno}\n"
                    . "UNIVERSO: {$persona->universe_type}\n"
                    . ($petList ? "MASCOTAS: {$petList}\n" : "NO TIENE MASCOTAS REGISTRADAS.\n")
                    . "----------------------------\n\n"
                    . "REGLAS DE ORO:\n"
                    . "1. Sé conciso, amable y usa emojis.\n"
                    . "2. Tu objetivo principal es ayudar al ciudadano a agendar una cita para este evento específico.\n"
                    . "3. Si el ciudadano pregunta por los puntos, dile que ganará " . ($event->bonus_points_for_attendee ?? 5) . " PUNTOS por su asistencia completa.\n"
                    . "4. Pregúntale a quién de sus mascotas traerá si el evento es de bienestar animal.\n"
                    . "5. Informa que se le asignará una {$unitLabel} para su atención.\n"
                    . "6. Consulta siempre los horarios disponibles usando la herramienta slots antes de prometer una hora.\n\n"
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
        // Alias support for CRM standardization
        if (!$request->has('whatsapp') && $request->has('whatsapp_number')) {
            $request->merge(['whatsapp' => $request->whatsapp_number]);
        }

        $validated = $request->validate([
            'whatsapp' => 'required|string',
            'curp' => 'nullable|string|max:18',
            'clave_elector' => 'nullable|string|max:18',
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
            'tags' => 'nullable|array',
            'universes' => 'nullable|array',
            'metadata' => 'nullable|array',
            'notes' => 'nullable|string',
            'leader_id' => 'nullable|exists:personas,id'
        ]);

        $whatsapp = $this->normalizePhoneNumber($validated['whatsapp']);

        $personaData = [
            'curp' => $validated['curp'] ?? null,
            'clave_elector' => $validated['clave_elector'] ?? null,
            'vigencia' => $validated['vigencia'] ?? null,
            'tipo_sangre' => $validated['tipo_sangre'] ?? null,
            'cedula' => $validated['cedula'] ?? ('ID-' . \Illuminate\Support\Str::random(10)),
            'nombre' => $validated['nombre'],
            'apellido_paterno' => $validated['apellido_paterno'] ?? '',
            'apellido_materno' => $validated['apellido_materno'] ?? '',
            'edad' => $validated['edad'] ?? 0,
        ];

        // --- SMART NAME SPLICING ---
        // If paterno has multiple words and materno is empty, split them
        if (!empty($personaData['apellido_paterno']) && empty($personaData['apellido_materno'])) {
            $parts = explode(' ', trim($personaData['apellido_paterno']));
            if (count($parts) >= 2) {
                $personaData['apellido_paterno'] = $parts[0];
                $personaData['apellido_materno'] = implode(' ', array_slice($parts, 1));
            }
        }
        // If only nombre is provided and has multiple words, try to extract surnames
        if (empty($personaData['apellido_paterno']) && empty($personaData['apellido_materno'])) {
            $nameParts = explode(' ', trim($personaData['nombre']));
            if (count($nameParts) >= 3) {
                // Assume Firstname, FatherSurname, MotherSurname
                $personaData['nombre'] = $nameParts[0];
                $personaData['apellido_paterno'] = $nameParts[1];
                $personaData['apellido_materno'] = implode(' ', array_slice($nameParts, 2));
            } elseif (count($nameParts) === 2) {
                // Assume Firstname, FatherSurname
                $personaData['nombre'] = $nameParts[0];
                $personaData['apellido_paterno'] = $nameParts[1];
            }
        }

        $persona = Persona::updateOrCreate(
            ['numero_celular' => $whatsapp],
            array_merge($personaData, [
                'sexo' => $validated['sexo'] ?? 'O',
                'email' => $validated['email'] ?? null,
                'calle' => $validated['calle'] ?? '',
                'numero_exterior' => $validated['numero_exterior'] ?? '',
                'numero_interior' => $validated['numero_interior'] ?? '',
                'colonia' => $validated['colonia'] ?? '',
                'codigo_postal' => $validated['codigo_postal'] ?? '',
                'municipio' => $validated['municipio'] ?? '',
                'estado' => $validated['estado'] ?? 'Querétaro',
                'region' => '',
                'numero_telefono' => $whatsapp,
                'universe_type' => 'U1',
                'universe_group' => 'I',
                'is_leader' => false,
                'loyalty_balance' => 0,
                'tags' => $validated['tags'] ?? [],
                'universes' => $validated['universes'] ?? [],
                'metadata' => $validated['metadata'] ?? [],
                'notes' => $validated['notes'] ?? null,
                'leader_id' => $validated['leader_id'] ?? null,
                'updated_at' => now(),
                'tenant_id' => \App\Models\Tenant::first()?->id
            ])
        );

        // Actualizar Geo (PostGIS) si hay coordenadas
        if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
            $lat = $validated['latitude'];
            $lng = $validated['longitude'];
            
            DB::table('personas')
                ->where('id', $persona->id)
                ->update([
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint($lng, $lat), 4326)")
                ]);
        }

        // Si es un Censo 100% Nuevo, enviarle el Mensaje Inmediato por WhatsApp
        if ($persona->wasRecentlyCreated) {
            $wspservice = app(\App\Services\WhatsAppNotificationService::class);
            $wspservice->sendGreetingNewCitizen($persona);
        }

        return response()->json([
            'success' => true,
            'message' => 'Universo del Ciudadano ' . $persona->nombre . ' actualizado en 360°',
            'persona_id' => $persona->id
        ]);
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
}
