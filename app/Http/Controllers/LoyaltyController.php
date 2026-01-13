<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\BonusPointHistory;
use App\Models\EventAttendee;
use App\Models\Redemption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LoyaltyController extends Controller
{
    /**
     * Get persona's points balance
     */
    public function getBalance($personaId): JsonResponse
    {
        try {
            $persona = Persona::findOrFail($personaId);

            return response()->json([
                'success' => true,
                'data' => [
                    'persona_id' => $persona->id,
                    'persona_name' => $persona->nombre . ' ' . $persona->apellido,
                    'total_points' => $persona->loyalty_balance ?? 0,
                    'universe_type' => $persona->universe_type,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el saldo de puntos.'
            ], 500);
        }
    }

    /**
     * Get persona's points history
     */
    public function getHistory($personaId): JsonResponse
    {
        try {
            $history = BonusPointHistory::where('persona_id', $personaId)
                ->with('event')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de puntos.'
            ], 500);
        }
    }

    /**
     * Add points manually (admin function)
     */
    public function addPoints(Request $request): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'points_earned' => 'required|integer|min:1',
            'transaction_type' => 'required|string|in:attendance,bonus,referral,manual',
            'description' => 'nullable|string',
            'event_id' => 'nullable|exists:events,id',
        ]);

        try {
            DB::beginTransaction();

            $persona = Persona::findOrFail($request->persona_id);
            $persona->increment('loyalty_balance', $request->points_earned);

            BonusPointHistory::create([
                'persona_id' => $request->persona_id,
                'event_id' => $request->event_id,
                'points_awarded' => $request->points_earned,
                'type' => $request->transaction_type,
                'description' => $request->description ?? 'Puntos añadidos manualmente',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Puntos añadidos exitosamente.',
                'data' => [
                    'new_balance' => $persona->fresh()->loyalty_balance,
                    'points_added' => $request->points_earned,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Add points error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al añadir puntos.'
            ], 500);
        }
    }

    /**
     * Redeem points
     */
    public function redeemPoints(Request $request): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'points_redeemed' => 'required|integer|min:1',
            'description' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $persona = Persona::findOrFail($request->persona_id);

            // Check if persona has enough points
            if ($persona->loyalty_balance < $request->points_redeemed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Puntos insuficientes.',
                    'data' => [
                        'current_balance' => $persona->loyalty_balance,
                        'required' => $request->points_redeemed,
                    ]
                ], 400);
            }

            // Deduct points
            $persona->decrement('loyalty_balance', $request->points_redeemed);

            // Create negative history entry for redemption
            BonusPointHistory::create([
                'persona_id' => $request->persona_id,
                'event_id' => null,
                'points_awarded' => -$request->points_redeemed,
                'type' => 'redemption',
                'description' => $request->description,
            ]);

            // Generate voucher code
            $voucherCode = Redemption::generateVoucherCode($request->persona_id);
            
            // Calculate expiration (30 days from now)
            $expiresAt = now()->addDays(30);

            // Create redemption record
            $redemption = Redemption::create([
                'voucher_code' => $voucherCode,
                'persona_id' => $request->persona_id,
                'points_redeemed' => $request->points_redeemed,
                'reward_description' => $request->description,
                'status' => 'pending',
                'redeemed_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            // Generate QR code for voucher
            $qrCodePath = $this->generateVoucherQr($redemption);
            $redemption->update(['qr_code_path' => $qrCodePath]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '✅ Puntos canjeados exitosamente. Voucher generado.',
                'data' => [
                    'new_balance' => $persona->fresh()->loyalty_balance,
                    'points_redeemed' => $request->points_redeemed,
                    'reward' => $request->description,
                    'voucher' => [
                        'code' => $voucherCode,
                        'qr_code_url' => url('storage/' . $qrCodePath),
                        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                        'status' => 'pending',
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Redeem points error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al canjear puntos.'
            ], 500);
        }
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(Request $request): JsonResponse
    {
        try {
            $limit = $request->query('limit', 10);
            $universeType = $request->query('universe');

            $query = Persona::query()
                ->select('id', 'nombre', 'apellido_paterno', 'apellido_materno', 'loyalty_balance', 'universe_type', 'municipio', 'estado')
                ->where('loyalty_balance', '>', 0);

            if ($universeType) {
                $query->where('universe_type', $universeType);
            }

            $leaderboard = $query
                ->orderBy('loyalty_balance', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($persona, $index) {
                    return [
                        'rank' => $index + 1,
                        'persona_id' => $persona->id,
                        'name' => trim($persona->nombre . ' ' . $persona->apellido_paterno),
                        'points' => $persona->loyalty_balance,
                        'universe' => $persona->universe_type,
                        'city' => $persona->municipio,
                        'state' => $persona->estado,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'leaderboard' => $leaderboard,
                    'universe_filter' => $universeType,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Leaderboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la tabla de líderes.'
            ], 500);
        }
    }

    /**
     * Get personas eligible for loyalty rewards (with minimum points)
     */
    public function getEligiblePersonas(Request $request): JsonResponse
    {
        try {
            $minPoints = $request->query('min_points', 50);

            $personas = Persona::where('loyalty_balance', '>=', $minPoints)
                ->select('id', 'nombre', 'apellido', 'numero_celular', 'loyalty_balance', 'universe_type')
                ->orderBy('loyalty_balance', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $personas
            ]);

        } catch (\Exception $e) {
            Log::error('Get eligible personas error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener personas elegibles.'
            ], 500);
        }
    }

    /**
     * Calculate and distribute points for completed event
     */
    public function distributeEventPoints($eventId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $attendees = EventAttendee::where('event_id', $eventId)
                ->where('attendance_status', 'completed')
                ->whereNotNull('entry_timestamp')
                ->whereNotNull('exit_timestamp')
                ->with('persona')
                ->get();

            $pointsDistributed = 0;
            $attendeesProcessed = 0;

            foreach ($attendees as $attendee) {
                // Check if points already awarded
                $existingBonus = BonusPointHistory::where('event_id', $eventId)
                    ->where('persona_id', $attendee->persona_id)
                    ->where('type', 'attendance')
                    ->exists();

                if ($existingBonus) {
                    continue;
                }

                // Calculate attendance duration
                $entryTime = \Carbon\Carbon::parse($attendee->entry_timestamp);
                $exitTime = \Carbon\Carbon::parse($attendee->exit_timestamp);
                $durationHours = $exitTime->diffInHours($entryTime);

                // Base points
                $points = 10;

                // Duration bonus
                if ($durationHours >= 2) {
                    $points += 5;
                }
                if ($durationHours >= 4) {
                    $points += 10;
                }

                // Universe multiplier
                $multipliers = [
                    'U1' => 1.0,
                    'U2' => 1.2,
                    'U3' => 1.5,
                    'U4' => 2.0
                ];

                $universe = $attendee->persona->universe_type ?? 'U1';
                $points = (int) round($points * $multipliers[$universe]);

                // Award points
                $attendee->persona->increment('loyalty_balance', $points);

                BonusPointHistory::create([
                    'persona_id' => $attendee->persona_id,
                    'event_id' => $eventId,
                    'points_awarded' => $points,
                    'type' => 'attendance',
                    'description' => "Asistencia al evento (duración: {$durationHours}h)",
                ]);

                $pointsDistributed += $points;
                $attendeesProcessed++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Puntos distribuidos exitosamente.',
                'data' => [
                    'attendees_processed' => $attendeesProcessed,
                    'total_points_distributed' => $pointsDistributed,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Distribute points error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al distribuir puntos.'
            ], 500);
        }
    }

    /**
     * Generate QR code for voucher
     */
    private function generateVoucherQr(Redemption $redemption): string
    {
        $directory = 'qrcodes/vouchers';
        $storagePath = storage_path('app/public/' . $directory);
        
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        $filename = 'voucher_' . $redemption->voucher_code . '.png';
        $fullPath = $storagePath . '/' . $filename;
        
        // Generate QR with voucher code
        QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->generate($redemption->voucher_code, $fullPath);
        
        return $directory . '/' . $filename;
    }
}
