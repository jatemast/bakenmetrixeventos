<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BonusPointHistory;
use App\Models\EventAttendee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyRewardService
{
    /**
     * Award points to the citizen and their leader upon vaccination completion.
     *
     * @param Appointment $appointment
     * @return void
     */
    public function rewardCompletion(Appointment $appointment): void
    {
        DB::transaction(function () use ($appointment) {
            $persona = $appointment->persona;
            $event = $appointment->event;

            if (!$persona || !$event) {
                return;
            }

            // 1. Reward the Citizen (Patient owner)
            // Points: 5 (from user request)
            $pointsForCitizen = 5;

            // Check if already rewarded for this specific appointment
            $alreadyRewarded = BonusPointHistory::where('persona_id', $persona->id)
                ->where('event_id', $event->id)
                ->where('type', 'attendance')
                ->where('metadata->appointment_id', $appointment->id)
                ->exists();

            if (!$alreadyRewarded) {
                $persona->increment('loyalty_balance', $pointsForCitizen);

                BonusPointHistory::create([
                    'persona_id' => $persona->id,
                    'event_id' => $event->id,
                    'points' => $pointsForCitizen,
                    'points_awarded' => $pointsForCitizen,
                    'type' => 'attendance',
                    'reason' => 'event_attendance',
                    'description' => "Vacunación completada para {$appointment->target?->nombre}",
                    'metadata' => ['appointment_id' => $appointment->id]
                ]);
            }

            // 2. Reward the Leader (if any)
            // Points: 3 per guest (from user request)
            if ($persona->leader_id) {
                $leader = $persona->leader;
                if ($leader) {
                    $pointsForLeader = 3;

                    $leader->increment('loyalty_balance', $pointsForLeader);

                    BonusPointHistory::create([
                        'persona_id' => $leader->id,
                        'event_id' => $event->id,
                        'points' => $pointsForLeader,
                        'points_awarded' => $pointsForLeader,
                        'type' => 'leader_bonus',
                        'reason' => 'leader_referral_bonus',
                        'description' => "Referido completó vacunación: {$persona->nombre}",
                        'metadata' => [
                            'referred_persona_id' => $persona->id,
                            'appointment_id' => $appointment->id
                        ]
                    ]);
                }
            }

            // 3. Mark attendee status as completed in the event
            EventAttendee::where('event_id', $event->id)
                ->where('persona_id', $persona->id)
                ->update(['attendance_status' => 'completed']);
        });
    }
}
