<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Persona;
use App\Models\BonusPointHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for calculating and distributing leader bonus points (U3)
 * 
 * Leaders earn bonus points based on:
 * - Number of guests they invited who actually attended
 * - Event's bonus_points_for_leader configuration
 * 
 * Example: If 5 guests attended and event has 10 points per guest,
 * the leader earns 5 * 10 = 50 bonus points
 */
class BonusPointsService
{
    /**
     * Calculate and distribute bonus points for all leaders after event completion
     * 
     * @param Event $event
     * @return array Statistics about points distributed
     */
    public function distributeEventBonuses(Event $event): array
    {
        if ($event->points_distributed) {
            Log::info("Points already distributed for event {$event->id}");
            return [
                'success' => false,
                'message' => 'Points already distributed for this event',
                'leaders_processed' => 0,
                'total_points_distributed' => 0,
            ];
        }

        DB::beginTransaction();
        
        try {
            $stats = [
                'leaders_processed' => 0,
                'total_points_distributed' => 0,
                'attendee_points_distributed' => 0,
                'leader_bonus_points_distributed' => 0,
                'details' => [],
            ];

            // 1. Distribute points to all attendees
            $attendeeStats = $this->distributeAttendeePoints($event);
            $stats['attendee_points_distributed'] = $attendeeStats['total_points'];
            
            // 2. Distribute bonus points to leaders based on their guests
            $leaderStats = $this->distributeLeaderBonuses($event);
            $stats['leaders_processed'] = $leaderStats['leaders_count'];
            $stats['leader_bonus_points_distributed'] = $leaderStats['total_points'];
            $stats['details'] = $leaderStats['details'];
            
            $stats['total_points_distributed'] = 
                $stats['attendee_points_distributed'] + 
                $stats['leader_bonus_points_distributed'];

            // Mark event as points distributed
            $event->update(['points_distributed' => true]);

            DB::commit();
            
            Log::info("Successfully distributed points for event {$event->id}", $stats);
            
            return array_merge(['success' => true, 'message' => 'Points distributed successfully'], $stats);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Failed to distribute points for event {$event->id}: {$e->getMessage()}");
            
            return [
                'success' => false,
                'message' => 'Failed to distribute points: ' . $e->getMessage(),
                'leaders_processed' => 0,
                'total_points_distributed' => 0,
            ];
        }
    }

    /**
     * Distribute points to all event attendees
     * 
     * @param Event $event
     * @return array
     */
    private function distributeAttendeePoints(Event $event): array
    {
        $attendees = EventAttendee::where('event_id', $event->id)
            ->where('attendance_status', 'completed')
            ->whereNull('points_earned')
            ->with('persona')
            ->get();

        $totalPoints = 0;
        $attendeesProcessed = 0;

        foreach ($attendees as $attendee) {
            if (!$attendee->persona) {
                continue;
            }

            $points = $event->bonus_points_for_attendee ?? 50;
            
            // Award points to persona
            $attendee->persona->increment('loyalty_balance', $points);
            
            // Record in attendee record
            $attendee->update(['points_earned' => $points]);
            
            // Create bonus history record
            BonusPointHistory::create([
                'persona_id' => $attendee->persona_id,
                'event_id' => $event->id,
                'points' => $points,
                'points_awarded' => $points,  // Required field
                'type' => 'event_bonus',  // Required field
                'reason' => 'event_attendance',
                'description' => "Attended event: {$event->detail}",
            ]);

            $totalPoints += $points;
            $attendeesProcessed++;
        }

        return [
            'attendees_count' => $attendeesProcessed,
            'total_points' => $totalPoints,
        ];
    }

    /**
     * Distribute bonus points to leaders based on guests attended
     * 
     * @param Event $event
     * @return array
     */
    private function distributeLeaderBonuses(Event $event): array
    {
        // Get all attendees who have a leader assigned
        $attendeesWithLeaders = EventAttendee::where('event_id', $event->id)
            ->where('attendance_status', 'completed')
            ->whereNotNull('leader_id')
            ->with('leader')
            ->get();

        // Group by leader
        $leaderGuestCounts = [];
        foreach ($attendeesWithLeaders as $attendee) {
            $leaderId = $attendee->leader_id;
            
            if (!isset($leaderGuestCounts[$leaderId])) {
                $leaderGuestCounts[$leaderId] = [
                    'leader' => $attendee->leader,
                    'guest_count' => 0,
                    'guest_ids' => [],
                ];
            }
            
            $leaderGuestCounts[$leaderId]['guest_count']++;
            $leaderGuestCounts[$leaderId]['guest_ids'][] = $attendee->persona_id;
        }

        $bonusPerGuest = $event->bonus_points_for_leader ?? 10;
        $totalLeaderPoints = 0;
        $leadersProcessed = 0;
        $details = [];

        foreach ($leaderGuestCounts as $leaderId => $data) {
            $leader = $data['leader'];
            
            if (!$leader) {
                continue;
            }

            $guestCount = $data['guest_count'];
            $bonusPoints = $guestCount * $bonusPerGuest;

            // Award bonus points to leader
            $leader->increment('loyalty_balance', $bonusPoints);

            // Create bonus history record
            BonusPointHistory::create([
                'persona_id' => $leader->id,
                'event_id' => $event->id,
                'points' => $bonusPoints,
                'points_awarded' => $bonusPoints,  // Required field
                'type' => 'leader_bonus',  // Required field
                'reason' => 'leader_referral_bonus',
                'description' => "Leader bonus: {$guestCount} guests attended (×{$bonusPerGuest} pts/guest)",
                'metadata' => json_encode([
                    'guest_count' => $guestCount,
                    'points_per_guest' => $bonusPerGuest,
                    'guest_ids' => $data['guest_ids'],
                ]),
            ]);

            $totalLeaderPoints += $bonusPoints;
            $leadersProcessed++;
            
            $details[] = [
                'leader_id' => $leader->id,
                'leader_name' => $leader->nombre,
                'guests_attended' => $guestCount,
                'bonus_points' => $bonusPoints,
            ];

            Log::info("Leader {$leader->id} earned {$bonusPoints} points for {$guestCount} guests at event {$event->id}");
        }

        return [
            'leaders_count' => $leadersProcessed,
            'total_points' => $totalLeaderPoints,
            'details' => $details,
        ];
    }

    /**
     * Calculate potential bonus for a leader (preview, doesn't award points)
     * 
     * @param Persona $leader
     * @param Event $event
     * @return array
     */
    public function calculateLeaderBonus(Persona $leader, Event $event): array
    {
        $guestCount = EventAttendee::where('event_id', $event->id)
            ->where('leader_id', $leader->id)
            ->where('attendance_status', 'completed')
            ->count();

        $bonusPerGuest = $event->bonus_points_for_leader ?? 10;
        $totalBonus = $guestCount * $bonusPerGuest;

        return [
            'leader_id' => $leader->id,
            'leader_name' => $leader->nombre,
            'guests_attended' => $guestCount,
            'points_per_guest' => $bonusPerGuest,
            'total_bonus' => $totalBonus,
        ];
    }

    /**
     * Get leader performance statistics
     * 
     * @param Persona $leader
     * @return array
     */
    public function getLeaderStats(Persona $leader): array
    {
        $totalGuests = EventAttendee::where('leader_id', $leader->id)
            ->where('attendance_status', 'completed')
            ->count();

        $totalBonusPoints = BonusPointHistory::where('persona_id', $leader->id)
            ->where('reason', 'leader_referral_bonus')
            ->sum('points');

        $eventsWithGuests = EventAttendee::where('leader_id', $leader->id)
            ->where('attendance_status', 'completed')
            ->distinct('event_id')
            ->count('event_id');

        $recentBonuses = BonusPointHistory::where('persona_id', $leader->id)
            ->where('reason', 'leader_referral_bonus')
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'leader_id' => $leader->id,
            'leader_name' => $leader->nombre,
            'total_guests_invited' => $totalGuests,
            'total_bonus_points_earned' => $totalBonusPoints,
            'events_participated' => $eventsWithGuests,
            'current_loyalty_balance' => $leader->loyalty_balance,
            'recent_bonuses' => $recentBonuses->map(function ($bonus) {
                return [
                    'event_name' => $bonus->event?->detail ?? 'Unknown',
                    'points' => $bonus->points,
                    'guests_count' => json_decode($bonus->metadata)?->guest_count ?? 0,
                    'date' => $bonus->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ];
    }

    /**
     * Recalculate and distribute points for a specific event (admin override)
     * 
     * @param Event $event
     * @param bool $force Force redistribution even if already distributed
     * @return array
     */
    public function recalculateEventBonuses(Event $event, bool $force = false): array
    {
        if (!$force && $event->points_distributed) {
            return [
                'success' => false,
                'message' => 'Points already distributed. Use force=true to recalculate.',
            ];
        }

        // Reset points_distributed flag to allow redistribution
        $event->update(['points_distributed' => false]);

        // Clear previous bonus records for this event
        BonusPointHistory::where('event_id', $event->id)->delete();

        // Reset points_earned for attendees
        EventAttendee::where('event_id', $event->id)
            ->update(['points_earned' => null]);

        // Redistribute
        return $this->distributeEventBonuses($event);
    }
}
