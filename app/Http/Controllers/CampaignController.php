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
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            try {
                $data = $request->validated();

                // Build file paths
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

                // SaaS: Assign tenant and creator
                $data['tenant_id'] = app()->bound('tenant_id') ? app('tenant_id') : null;
                $data['created_by'] = auth()->id();

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
                    }
                }

                // Generate militant QR codes when campaign is created
                $militantQrStats = null;
                try {
                    $militantQrStats = $this->militantQrService->generateCampaignMilitantQrs($campaign);
                    Log::info("Generated militant QR codes for campaign {$campaign->id}", $militantQrStats ?: []);
                } catch (\Exception $e) {
                    Log::error("Error generating militant QRs for campaign {$campaign->id}: {$e->getMessage()}");
                }

                return response()->json([
                    'message' => 'Campaña creada exitosamente',
                    'campaign' => $campaign,
                    'segmentation_processing' => $processingStats,
                    'militant_qr_generation' => $militantQrStats
                ], 201);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Campaign Creation Error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'message' => 'Error al crear la campaña',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
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
        $universeFilter = $request->input('universe') ?: null;
        $territorioFilter = ($request->input('municipio') ?: $request->input('territorio')) ?: null;
        $coloniaFilter = $request->input('colonia') ?: null;
        $seccionFilter = $request->input('seccion') ?: null;
        $cdzFilter = $request->input('cdz') ?: null;
        $searchFilter = $request->input('search') ?: null;
        $tagFilter = $request->input('tags') ?: null; // New: comma separated tags or tag names
        
        $minAge = $request->input('min_age');
        $maxAge = $request->input('max_age');
        
        $includeAttendees = $request->boolean('include_attendees', true);
        $limit = $request->input('limit');
        $offset = $request->input('offset', 0);

        $query = Persona::query();

        // 0. Event Context & Audience Intelligence
        $eventId = $request->input('event_id');
        $event = null;
        if ($eventId) {
            $event = \App\Models\Event::with('tags')->find($eventId);
            if ($event) {
                // Inherit territory from event if not provided
                if (!$territorioFilter && $event->municipality) {
                    $territorioFilter = $event->municipality;
                }
                if (!$coloniaFilter && $event->neighborhood) {
                    $coloniaFilter = $event->neighborhood;
                }

                // If event has tags, we prioritize personas who match at least one event tag
                $eventTagNames = $event->tags->pluck('name')->toArray();
                if (!empty($eventTagNames)) {
                    $query->where(function($q) use ($eventTagNames) {
                        foreach ($eventTagNames as $tagName) {
                            $q->orWhereJsonContains('tags', $tagName);
                        }
                    });
                }
            }
        }

        // 1. Tag Filtering (Explicit or Theme-based)
        if ($tagFilter) {
            $tags = explode(',', $tagFilter);
            $query->where(function($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', trim($tag));
                }
            });
        } elseif ($campaign->theme && !$eventId) {
            // Auto-filter by theme if no specific event/tag is requested
            $theme = $campaign->theme;
            $query->where(function($q) use ($theme) {
                $q->orWhere('tags', 'like', "%{$theme}%") // Soft match for variations
                  ->orWhereJsonContains('tags', $theme);
            });
        }

        // 2. Audience Segmentation (Pilar 1 - Demographic Logic)
        if ($event && $event->target_audience_filters) {
            $filters = $event->target_audience_filters;
            
            $hasBeneficiaries = $filters['has_beneficiaries'] ?? $filters['has_pets'] ?? false;
            if ($hasBeneficiaries === true || $hasBeneficiaries === 'true') {
                $query->where(function($q) {
                    $q->whereHas('beneficiarios')
                        ->orWhereHas('mascotas')
                        ->orWhereJsonContains('universes', 'mascotas')
                        ->orWhereJsonContains('tags', 'tiene_mascotas')
                        ->orWhereJsonContains('tags', 'Dueño de Perro')
                        ->orWhereJsonContains('tags', 'Dueño de Gato');
                });
            }
            if (!empty($filters['gender']) && $filters['gender'] !== 'both') {
                $genderMap = ['male' => 'H', 'female' => 'M'];
                $mappedGender = $genderMap[$filters['gender']] ?? $filters['gender'];
                $query->where('sexo', $mappedGender);
            }
            if (!empty($minAge) || !empty($filters['min_age'])) {
                $query->where('edad', '>=', $minAge ?: $filters['min_age']);
            }
            if (!empty($maxAge) || !empty($filters['max_age'])) {
                $query->where('edad', '<=', $maxAge ?: $filters['max_age']);
            }
        }

        // 3. Universe Segmentation
        if ($campaign->target_universes && count($campaign->target_universes) > 0) {
            $query->whereIn('universe_type', $campaign->target_universes);
        }

        if ($universeFilter) {
            $universes = explode(',', $universeFilter);
            $query->whereIn('universe_type', $universes);
        }

        // 4. SMART TERRITORY ENGINE 
        if ($territorioFilter || $coloniaFilter) {
            $query->where(function($q) use ($territorioFilter, $coloniaFilter) {
                if ($territorioFilter) {
                    $q->where(function($sq) use ($territorioFilter) {
                        $sq->where('municipio', 'like', "%{$territorioFilter}%")
                           ->orWhere('colonia', 'like', "%{$territorioFilter}%")
                           ->orWhere('region', 'like', "%{$territorioFilter}%")
                           ->orWhere('estado', 'like', "%{$territorioFilter}%");
                    });
                }
                if ($coloniaFilter) {
                    $q->orWhere('colonia', 'like', "%{$coloniaFilter}%");
                }
            });
        }

        // 5. Electoral Filter
        if ($seccionFilter) {
            $query->where('seccion', $seccionFilter);
        }

        // 6. Citizen Code Filter (Fixed column name)
        if ($cdzFilter) {
            $query->where('codigo_ciudadano', 'like', "%{$cdzFilter}%");
        }

        // 7. Age Range Filter
        if ($minAge) $query->where('edad', '>=', (int)$minAge);
        if ($maxAge) $query->where('edad', '<=', (int)$maxAge);

        // 8. Global Search
        if ($searchFilter) {
            $query->where(function($q) use ($searchFilter) {
                $q->where('nombre', 'like', "%{$searchFilter}%")
                  ->orWhere('apellido_paterno', 'like', "%{$searchFilter}%")
                  ->orWhere('cedula', 'like', "%{$searchFilter}%")
                  ->orWhere('curp', 'like', "%{$searchFilter}%")
                  ->orWhere('codigo_ciudadano', 'like', "%{$searchFilter}%");
            });
        }

        // 9. Past Attendees Union
        if ($includeAttendees) {
            $attendeeIds = $campaign->events()->with('attendees')->get()
                ->flatMap(fn($e) => $e->attendees->pluck('persona_id'))
                ->unique()->toArray();

            if (!empty($attendeeIds)) {
                $query->orWhereIn('id', $attendeeIds);
            }
        }

        // Update invited status if requested
        if ($eventId && $request->boolean('update_invited')) {
            $updateQuery = clone $query;
            $updateQuery->update([
                'last_invited_event_id' => $eventId,
                'last_invited_at' => now()
            ]);
        }

        $total = $query->count();

        // Apply pagination and select lighter fields
        if ($limit) $query->limit($limit)->offset($offset);

        $personas = $query->select([
            'id', 'cedula', 'nombre', 'apellido_paterno', 'apellido_materno',
            'numero_celular', 'numero_telefono', 'universe_type', 'is_leader',
            'referral_code', 'loyalty_balance', 'municipio', 'estado', 'region', 'tags'
        ])->orderByDesc('loyalty_balance')
          ->orderBy('nombre')
          ->get();

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

    /**
     * Trigger a massive invitation broadcast for a campaign via n8n.
     * This endpoint is called from the UI when the admin clicks "Send invitations to all".
     * Triggers FLOW 7 – Invitaciones Masivas WhatsApp in n8n.
     */
    public function broadcastInvitations(string $id, Request $request): JsonResponse
    {
        $campaign = Campaign::with('events')->findOrFail($id);
        
        $webhookUrl = config('services.n8n.webhook_flow7_broadcast_url');

        if (!$webhookUrl) {
            return response()->json([
                'success' => false,
                'message' => 'n8n broadcast webhook URL not configured (FLOW 7)'
            ], 500);
        }

        // Get the most recent/active event for this campaign
        $activeEvent = $campaign->events()
            ->where('status', '!=', 'completed')
            ->orderBy('date', 'desc')
            ->first();

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(120)->post($webhookUrl, [
                'message_type' => 'campaign_broadcast',
                'campaign_id' => $campaign->id,
                'event_id' => $request->input('event_id', $activeEvent?->id ?? ''),
                'custom_message' => $request->input('custom_message', ''),
                'filters' => [
                    'municipio' => $request->input('municipio', ''),
                    'colonia' => $request->input('colonia', ''),
                    'universe' => $request->input('universe', ''),
                ],
                'api_base_url' => config('app.url') . '/api',
                'timestamp' => now()->toIso8601String(),
            ]);

            $result = $response->json();

            return response()->json([
                'success' => true,
                'message' => 'Broadcast masivo ejecutado via FLOW 7',
                'broadcast_result' => $result,
                'campaign_id' => $campaign->id,
                'event_id' => $activeEvent?->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger broadcast: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error triggering broadcast: ' . $e->getMessage()
            ], 500);
        }
    }
}
