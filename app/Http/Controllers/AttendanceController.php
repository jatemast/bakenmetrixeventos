<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventCheckinRequest;
use App\Http\Requests\EventCheckoutRequest;
use App\Models\Event;
use App\Models\Persona;
use App\Models\EventAttendee;
use App\Models\BonusPointHistory;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    protected $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Scan QR code for registration
     */
    public function scanRegister(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
            'persona_id' => 'required|exists:personas,id',
        ]);

        try {
            DB::beginTransaction();

            $validation = $this->qrCodeService->validateQrCode($request->qr_code);

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message']
                ], 400);
            }

            $qrCode = $validation['qr_code'];
            $event = $validation['event'];

            if ($qrCode->type !== 'QR1') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este código QR no es válido para registro. Use el código QR1.'
                ], 400);
            }

            $existing = EventAttendee::where('event_id', $event->id)
                ->where('persona_id', $request->persona_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta persona ya está registrada para este evento.'
                ], 400);
            }

            $persona = Persona::findOrFail($request->persona_id);

            EventAttendee::create([
                'event_id' => $event->id,
                'persona_id' => $request->persona_id,
                'attendance_status' => 'registered',
                'registered_at' => now(),
            ]);

            $qrCode->incrementScan();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registro exitoso.',
                'data' => [
                    'event_name' => $event->detail,
                    'event_date' => $event->date,
                    'persona_name' => $persona->nombre . ' ' . $persona->apellido,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration scan error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el registro.'
            ], 500);
        }
    }

    /**
     * Scan QR code for entry
     */
    public function scanEntry(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
            'persona_id' => 'required|exists:personas,id',
        ]);

        try {
            DB::beginTransaction();

            $validation = $this->qrCodeService->validateQrCode($request->qr_code);

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message']
                ], 400);
            }

            $qrCode = $validation['qr_code'];
            $event = $validation['event'];

            $validEntryTypes = ['QR2', 'QR2-L', 'QR-MILITANT'];
            if (!in_array($qrCode->type, $validEntryTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este código QR no es válido para entrada.'
                ], 400);
            }

            $attendee = EventAttendee::firstOrCreate(
                [
                    'event_id' => $event->id,
                    'persona_id' => $request->persona_id,
                ],
                [
                    'attendance_status' => 'registered',
                ]
            );

            if ($attendee->entry_timestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta persona ya ha ingresado al evento.',
                    'data' => ['entry_time' => $attendee->entry_timestamp]
                ], 400);
            }

            $attendee->update([
                'entry_timestamp' => now(),
                'entry_qr_id' => $qrCode->id,
                'attendance_status' => 'entered',
            ]);

            $qrCode->incrementScan();

            DB::commit();

            $persona = Persona::findOrFail($request->persona_id);

            return response()->json([
                'success' => true,
                'message' => '¡Bienvenido al evento!',
                'data' => [
                    'entry_time' => $attendee->entry_timestamp,
                    'event_name' => $event->detail,
                    'persona_name' => $persona->nombre . ' ' . $persona->apellido,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Entry scan error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la entrada.'
            ], 500);
        }
    }

    /**
     * Scan QR code for exit
     */
    public function scanExit(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
            'persona_id' => 'required|exists:personas,id',
        ]);

        try {
            DB::beginTransaction();

            $validation = $this->qrCodeService->validateQrCode($request->qr_code);

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message']
                ], 400);
            }

            $qrCode = $validation['qr_code'];
            $event = $validation['event'];

            if ($qrCode->type !== 'QR3') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este código QR no es válido para salida. Use el código QR3.'
                ], 400);
            }

            $attendee = EventAttendee::where('event_id', $event->id)
                ->where('persona_id', $request->persona_id)
                ->first();

            if (!$attendee) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró registro de asistencia para esta persona.'
                ], 404);
            }

            if (!$attendee->entry_timestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta persona no ha ingresado al evento aún.'
                ], 400);
            }

            if ($attendee->exit_timestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta persona ya ha salido del evento.',
                    'data' => ['exit_time' => $attendee->exit_timestamp]
                ], 400);
            }

            $attendee->update([
                'exit_timestamp' => now(),
                'exit_qr_id' => $qrCode->id,
                'attendance_status' => 'completed',
            ]);

            $entryTime = \Carbon\Carbon::parse($attendee->entry_timestamp);
            $exitTime = \Carbon\Carbon::parse($attendee->exit_timestamp);
            $durationMinutes = $exitTime->diffInMinutes($entryTime);

            $qrCode->incrementScan();

            DB::commit();

            $persona = Persona::findOrFail($request->persona_id);

            return response()->json([
                'success' => true,
                'message' => '¡Gracias por asistir!',
                'data' => [
                    'exit_time' => $attendee->exit_timestamp,
                    'duration_minutes' => $durationMinutes,
                    'event_name' => $event->detail,
                    'persona_name' => $persona->nombre . ' ' . $persona->apellido,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Exit scan error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la salida.'
            ], 500);
        }
    }

    /**
     * Get attendance status
     */
    public function getStatus($eventId, $personaId): JsonResponse
    {
        $attendee = EventAttendee::where('event_id', $eventId)
            ->where('persona_id', $personaId)
            ->with('persona', 'event')
            ->first();

        if (!$attendee) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'not_registered',
                    'message' => 'No registrado para este evento.'
                ]
            ]);
        }

        $duration = null;
        if ($attendee->entry_timestamp && $attendee->exit_timestamp) {
            $entryTime = \Carbon\Carbon::parse($attendee->entry_timestamp);
            $exitTime = \Carbon\Carbon::parse($attendee->exit_timestamp);
            $duration = $exitTime->diffInMinutes($entryTime);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $attendee->attendance_status,
                'duration_minutes' => $duration,
                'entry_time' => $attendee->entry_timestamp,
                'exit_time' => $attendee->exit_timestamp,
            ]
        ]);
    }

    /**
     * Handle event check-in.
     */
    public function checkin(EventCheckinRequest $request): JsonResponse
    {
        $data = $request->validated();

        $event = Event::where('checkin_code', $data['checkin_code'])->firstOrFail();
        
        // Buscar persona por numero_celular (WhatsApp)
        $persona = Persona::where('numero_celular', $data['whatsapp'])->first();

        if (!$persona) {
            return response()->json(['message' => 'El número de WhatsApp no está registrado en nuestra base de datos.'], 404);
        }

        // Verificar si ya ha hecho check-in en este evento
        $existingAttendance = EventAttendee::where('event_id', $event->id)
            ->where('persona_id', $persona->id)
            ->whereNotNull('checkin_at')
            ->first();

        if ($existingAttendance) {
            return response()->json(['message' => 'Ya has realizado el check-in para este evento.'], 409);
        }

        DB::beginTransaction();
        try {
            $leader = null;
            if (isset($data['referral_code'])) {
                $leader = Persona::where('referral_code', $data['referral_code'])
                    ->where('is_leader', true)
                    ->first();
                if (!$leader) {
                    throw new \Exception('Código de referido de líder no válido.');
                }
            }

            EventAttendee::create([
                'event_id' => $event->id,
                'persona_id' => $persona->id,
                'leader_id' => $leader ? $leader->id : null,
                'checkin_at' => now(),
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Asistencia registrada con éxito para el check-in.',
                'persona_name' => $persona->nombre . ' ' . $persona->apellido_paterno,
                'bonus_points_pending' => $event->bonus_points_for_attendee ?? 0
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar el check-in: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle event check-out.
     */
    public function checkout(EventCheckoutRequest $request): JsonResponse
    {
        $data = $request->validated();

        $event = Event::where('checkout_code', $data['checkout_code'])->firstOrFail();
        
        // Buscar persona por numero_celular (WhatsApp)
        $persona = Persona::where('numero_celular', $data['whatsapp'])->first();

        if (!$persona) {
            return response()->json(['message' => 'El número de WhatsApp no está registrado en nuestra base de datos.'], 404);
        }

        // Buscar el registro de asistencia que ya hizo check-in
        $attendance = EventAttendee::where('event_id', $event->id)
            ->where('persona_id', $persona->id)
            ->whereNotNull('checkin_at')
            ->whereNull('checkout_at') // Asegurarse de que no ha hecho checkout antes
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'No se encontró un registro de check-in activo para este evento.'], 404);
        }

        DB::beginTransaction();
        try {
            $attendance->checkout_at = now();
            $attendance->save();

            // Asignar puntos al asistente
            if ($event->bonus_points_for_attendee > 0) {
                $persona->increment('loyalty_balance', $event->bonus_points_for_attendee);
                BonusPointHistory::create([
                    'persona_id' => $persona->id,
                    'event_id' => $event->id,
                    'points_awarded' => $event->bonus_points_for_attendee,
                    'type' => 'attendance',
                    'description' => 'Puntos por asistencia al evento: ' . $event->detail,
                ]);
            }

            // Asignar puntos al líder referido (si existe)
            if ($attendance->leader_id && $event->bonus_points_for_leader > 0) {
                $leader = Persona::find($attendance->leader_id);
                if ($leader) {
                    $leader->increment('loyalty_balance', $event->bonus_points_for_leader);
                    BonusPointHistory::create([
                        'persona_id' => $leader->id,
                        'event_id' => $event->id,
                        'points_awarded' => $event->bonus_points_for_leader,
                        'type' => 'referral',
                        'description' => 'Puntos por referido de asistencia al evento: ' . $event->detail,
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Check-out registrado y puntos asignados con éxito.',
                'persona_name' => $persona->nombre . ' ' . $persona->apellido_paterno,
                'points_awarded' => $event->bonus_points_for_attendee ?? 0,
                'total_points' => $persona->loyalty_balance
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar el check-out: ' . $e->getMessage()], 500);
        }
    }
}
