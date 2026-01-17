<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignRequest;
use App\Models\Campaign;
use App\Models\Persona;
use App\Services\CsvSegmentationService;
use App\Services\MilitantQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    protected $militantQrService;

    public function __construct(MilitantQrService $militantQrService)
    {
        $this->militantQrService = $militantQrService;
    }

    /**
     * Store a newly created campaign in storage.
     */
    public function store(CampaignRequest $request): JsonResponse
    {
        $data = $request->validated();

        $fileFields = [
            'citizen_segmentation_file',
            'leader_segmentation_file',
            'militant_segmentation_file',
        ];

        $hasSegmentationFiles = false;
        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $filePath = $request->file($field)->store('campaign_files', 'public');
                $data[$field] = $filePath;
                $hasSegmentationFiles = true;
            }
        }

        $campaign = Campaign::create($data);

        // Auto-process segmentation files if any were uploaded
        $processingStats = null;
        if ($hasSegmentationFiles) {
            try {
                $service = new CsvSegmentationService();
                $processingStats = $service->processAllSegmentationFiles($campaign);
                Log::info("Auto-processed CSV files for new campaign {$campaign->id}", $processingStats);
            } catch (\Exception $e) {
                Log::error("Error auto-processing segmentation for campaign {$campaign->id}: {$e->getMessage()}");
                // Don't fail campaign creation if processing fails
            }
        }

        // Generate militant QR codes when campaign is created
        $militantQrStats = null;
        try {
            $militantQrStats = $this->militantQrService->generateCampaignMilitantQrs($campaign);
            Log::info("Generated militant QR codes for campaign {$campaign->id}", $militantQrStats);
        } catch (\Exception $e) {
            Log::error("Error generating militant QRs for campaign {$campaign->id}: {$e->getMessage()}");
        }

        return response()->json([
            'message' => 'Campaña creada exitosamente',
            'campaign' => $campaign,
            'segmentation_processing' => $processingStats,
            'militant_qr_generation' => $militantQrStats
        ], 201);
    }

    /**
     * Display a listing of the campaigns.
     */
    public function index(): JsonResponse
    {
        $campaigns = Campaign::all(); // Carga todas las campañas sin los eventos
        return response()->json([
            'campaigns' => $campaigns
        ]);
    }

    /**
     * Display the specified campaign.
     */
    public function show(string $id): JsonResponse
    {
        $campaign = Campaign::with(['events.attendees'])->findOrFail($id);

        $totalAttendees = 0;
        $totalCheckins = 0;
        $totalCheckouts = 0;

        foreach ($campaign->events as $event) {
            $totalAttendees += $event->attendees->count();
            $totalCheckins += $event->attendees->whereNotNull('checkin_at')->count();
            $totalCheckouts += $event->attendees->whereNotNull('checkout_at')->count();
        }

        $effectivenessRate = $totalAttendees > 0 
            ? round(($totalCheckouts / $totalAttendees) * 100, 1) 
            : 0;

        return response()->json([
            'campaign' => $campaign,
            'metrics' => [
                'total_events' => $campaign->events->count(),
                'total_registered' => $totalAttendees,
                'total_checkins' => $totalCheckins,
                'total_checkouts' => $totalCheckouts,
                'effectiveness_rate' => $effectivenessRate,
            ]
        ]);
    }

    /**
     * Update the specified campaign in storage.
     */
    public function update(CampaignRequest $request, string $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $data = $request->validated();

        $fileFields = [
            'citizen_segmentation_file',
            'leader_segmentation_file',
            'militant_segmentation_file',
        ];

        $filesChanged = false;
        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                // Eliminar archivo antiguo si existe
                if ($campaign->{$field} && Storage::disk('public')->exists($campaign->{$field})) {
                    Storage::disk('public')->delete($campaign->{$field});
                }
                $filePath = $request->file($field)->store('campaign_files', 'public');
                $data[$field] = $filePath;
                $filesChanged = true;
            } elseif (isset($data[$field]) && $data[$field] === null) {
                // Si el campo se envía como null, eliminar el archivo existente
                if ($campaign->{$field} && Storage::disk('public')->exists($campaign->{$field})) {
                    Storage::disk('public')->delete($campaign->{$field});
                }
                $data[$field] = null;
            }
        }

        $campaign->update($data);

        // Auto-process segmentation files if any were changed/added
        $processingStats = null;
        if ($filesChanged) {
            try {
                $service = new CsvSegmentationService();
                $processingStats = $service->processAllSegmentationFiles($campaign);
                Log::info("Auto-processed updated CSV files for campaign {$campaign->id}", $processingStats);
            } catch (\Exception $e) {
                Log::error("Error auto-processing segmentation for campaign {$campaign->id}: {$e->getMessage()}");
                // Don't fail campaign update if processing fails
            }
        }

        return response()->json([
            'message' => 'Campaña actualizada exitosamente',
            'campaign' => $campaign,
            'segmentation_processing' => $processingStats
        ]);
    }

    /**
     * Remove the specified campaign from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $fileFields = [
            'citizen_segmentation_file',
            'leader_segmentation_file',
            'militant_segmentation_file',
        ];

        foreach ($fileFields as $field) {
            if ($campaign->{$field} && Storage::disk('public')->exists($campaign->{$field})) {
                Storage::disk('public')->delete($campaign->{$field});
            }
        }

        $campaign->delete();

        return response()->json([
            'message' => 'Campaña eliminada exitosamente'
        ], 204);
    }

    /**
     * Get personas associated with the campaign for n8n workflow.
     * 
     * This endpoint returns all personas that should receive invitations
     * for this campaign's events, based on:
     * 1. Personas from processed segmentation files (filtered by target_universes)
     * 2. Personas who have previously attended campaign events
     * 
     * Used by n8n workflow to send WhatsApp invitations.
     */
    public function personas(string $id, Request $request): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        
        // Get filter parameters from request
        $universeFilter = $request->input('universe'); // Filter by specific universe
        $includeAttendees = $request->boolean('include_attendees', true); // Include past attendees
        $limit = $request->input('limit'); // Optional limit
        $offset = $request->input('offset', 0); // Pagination offset

        // Start building query
        $query = Persona::query();

        // Filter by target universes if campaign has them defined
        if ($campaign->target_universes && count($campaign->target_universes) > 0) {
            $query->whereIn('universe_type', $campaign->target_universes);
        }

        // If specific universe requested, filter by it
        if ($universeFilter) {
            $query->where('universe_type', $universeFilter);
        }

        // If include_attendees is true, also get personas from past event attendees
        if ($includeAttendees) {
            $attendeeIds = $campaign->events()
                ->with('attendees')
                ->get()
                ->flatMap(function ($event) {
                    return $event->attendees->pluck('persona_id');
                })
                ->unique()
                ->toArray();

            if (!empty($attendeeIds)) {
                // Union with attendees (if they match universe criteria)
                $query->orWhereIn('id', $attendeeIds);
            }
        }

        // Order by loyalty balance (prioritize engaged personas)
        $query->orderByDesc('loyalty_balance')
              ->orderBy('nombre');

        // Get total count before pagination
        $total = $query->count();

        // Apply pagination if limit specified
        if ($limit) {
            $query->limit($limit)->offset($offset);
        }

        // Get the personas with necessary fields
        $personas = $query->select([
            'id',
            'cedula',
            'nombre',
            'apellido_paterno',
            'apellido_materno',
            'numero_celular',
            'numero_telefono',
            'universe_type',
            'is_leader',
            'referral_code',
            'loyalty_balance',
            'municipio',
            'estado',
            'region',
        ])->get();

        // Add full name for convenience
        $personas = $personas->map(function ($persona) {
            $persona->full_name = trim("{$persona->nombre} {$persona->apellido_paterno} {$persona->apellido_materno}");
            $persona->phone = $persona->numero_celular ?: $persona->numero_telefono;
            return $persona;
        });

        return response()->json([
            'success' => true,
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'theme' => $campaign->theme,
                'target_universes' => $campaign->target_universes,
            ],
            'personas' => $personas,
            'meta' => [
                'total' => $total,
                'count' => $personas->count(),
                'offset' => $offset,
                'limit' => $limit,
            ]
        ]);
    }

    /**
     * Process all CSV segmentation files for a campaign.
     * 
     * This endpoint triggers the import of personas from uploaded CSV files.
     * Should be called after campaign creation or when files are updated.
     */
    public function processSegmentation(string $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        
        // Check if any segmentation files exist
        if (!$campaign->citizen_segmentation_file && 
            !$campaign->leader_segmentation_file && 
            !$campaign->militant_segmentation_file) {
            return response()->json([
                'success' => false,
                'message' => 'No segmentation files found for this campaign'
            ], 400);
        }

        try {
            $service = new CsvSegmentationService();
            $stats = $service->processAllSegmentationFiles($campaign);

            // Log the import
            Log::info("CSV segmentation processed for campaign {$id}", $stats);

            return response()->json([
                'success' => true,
                'message' => 'Segmentation files processed successfully',
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error processing segmentation for campaign {$id}: {$e->getMessage()}");
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing segmentation files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate a segmentation file without processing it
     */
    public function validateSegmentation(string $id, Request $request): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        
        $fileType = $request->input('file_type'); // 'citizen', 'leader', or 'militant'
        
        $fileField = match($fileType) {
            'citizen' => 'citizen_segmentation_file',
            'leader' => 'leader_segmentation_file',
            'militant' => 'militant_segmentation_file',
            default => null
        };

        if (!$fileField || !$campaign->{$fileField}) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type or file not found'
            ], 400);
        }

        $service = new CsvSegmentationService();
        $validation = $service->validateCsvFile($campaign->{$fileField});

        return response()->json([
            'success' => $validation['valid'],
            'validation' => $validation
        ]);
    }

    /**
     * Get preview of segmentation file data
     */
    public function previewSegmentation(string $id, Request $request): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        
        $fileType = $request->input('file_type'); // 'citizen', 'leader', or 'militant'
        $limit = $request->input('limit', 5);
        
        $fileField = match($fileType) {
            'citizen' => 'citizen_segmentation_file',
            'leader' => 'leader_segmentation_file',
            'militant' => 'militant_segmentation_file',
            default => null
        };

        if (!$fileField || !$campaign->{$fileField}) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type or file not found'
            ], 400);
        }

        $service = new CsvSegmentationService();
        $preview = $service->getCsvPreview($campaign->{$fileField}, $limit);

        return response()->json([
            'success' => true,
            'file_type' => $fileType,
            'preview' => $preview
        ]);
    }

    /**
     * Get militant QR distribution data for n8n workflow
     * Returns all militants with their QR codes ready for WhatsApp distribution
     * 
     * @param string $id Campaign ID
     * @return JsonResponse
     */
    public function getMilitantQrsForDistribution(string $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        
        $distributionData = $this->militantQrService->getMilitantQrsForDistribution($campaign);

        return response()->json([
            'success' => true,
            'data' => $distributionData
        ]);
    }
}
