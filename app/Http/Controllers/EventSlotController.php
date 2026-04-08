<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSlot;
use App\Models\Appointment;
use App\Services\EventSlotService;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventSlotController extends Controller
{
    public function __construct(
        private readonly EventSlotService $slotService,
        private readonly AppointmentService $appointmentService
    ) {}

    /**
     * Admin endpoint para crear la agenda del día
     */
    public function generate(Request $request, int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);
        
        try {
            $created = $this->slotService->generateSlots(
                event: $event,
                startTime: $request->input('start_time', '10:00'),
                endTime: $request->input('end_time', '18:00'),
                intervalMinutes: $request->input('interval', 20),
                capacity: $request->input('capacity', 4),
                unitName: $event->slot_unit_name ?? 'mesa'
            );

            $unitLabel = $event->slot_unit_name ?? 'espacio';

            return response()->json([
                'success' => true,
                'message' => "Se generaron exitosamente {$created} turnos ({$unitLabel}s) para el evento.",
                'event' => $event->detail,
                'total_slots' => $created
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Endpoint público para obtener slots disponibles (WhatsApp IA / Web)
     */
    public function getAvailableSlots(int $eventId): JsonResponse
    {
        $slots = EventSlot::where('event_id', $eventId)
            ->where('status', 'available')
            ->whereColumn('booked_count', '<', 'capacity')
            ->orderBy('start_time')
            ->get(['id', 'start_time', 'end_time', 'capacity', 'booked_count']);

        return response()->json([
            'success' => true,
            'data' => $slots
        ]);
    }

    /**
     * Reservar una cita (Genérica)
     */
    public function book(Request $request, int $slotId): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'target_id' => 'nullable|integer',
            'target_type' => 'nullable|string',
        ]);

        try {
            $appointment = $this->appointmentService->bookAppointment(
                $slotId,
                $request->input('persona_id'),
                $request->input('target_id'),
                $request->input('target_type')
            );

            return response()->json([
                'success' => true,
                'message' => 'Cita reservada con éxito.',
                'appointment' => $appointment->load(['event', 'slot', 'persona', 'target'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Iniciar proceso en mesa (Scan de anfitriona/médico)
     */
    public function startProcess(Request $request): JsonResponse
    {
        $request->validate([
            'qr_token' => 'required|string',
            'location' => 'required|string'
        ]);

        try {
            $appointment = $this->appointmentService->startService(
                $request->input('qr_token'),
                $request->input('location')
            );

            return response()->json([
                'success' => true,
                'message' => 'Proceso iniciado correctamente.',
                'appointment' => $appointment->load(['persona', 'target'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Finalizar proceso en mesa
     */
    public function completeProcess(int $appointmentId): JsonResponse
    {
        try {
            $appointment = $this->appointmentService->completeService($appointmentId);

            return response()->json([
                'success' => true,
                'message' => 'Servicio completado.',
                'duration_minutes' => $appointment->service_duration_minutes,
                'appointment' => $appointment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Consultar cita por QR
     */
    public function showByQr(string $qrCode): JsonResponse
    {
        $appointment = Appointment::where('qr_code_token', $qrCode)
            ->with(['event', 'slot', 'persona', 'target'])
            ->first();

        if (!$appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Código de ticket no válido.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'appointment' => $appointment
        ]);
    }
}
