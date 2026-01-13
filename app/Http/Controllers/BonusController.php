<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Persona;
use App\Services\BonusPointsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * BonusController
 * 
 * Handles bonus points distribution and leader statistics
 */
class BonusController extends Controller
{
    protected BonusPointsService $bonusService;

    public function __construct(BonusPointsService $bonusService)
    {
        $this->bonusService = $bonusService;
    }

    /**
     * Distribute bonuses for an event
     */
    public function distributeEventBonuses(string $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);
        
        $result = $this->bonusService->distributeEventBonuses($event);
        
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    /**
     * Recalculate and redistribute bonuses for an event
     */
    public function recalculateEventBonuses(Request $request, string $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);
        $force = $request->boolean('force', false);
        
        $result = $this->bonusService->recalculateEventBonuses($event, $force);
        
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    /**
     * Get leader bonus preview (without distributing)
     */
    public function leaderBonusPreview(string $personaId, string $eventId): JsonResponse
    {
        $leader = Persona::findOrFail($personaId);
        $event = Event::findOrFail($eventId);
        
        if (!$leader->is_leader) {
            return response()->json([
                'success' => false,
                'message' => 'Persona is not a leader',
            ], 400);
        }
        
        $preview = $this->bonusService->calculateLeaderBonus($leader, $event);
        
        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Get leader statistics
     */
    public function leaderStats(string $personaId): JsonResponse
    {
        $leader = Persona::findOrFail($personaId);
        
        if (!$leader->is_leader) {
            return response()->json([
                'success' => false,
                'message' => 'Persona is not a leader',
            ], 400);
        }
        
        $stats = $this->bonusService->getLeaderStats($leader);
        
        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Get all leaders with their performance stats
     */
    public function leaderLeaderboard(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);
        $sortBy = $request->input('sort_by', 'loyalty_balance');
        
        $leaders = Persona::where('is_leader', true)
            ->where('universe_type', 'U3')
            ->orderByDesc($sortBy)
            ->limit($limit)
            ->get();
        
        $leaderboard = $leaders->map(function ($leader, $index) {
            $stats = $this->bonusService->getLeaderStats($leader);
            return [
                'rank' => $index + 1,
                'leader_id' => $leader->id,
                'leader_name' => $leader->nombre,
                'referral_code' => $leader->referral_code,
                'stats' => $stats,
            ];
        });
        
        return response()->json([
            'success' => true,
            'leaderboard' => $leaderboard,
        ]);
    }
}
