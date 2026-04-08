<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Services\LoyaltyPointService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DistributeEventPointsJob implements ShouldQueue
{
    use Queueable;

    protected Event $event;

    /**
     * Create a new job instance.
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * Execute the job.
     */
    public function handle(LoyaltyPointService $loyaltyService): void
    {
        Log::info("Iniciando distribución masiva de puntos para el evento {$this->event->id}");

        // Obtener todos los asistentes que no han sido procesados
        $attendees = $this->event->attendees()->get();

        foreach ($attendees as $attendee) {
            try {
                $loyaltyService->processPointsForAttendee($attendee);
            } catch (\Exception $e) {
                Log::error("Error procesando puntos para asistente {$attendee->persona_id}: " . $e->getMessage());
            }
        }

        // Marcar evento como puntos distribuidos
        $this->event->update(['points_distributed' => true]);
        
        Log::info("Distribución de puntos completada para el evento {$this->event->id}");
    }
}
