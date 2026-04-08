<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\EventSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use App\Services\WhatsAppNotificationService;

class AppointmentService
{
    private readonly WhatsAppNotificationService $whatsappService;
    private readonly LoyaltyRewardService $loyaltyRewardService;

    public function __construct(
        WhatsAppNotificationService $whatsappService,
        LoyaltyRewardService $loyaltyRewardService
    ) {
        $this->whatsappService = $whatsappService;
        $this->loyaltyRewardService = $loyaltyRewardService;
    }

    /**
     * Reserva una cita genérica para una persona vinculada a un sujeto opcional
     */
    public function bookAppointment(
        int $slotId, 
        int $personaId, 
        ?int $targetId = null, 
        ?string $targetType = null
    ): Appointment {
        return DB::transaction(function () use ($slotId, $personaId, $targetId, $targetType) {
            // Buscamos el slot con un bloqueo de fila (pessimistic locking)
            $slot = EventSlot::lockForUpdate()->findOrFail($slotId);

            // Validar capacidad
            if (!$slot->hasCapacity()) {
                throw new Exception("El horario seleccionado ya no tiene espacios disponibles.");
            }

            // Validar que el mismo sujeto no tenga otra cita en el mismo evento (si aplica)
            if ($targetId && $targetType) {
                $alreadyBooked = Appointment::where('event_id', $slot->event_id)
                    ->where('target_id', $targetId)
                    ->where('target_type', $targetType)
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if ($alreadyBooked) {
                    throw new Exception("Este sujeto ya tiene una cita registrada para este evento.");
                }
            }

            // Crear la cita
            $appointment = Appointment::create([
                'event_id' => $slot->event_id,
                'event_slot_id' => $slot->id,
                'persona_id' => $personaId,
                'target_id' => $targetId,
                'target_type' => $targetType,
                'qr_code_token' => 'TKT-' . strtoupper(Str::random(12)),
                'status' => 'pending'
            ]);

            // Incrementar contador de reservas en el slot
            $slot->increment('booked_count');

            // Si llegamos a la capacidad, marcamos como full
            if (!$slot->hasCapacity()) {
                $slot->update(['status' => 'full']);
            }

            // 1. Register as Attendee for the event if not already registered
            $event = $slot->event;
            $existingAttendee = $event->attendees()->where('persona_id', $personaId)->exists();
            if (!$existingAttendee) {
                $event->attendees()->create([
                    'persona_id' => $personaId,
                    'status' => 'registered'
                ]);
            }

            // Notificar por WhatsApp
            $this->whatsappService->sendAppointmentConfirmation($appointment);

            return $appointment;
        });
    }

    /**
     * Iniciar el proceso en sitio (Mesa/Lugar)
     */
    public function startService(string $qrCode, string $location): Appointment
    {
        $appointment = Appointment::where('qr_code_token', $qrCode)->firstOrFail();

        if ($appointment->status !== 'pending' && $appointment->status !== 'in_site') {
            throw new Exception("Esta cita ya fue procesada o cancelada.");
        }

        $appointment->update([
            'status' => 'in_site',
            'assigned_location' => $location,
            'started_at' => now(),
        ]);

        return $appointment;
    }

    /**
     * Finalizar el proceso en sitio
     */
    public function completeService(int $appointmentId): Appointment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        if ($appointment->status !== 'in_site' || !$appointment->started_at) {
            throw new Exception("La cita debe estar en proceso ('in_site') para finalizarla.");
        }

        $now = now();
        $duration = $appointment->started_at->diffInMinutes($now);

        $appointment->update([
            'status' => 'completed',
            'completed_at' => $now,
            'service_duration_minutes' => $duration
        ]);

        // AUTOMATIC LOYALTY REWARD
        $this->loyaltyRewardService->rewardCompletion($appointment);

        return $appointment;
    }

    /**
     * Cancelar una cita
     */
    public function cancelAppointment(int $appointmentId): bool
    {
        return DB::transaction(function () use ($appointmentId) {
            $appointment = Appointment::findOrFail($appointmentId);
            
            if ($appointment->status === 'cancelled') {
                return true;
            }

            $appointment->update(['status' => 'cancelled']);
            
            $slot = EventSlot::lockForUpdate()->find($appointment->event_slot_id);
            if ($slot) {
                $slot->decrement('booked_count');
                if ($slot->status === 'full') {
                    $slot->update(['status' => 'available']);
                }
            }

            return true;
        });
    }
}
