<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\QrCode;
use App\Models\Persona;
use App\Services\MilitantQrService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MilitantQrController extends Controller
{
    protected MilitantQrService $militantQrService;

    public function __construct(MilitantQrService $militantQrService)
    {
        $this->militantQrService = $militantQrService;
    }

    /**
     * Get all militant QR codes for a campaign
     */
    public function getCampaignMilitantQrs(int $campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);
            $qrCodes = $this->militantQrService->getCampaignMilitantQrs($campaign);

            return response()->json([
                'success' => true,
                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                ],
                'qr_codes' => $qrCodes->map(function ($qr) {
                    return [
                        'id' => $qr->id,
                        'code' => $qr->code,
                        'persona' => [
                            'id' => $qr->persona->id,
                            'nombre' => $qr->persona->nombre,
                            'apellido_paterno' => $qr->persona->apellido_paterno,
                            'cedula' => $qr->persona->cedula,
                            'numero_celular' => $qr->persona->numero_celular,
                        ],
                        'scan_count' => $qr->scan_count ?? 0,
                        'created_at' => $qr->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'total' => $qrCodes->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching militant QR codes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate campaign militant QR codes
     */
    public function generateCampaignMilitantQrs(int $campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);
            $stats = $this->militantQrService->generateCampaignMilitantQrs($campaign);

            return response()->json([
                'success' => true,
                'message' => 'Militant QR codes generated successfully',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate militant QR codes', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating militant QR codes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate a single militant QR code (for lost/damaged codes)
     */
    public function regenerateMilitantQr(int $personaId, int $campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);
            $persona = Persona::findOrFail($personaId);

            if ($persona->universe_type !== 'U4') {
                return response()->json([
                    'success' => false,
                    'message' => 'This persona is not a militant (U4)',
                ], 400);
            }

            // Delete old QR code
            QrCode::where('campaign_id', $campaign->id)
                ->where('persona_id', $persona->id)
                ->where('type', 'QR-MILITANT')
                ->delete();

            // Generate new QR code
            $stats = $this->militantQrService->generateCampaignMilitantQrs($campaign);

            $newQr = QrCode::where('campaign_id', $campaign->id)
                ->where('persona_id', $persona->id)
                ->where('type', 'QR-MILITANT')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'QR code regenerated successfully',
                'qr_code' => [
                    'id' => $newQr->id,
                    'code' => $newQr->code,
                    'persona' => [
                        'id' => $persona->id,
                        'nombre' => $persona->nombre,
                        'cedula' => $persona->cedula,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error regenerating QR code: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get militant QR statistics for a campaign
     */
    public function getMilitantQrStats(int $campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);

            $totalMilitants = Persona::where('universe_type', 'U4')->count();
            $qrCodes = QrCode::where('campaign_id', $campaign->id)
                ->where('type', 'QR-MILITANT')
                ->get();

            $totalScans = $qrCodes->sum('scan_count');

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_militants' => $totalMilitants,
                    'qr_codes_generated' => $qrCodes->count(),
                    'total_scans' => $totalScans,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stats: ' . $e->getMessage(),
            ], 500);
        }
    }


}
