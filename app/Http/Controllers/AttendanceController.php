<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventCheckinRequest;
use App\Http\Requests\EventCheckoutRequest;
use App\Models\Event;
use App\Models\Persona;
use App\Models\EventAttendee;
use App\Models\BonusPointHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Handle event check-in.
     */
    public function checkin(EventCheckinRequest $request): JsonResponse
    {
        $data = $request->validated();

        $event = Event::where('checkin_code', $data['checkin_code'])->firstOrFail();
        $persona = Persona::where('cedula', $data['cedula'])->firstOrFail();

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
            return response()->json(['message' => 'Asistencia registrada con éxito para el check-in.'], 200);

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
        $persona = Persona::where('cedula', $data['cedula'])->firstOrFail();

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
                $persona->increment('bonus_points', $event->bonus_points_for_attendee);
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
                    $leader->increment('bonus_points', $event->bonus_points_for_leader);
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
            return response()->json(['message' => 'Check-out registrado y puntos asignados con éxito.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar el check-out: ' . $e->getMessage()], 500);
        }
    }
}
