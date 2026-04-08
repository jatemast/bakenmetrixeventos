<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\Persona;
use App\Models\EventAttendee;
use App\Models\BonusPointHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyPointService
{
    /**
     * Procesa y distribuye los puntos para un asistente específico en un evento
     */
    public function processPointsForAttendee(EventAttendee $attendee): void
    {
        DB::transaction(function () use ($attendee) {
            $event = $attendee->event;
            $persona = $attendee->persona;

            // 1. Validar permanencia (si se definió en el evento)
            // Si el objetivo es "Inicio a Fin", verificamos duración
            if ($attendee->status !== 'completed' && $attendee->status !== 'present') {
                 Log::info("Asistente {$persona->id} no califica para puntos (Status: {$attendee->status})");
                 return;
            }

            // 2. Repartir puntos al Asistente (U1, U2, U4 ganan 5 puntos)
            // Según objetivos.md: Ciudadanos y Militantes ganan 5 puntos si están de inicio a fin.
            $pointsToAward = 0;
            if (in_array($persona->universe_type, ['U1', 'U2', 'U4'])) {
                $pointsToAward = $event->bonus_points_for_attendee ?? 5;
            }

            if ($pointsToAward > 0) {
                $this->awardPoints($persona, $pointsToAward, 'event_attendance', "Asistencia a evento: {$event->nombre}", $event->id);
                $this->notifyWhatsApp($persona, "🎁 *¡Felicidades {$persona->nombre}!* Has ganado *{$pointsToAward} puntos* por asistir al evento: *{$event->nombre}*.\n\n¡Sigue participando para acumular más beneficios!");
            }

            // 3. Repartir puntos al Líder (U3) si aplica
            // Si el asistente fue invitado por un líder (según leader_id en persona o en scan)
            $leaderId = $persona->leader_id ?? $attendee->leader_id;
            
            if ($leaderId) {
                $leader = Persona::find($leaderId);
                if ($leader && $leader->universe_type === 'U3') {
                    $leaderPoints = $event->bonus_points_for_leader ?? 3;
                    if ($leaderPoints > 0) {
                        $this->awardPoints($leader, $leaderPoints, 'leader_referral', "Tu invitado {$persona->nombre} asistió a: {$event->nombre}", $event->id);
                        $this->notifyWhatsApp($leader, "🏆 *¡Buen trabajo {$leader->nombre}!* Has ganado *{$leaderPoints} puntos* de bono porque tu invitado *{$persona->nombre}* asistió al evento: *{$event->nombre}*.");
                    }
                }
            }
        });
    }

    /**
     * Método genérico para otorgar puntos
     */
    private function awardPoints(Persona $persona, int $points, string $type, string $description, int $eventId): void
    {
        // Guardar en el historial
        BonusPointHistory::create([
            'persona_id' => $persona->id,
            'event_id' => $eventId,
            'points' => $points,
            'type' => $type,
            'description' => $description,
            'status' => 'approved'
        ]);

        // Actualizar el acumulado en la tabla personas
        $persona->increment('loyalty_balance', $points);
        
        Log::info("Otorgados {$points} puntos a {$persona->nombre} ({$persona->universe_type}) por {$type}");
    }

    /**
     * Enviar notificación vía WhatsApp (n8n FLOW 4)
     */
    private function notifyWhatsApp(Persona $persona, string $message): void
    {
        $webhookUrl = config('services.n8n.webhook_flow4_url') ?? 'https://n8n.soymetrix.com/webhook/enviar-mensaje';
        $metaToken = config('services.meta.token');
        $metaPhoneId = config('services.meta.phone_id');

        try {
            \Illuminate\Support\Facades\Http::timeout(10)->post($webhookUrl, [
                'token' => $metaToken,
                'phone_number_id' => $metaPhoneId,
                'destinatario' => $persona->numero_celular,
                'tipo' => 'text',
                'mensaje' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send points notification: " . $e->getMessage());
        }
    }
}
