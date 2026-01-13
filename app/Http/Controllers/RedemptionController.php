<?php

namespace App\Http\Controllers;

use App\Models\Redemption;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class RedemptionController extends Controller
{
    /**
     * Validate a voucher QR code (staff scans to redeem)
     */
    public function validateVoucher(Request $request): JsonResponse
    {
        $request->validate([
            'voucher_code' => 'required|string'
        ]);

        try {
            $redemption = Redemption::where('voucher_code', $request->voucher_code)
                ->with('persona')
                ->first();

            if (!$redemption) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher no encontrado.'
                ], 404);
            }

            // Check if valid
            if (!$redemption->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => "Voucher no válido. Estado actual: {$redemption->status}",
                    'data' => [
                        'status' => $redemption->status,
                        'validated_at' => $redemption->validated_at,
                    ]
                ], 400);
            }

            // Validate voucher
            $userId = Auth::id();
            $redemption->validate($userId);

            return response()->json([
                'success' => true,
                'message' => '✅ Voucher validado exitosamente.',
                'data' => [
                    'voucher_code' => $redemption->voucher_code,
                    'persona_name' => $redemption->persona->nombre . ' ' . $redemption->apellido_paterno,
                    'reward' => $redemption->reward_description,
                    'points_redeemed' => $redemption->points_redeemed,
                    'validated_at' => $redemption->validated_at,
                    'validated_by' => $userId
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Validate voucher error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al validar voucher.'
            ], 500);
        }
    }

    /**
     * Get all redemptions for a persona
     */
    public function getPersonaRedemptions($personaId): JsonResponse
    {
        try {
            $redemptions = Redemption::where('persona_id', $personaId)
                ->with('validator:id,name')
                ->orderBy('redeemed_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $redemptions
            ]);

        } catch (\Exception $e) {
            Log::error('Get persona redemptions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener canjes.'
            ], 500);
        }
    }

    /**
     * Get voucher details
     */
    public function getVoucherDetails($voucherCode): JsonResponse
    {
        try {
            $redemption = Redemption::where('voucher_code', $voucherCode)
                ->with(['persona', 'validator'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'voucher_code' => $redemption->voucher_code,
                    'status' => $redemption->status,
                    'persona' => [
                        'id' => $redemption->persona->id,
                        'name' => $redemption->persona->nombre . ' ' . $redemption->persona->apellido_paterno,
                        'phone' => $redemption->persona->numero_celular,
                    ],
                    'points_redeemed' => $redemption->points_redeemed,
                    'reward_description' => $redemption->reward_description,
                    'qr_code_url' => $redemption->qr_code_path ? url('storage/' . $redemption->qr_code_path) : null,
                    'redeemed_at' => $redemption->redeemed_at,
                    'validated_at' => $redemption->validated_at,
                    'expires_at' => $redemption->expires_at,
                    'is_valid' => $redemption->isValid(),
                    'validated_by' => $redemption->validator ? $redemption->validator->name : null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher no encontrado.'
            ], 404);
        }
    }

    /**
     * Cancel a redemption (refund points)
     */
    public function cancelRedemption($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $redemption = Redemption::findOrFail($id);

            if ($redemption->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden cancelar vouchers pendientes.'
                ], 400);
            }

            $redemption->cancel();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voucher cancelado y puntos reembolsados.',
                'data' => [
                    'points_refunded' => $redemption->points_redeemed,
                    'new_balance' => $redemption->persona->fresh()->loyalty_balance,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cancel redemption error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar voucher.'
            ], 500);
        }
    }

    /**
     * Get all pending redemptions (for staff validation queue)
     */
    public function getPendingRedemptions(Request $request): JsonResponse
    {
        try {
            $query = Redemption::pending()
                ->notExpired()
                ->with('persona:id,nombre,apellido_paterno,numero_celular')
                ->orderBy('redeemed_at', 'desc');

            // Optional filters
            if ($request->has('persona_id')) {
                $query->where('persona_id', $request->persona_id);
            }

            if ($request->has('min_points')) {
                $query->where('points_redeemed', '>=', $request->min_points);
            }

            $redemptions = $query->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $redemptions
            ]);

        } catch (\Exception $e) {
            Log::error('Get pending redemptions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vouchers pendientes.'
            ], 500);
        }
    }

    /**
     * Get redemption statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $totalRedemptions = Redemption::count();
            $pendingCount = Redemption::pending()->count();
            $validatedCount = Redemption::validated()->count();
            $expiredCount = Redemption::where('status', 'expired')->count();
            $totalPointsRedeemed = Redemption::sum('points_redeemed');

            $topRedeemers = Persona::select('personas.id', 'personas.nombre', 'personas.apellido_paterno')
                ->join('redemptions', 'redemptions.persona_id', '=', 'personas.id')
                ->selectRaw('COUNT(redemptions.id) as redemption_count')
                ->selectRaw('SUM(redemptions.points_redeemed) as total_points')
                ->groupBy('personas.id', 'personas.nombre', 'personas.apellido_paterno')
                ->orderByDesc('total_points')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_redemptions' => $totalRedemptions,
                        'pending' => $pendingCount,
                        'validated' => $validatedCount,
                        'expired' => $expiredCount,
                        'total_points_redeemed' => $totalPointsRedeemed,
                    ],
                    'top_redeemers' => $topRedeemers,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get redemption stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas.'
            ], 500);
        }
    }
}
