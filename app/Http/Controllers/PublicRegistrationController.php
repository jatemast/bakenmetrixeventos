<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicRegistrationController extends Controller
{
    /**
     * Check if WhatsApp number exists and return persona data
     */
    public function checkWhatsApp(Request $request)
    {
        $validator = Validator::make($request->all(), [
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
                    'universe_type' => $persona->universe_type ?? 'U1'
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'maternal_name' => 'nullable|string|max:255',
            'whatsapp_number' => 'required|string|unique:personas,numero_celular',
            'email' => 'nullable|email|unique:personas,email',
            'identification_number' => 'nullable|string|unique:personas,cedula',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:H,M,O',
            'address' => 'nullable|string',
            'age' => 'nullable|integer|min:0|max:150',
            // Optional fields
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
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

            // Create Persona
            $persona = Persona::create([
                'cedula' => $request->identification_number ?? 'ID-' . Str::random(10),
                'nombre' => $request->name,
                'apellido_paterno' => $request->last_name,
                'apellido_materno' => $request->maternal_name ?? '',
                'edad' => $request->age ?? 0,
                'sexo' => $request->gender ?? 'O',
                'calle' => $request->address ?? '',
                'numero_exterior' => '',
                'numero_interior' => '',
                'colonia' => $request->city ?? '',
                'codigo_postal' => $request->postal_code ?? '',
                'municipio' => $request->city ?? '',
                'estado' => $request->state ?? '',
                'numero_celular' => $whatsappNumber,
                'numero_telefono' => $whatsappNumber,
                'email' => $request->email,
                'universe_type' => 'U1', // Default to citizen
                'is_leader' => false,
                'loyalty_points' => 0,
                'fecha_nacimiento' => $request->birth_date,
            ]);

            // Create User account for portal access
            if ($request->email) {
                $user = User::create([
                    'name' => $request->name . ' ' . $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($whatsappNumber), // Temporary password
                    'role' => 'user',
                    'persona_id' => $persona->id,
                ]);

                // Send welcome email with credentials (implement later)
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful!',
                'persona' => [
                    'id' => $persona->id,
                    'name' => $persona->nombre,
                    'full_name' => $persona->nombre . ' ' . $persona->apellido_paterno,
                    'whatsapp_number' => $persona->numero_celular,
                    'email' => $persona->email,
                    'loyalty_points' => $persona->loyalty_points,
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Public registration error: ' . $e->getMessage());
            
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
        $validator = Validator::make($request->all(), [
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
        $validator = Validator::make($request->all(), [
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
     * Normalize phone number format
     */
    private function normalizePhoneNumber($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present (assuming Mexico +52)
        if (strlen($phone) == 10) {
            $phone = '52' . $phone;
        }
        
        return $phone;
    }
}
