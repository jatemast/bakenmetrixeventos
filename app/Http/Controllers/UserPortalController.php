<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\QrCode;
use App\Models\BonusPointHistory;
use App\Models\Redemption;
use App\Models\PortalSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * UserPortalController
 * 
 * Citizen Self-Service Portal API
 * Provides endpoints for citizens to view their profile, events, 
 * attendance history, loyalty points, and manage preferences.
 */
class UserPortalController extends Controller
{
    /**
     * Request OTP code for portal access
     * Generates OTP and triggers n8n webhook to send via WhatsApp
     */
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'whatsapp_number' => 'required|string|min:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find persona by phone
        $persona = $this->findPersonaByPhone($request->whatsapp_number);

        if (!$persona) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró una cuenta con este número de WhatsApp'
            ], 404);
        }

        // Rate limiting: Check for recent OTP requests (max 3 per 15 minutes)
        $recentRequests = PortalSession::where('persona_id', $persona->id)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($recentRequests >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Por favor espera 15 minutos.'
            ], 429);
        }

        // Generate OTP
        $otpCode = PortalSession::generateOtp();

        // Create or update portal session
        $session = PortalSession::updateOrCreate(
            [
                'persona_id' => $persona->id,
                'is_verified' => false,
            ],
            [
                'whatsapp_number' => $persona->numero_celular,
                'otp_code' => $otpCode,
                'otp_expires_at' => now()->addMinutes(5),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        // Trigger n8n webhook to send OTP via WhatsApp
        $this->sendOtpViaWebhook($persona, $otpCode);

        return response()->json([
            'success' => true,
            'message' => 'Código de verificación enviado a tu WhatsApp',
            'expires_in' => 300, // 5 minutes in seconds
            'phone_hint' => '***' . substr($persona->numero_celular, -4), // Show last 4 digits
        ]);
    }

    /**
     * Verify OTP code and create session
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'whatsapp_number' => 'required|string|min:10',
            'otp_code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $persona = $this->findPersonaByPhone($request->whatsapp_number);

        if (!$persona) {
            return response()->json([
                'success' => false,
                'message' => 'Número de WhatsApp no encontrado'
            ], 404);
        }

        // Find pending session with matching OTP
        $session = PortalSession::where('persona_id', $persona->id)
            ->where('is_verified', false)
            ->where('otp_code', $request->otp_code)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (!$session) {
            // Log failed attempt (could implement lockout after X failures)
            return response()->json([
                'success' => false,
                'message' => 'Código inválido o expirado. Por favor solicita uno nuevo.'
            ], 401);
        }

        // Mark session as verified and generate token
        $session->markVerified();

        return response()->json([
            'success' => true,
            'message' => 'Verificación exitosa',
            'session_token' => $session->session_token,
            'expires_at' => $session->session_expires_at,
        ]);
    }

    /**
     * Validate session token middleware helper
     */
    private function validateSession(Request $request)
    {
        $token = $request->header('X-Portal-Token') ?? $request->input('session_token');

        if (!$token) {
            return null;
        }

        return PortalSession::findValidSession($token);
    }

    /**
     * Send OTP via n8n webhook
     */
    private function sendOtpViaWebhook(Persona $persona, string $otpCode)
    {
        $webhookUrl = config('services.n8n.portal_otp_webhook');

        if (!$webhookUrl) {
            // Fallback: Log the OTP (for development)
            \Log::info("Portal OTP for {$persona->numero_celular}: {$otpCode}");
            return;
        }

        try {
            Http::timeout(10)->post($webhookUrl, [
                'whatsapp_number' => $persona->numero_celular,
                'otp_code' => $otpCode,
                'persona_name' => trim($persona->nombre . ' ' . $persona->apellido_paterno),
                'expires_in_minutes' => 5,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send OTP webhook: " . $e->getMessage());
        }
    }

    /**
     * Logout / invalidate session
     */
    public function logout(Request $request)
    {
        $session = $this->validateSession($request);

        if ($session) {
            $session->update([
                'session_token' => null,
                'session_expires_at' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    /**
     * Get citizen profile by WhatsApp number
     * Returns full profile with loyalty balance and stats
     * SECURED: Requires valid session token
     */
    public function getProfile(Request $request)
    {
        // Validate session token
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada. Por favor inicia sesión nuevamente.'
            ], 401);
        }

        $persona = $session->persona;

        // Get loyalty balance (from persona field or calculated)
        $loyaltyBalance = $persona->loyalty_balance ?? BonusPointHistory::where('persona_id', $persona->id)
            ->sum('points_earned');

        // Get attendance stats
        $totalEvents = EventAttendee::where('persona_id', $persona->id)->count();
        $checkedInEvents = EventAttendee::where('persona_id', $persona->id)
            ->whereNotNull('checkin_at')
            ->count();

        // Get pending redemptions
        $pendingRedemptions = Redemption::where('persona_id', $persona->id)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'success' => true,
            'profile' => [
                'id' => $persona->id,
                'name' => $persona->nombre,
                'last_name' => $persona->apellido_paterno . ' ' . $persona->apellido_materno,
                'full_name' => trim($persona->nombre . ' ' . $persona->apellido_paterno . ' ' . $persona->apellido_materno),
                'whatsapp_number' => $persona->numero_celular,
                'email' => $persona->email,
                'universe_type' => $persona->universe_type ?? 'U1',
                'qr_type' => $persona->qr_type ?? 'QR1',
                'notification_preferences' => [
                    'whatsapp' => $persona->whatsapp_notifications ?? true,
                    'email' => $persona->email_notifications ?? false,
                ],
                'created_at' => $persona->created_at,
            ],
            'stats' => [
                'loyalty_balance' => (int) $loyaltyBalance,
                'total_events_invited' => $totalEvents,
                'events_attended' => $checkedInEvents,
                'attendance_rate' => $totalEvents > 0 ? round(($checkedInEvents / $totalEvents) * 100, 1) : 0,
                'pending_redemptions' => $pendingRedemptions,
            ]
        ]);
    }

    /**
     * Get upcoming events for a citizen
     * Returns events the persona is invited to that haven't happened yet
     * SECURED: Requires valid session token
     */
    public function getUpcomingEvents(Request $request)
    {
        // Validate session token
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada'
            ], 401);
        }

        $limit = $request->input('limit', 10);
        $persona = $session->persona;

        // Get upcoming events through QR codes or event attendance
        $upcomingEvents = Event::where('fecha', '>=', Carbon::now()->format('Y-m-d'))
            ->whereHas('qrCodes', function ($query) use ($persona) {
                $query->where('persona_id', $persona->id);
            })
            ->orWhereHas('attendances', function ($query) use ($persona) {
                $query->where('persona_id', $persona->id);
            })
            ->where('fecha', '>=', Carbon::now()->format('Y-m-d'))
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->limit($limit)
            ->get();

        $events = $upcomingEvents->map(function ($event) use ($persona) {
            $qrCode = QrCode::where('event_id', $event->id)
                ->where('persona_id', $persona->id)
                ->first();
            
            $attendance = EventAttendee::where('event_id', $event->id)
                ->where('persona_id', $persona->id)
                ->first();

            return [
                'id' => $event->id,
                'name' => $event->nombre,
                'description' => $event->descripcion,
                'date' => $event->fecha,
                'start_time' => $event->hora_inicio,
                'end_time' => $event->hora_fin,
                'location' => $event->direccion,
                'campaign' => [
                    'id' => $event->campaign->id ?? null,
                    'name' => $event->campaign->nombre ?? null,
                ],
                'qr_code' => $qrCode ? [
                    'code' => $qrCode->code,
                    'type' => $qrCode->type,
                ] : null,
                'attendance_status' => $attendance ? [
                    'checked_in' => $attendance->checkin_at !== null,
                    'checked_out' => $attendance->checkout_at !== null,
                    'checkin_at' => $attendance->checkin_at,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'events' => $events,
            'total' => $events->count()
        ]);
    }

    /**
     * Get event history for a citizen
     * Returns past events with attendance records
     * SECURED: Requires valid session token
     */
    public function getEventHistory(Request $request)
    {
        // Validate session token
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada'
            ], 401);
        }

        $limit = $request->input('limit', 20);
        $page = $request->input('page', 1);
        $persona = $session->persona;

        // Get past events with attendance
        $attendances = EventAttendee::where('persona_id', $persona->id)
            ->with(['event', 'event.campaign'])
            ->whereHas('event', function ($query) {
                $query->where('fecha', '<', Carbon::now()->format('Y-m-d'));
            })
            ->orderByDesc(
                Event::select('fecha')
                    ->whereColumn('events.id', 'event_attendances.event_id')
            )
            ->paginate($limit, ['*'], 'page', $page);

        $history = $attendances->map(function ($attendance) {
            return [
                'event' => [
                    'id' => $attendance->event->id,
                    'name' => $attendance->event->nombre,
                    'date' => $attendance->event->fecha,
                    'location' => $attendance->event->direccion,
                    'campaign_name' => $attendance->event->campaign->nombre ?? 'N/A',
                ],
                'attendance' => [
                    'checked_in' => $attendance->checkin_at !== null,
                    'checked_out' => $attendance->checkout_at !== null,
                    'checkin_at' => $attendance->checkin_at,
                    'checkout_at' => $attendance->checkout_at,
                    'points_earned' => $attendance->points_earned ?? 0,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'history' => $history,
            'pagination' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
            ]
        ]);
    }

    /**
     * Get loyalty point transactions for a citizen
     * SECURED: Requires valid session token
     */
    public function getLoyaltyHistory(Request $request)
    {
        // Validate session token
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada'
            ], 401);
        }

        $limit = $request->input('limit', 20);
        $persona = $session->persona;

        $transactions = BonusPointHistory::where('persona_id', $persona->id)
            ->with('event')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $balance = $persona->loyalty_balance ?? BonusPointHistory::where('persona_id', $persona->id)->sum('points_earned');

        $history = $transactions->map(function ($tx) {
            return [
                'id' => $tx->id,
                'points' => $tx->points_earned,
                'type' => $tx->points_earned > 0 ? 'earned' : 'redeemed',
                'description' => $tx->transaction_type,
                'event_name' => $tx->event->nombre ?? null,
                'date' => $tx->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'balance' => (int) $balance,
            'transactions' => $history
        ]);
    }

    /**
     * Get active redemptions/vouchers for a citizen
     * SECURED: Requires valid session token
     */
    public function getRedemptions(Request $request)
    {
        // Validate session token
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada'
            ], 401);
        }

        $persona = $session->persona;

        $query = Redemption::where('persona_id', $persona->id)
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $redemptions = $query->get()->map(function ($redemption) {
            return [
                'id' => $redemption->id,
                'voucher_code' => $redemption->voucher_code,
                'points_used' => $redemption->points_used,
                'reward_description' => $redemption->reward_description,
                'status' => $redemption->status,
                'expires_at' => $redemption->expires_at,
                'used_at' => $redemption->used_at,
                'created_at' => $redemption->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'redemptions' => $redemptions
        ]);
    }

    /**
     * Update notification preferences for a citizen
     * SECURED: Requires valid session token
     */
    public function updatePreferences(Request $request)
    {
        // Validate session token
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'whatsapp_notifications' => 'nullable|boolean',
            'email_notifications' => 'nullable|boolean',
            'email' => 'nullable|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $persona = $session->persona;

        $updates = [];

        if ($request->has('whatsapp_notifications')) {
            $updates['whatsapp_notifications'] = $request->whatsapp_notifications;
        }

        if ($request->has('email_notifications')) {
            $updates['email_notifications'] = $request->email_notifications;
        }

        if ($request->has('email') && $request->email) {
            // Check if email is already used by another persona
            $existingEmail = Persona::where('email', $request->email)
                ->where('id', '!=', $persona->id)
                ->exists();

            if ($existingEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already in use by another account'
                ], 422);
            }

            $updates['email'] = $request->email;
        }

        if (!empty($updates)) {
            $persona->update($updates);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'preferences' => [
                'whatsapp_notifications' => $persona->whatsapp_notifications ?? true,
                'email_notifications' => $persona->email_notifications ?? false,
                'email' => $persona->email,
            ]
        ]);
    }

    /**
     * Get event details with check-in information for a citizen
     * SECURED: Requires valid session token
     */
    public function getEventDetails(Request $request, $eventId)
    {
        // Validate session token
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada'
            ], 401);
        }

        $persona = $session->persona;

        $event = Event::with(['campaign'])->find($eventId);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        // Get persona's QR code for this event
        $qrCode = QrCode::where('event_id', $event->id)
            ->where('persona_id', $persona->id)
            ->first();

        // Get attendance record
        $attendance = EventAttendee::where('event_id', $event->id)
            ->where('persona_id', $persona->id)
            ->first();

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'name' => $event->nombre,
                'description' => $event->descripcion,
                'date' => $event->fecha,
                'start_time' => $event->hora_inicio,
                'end_time' => $event->hora_fin,
                'location' => $event->direccion,
                'status' => $event->status ?? 'scheduled',
                'campaign' => [
                    'id' => $event->campaign->id ?? null,
                    'name' => $event->campaign->nombre ?? null,
                ],
            ],
            'my_participation' => [
                'has_qr_code' => $qrCode !== null,
                'qr_code' => $qrCode ? $qrCode->code : null,
                'qr_type' => $qrCode ? $qrCode->type : null,
                'attendance_status' => $attendance ? [
                    'checked_in' => $attendance->checkin_at !== null,
                    'checked_out' => $attendance->checkout_at !== null,
                    'checkin_at' => $attendance->checkin_at,
                    'checkout_at' => $attendance->checkout_at,
                    'points_earned' => $attendance->points_earned ?? 0,
                ] : null,
            ],
        ]);
    }

    /**
     * Normalize phone number to standard format
     */
    private function normalizePhoneNumber($phoneNumber)
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Remove leading zeros
        $cleaned = ltrim($cleaned, '0');
        
        return $cleaned;
    }
    
    /**
     * Find persona by phone number (tries multiple formats)
     */
    private function findPersonaByPhone($phoneNumber)
    {
        $cleaned = $this->normalizePhoneNumber($phoneNumber);
        
        // Try exact match first
        $persona = Persona::where('numero_celular', $cleaned)->first();
        if ($persona) return $persona;
        
        // Try with 52 prefix for Mexican numbers
        if (strlen($cleaned) === 10) {
            $persona = Persona::where('numero_celular', '52' . $cleaned)->first();
            if ($persona) return $persona;
        }
        
        // Try without 52 prefix
        if (strlen($cleaned) === 12 && str_starts_with($cleaned, '52')) {
            $persona = Persona::where('numero_celular', substr($cleaned, 2))->first();
            if ($persona) return $persona;
        }
        
        return null;
    }
}
