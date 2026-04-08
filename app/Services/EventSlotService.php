<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class EventSlotService
{
    /**
     * Genera automáticamente los slots para un evento.
     * Ejemplo: de 10:00 a 18:00, cada 20 mins, 4 mesas.
     */
    public function generateSlots(
        Event $event, 
        string $startTime = '10:00', 
        string $endTime = '18:00', 
        int $intervalMinutes = 20, 
        int $capacity = 4,
        string $unitName = 'mesa'
    ): int {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $slotsCreated = 0;

        DB::beginTransaction();
        try {
            // Limpiamos slots anteriores si el evento no tiene citas aún (Previene duplicados)
            if ($event->appointments()->exists()) {
                throw new Exception("No se pueden regenerar slots; el evento ya tiene citas agendadas.");
            }
            $event->slots()->delete();

            $currentSlotStart = $start->copy();

            while ($currentSlotStart < $end) {
                $currentSlotEnd = $currentSlotStart->copy()->addMinutes($intervalMinutes);

                // No crear un slot que exceda la hora de fin
                if ($currentSlotEnd > $end) {
                    break; 
                }

                EventSlot::create([
                    'event_id'   => $event->id,
                    'start_time' => $currentSlotStart->format('H:i:s'),
                    'end_time'   => $currentSlotEnd->format('H:i:s'),
                    'capacity'   => $capacity,
                    'status'     => 'available'
                ]);

                $currentSlotStart->addMinutes($intervalMinutes);
                $slotsCreated++;
            }

            DB::commit();
            return $slotsCreated;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
