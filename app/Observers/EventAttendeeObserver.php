<?php

namespace App\Observers;

use App\Models\EventAttendee;
use App\Services\LoyaltyPointService;
use Illuminate\Support\Facades\Log;

class EventAttendeeObserver
{
    public function __construct(protected LoyaltyPointService $loyaltyService)
    {
    }

    /**
     * Handle the EventAttendee "updated" event.
     */
    public function updated(EventAttendee $attendee): void
    {
        // Detectar si se acaba de marcar el checkout
        if ($attendee->wasChanged('checkout_at') && !empty($attendee->checkout_at)) {
            Log::info("Checkout detectado para Persona: {$attendee->persona_id} en Evento: {$attendee->event_id}. Disparando motor de puntos.");
            
            try {
                $this->loyaltyService->processPointsForAttendee($attendee);
            } catch (\Exception $e) {
                Log::error("Error procesando puntos en el Observer: " . $e->getMessage());
            }
        }
    }
}
