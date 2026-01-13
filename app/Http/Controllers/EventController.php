<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\QrCode;
use App\Services\QrCodeService;
use App\Services\MilitantQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    protected $qrCodeService;
    protected $militantQrService;

    public function __construct(QrCodeService $qrCodeService, MilitantQrService $militantQrService)
    {
        $this->qrCodeService = $qrCodeService;
        $this->militantQrService = $militantQrService;
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(EventRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            if ($request->hasFile('pdf_path')) {
                $filePath = $request->file('pdf_path')->store('event_files', 'public');
                $data['pdf_path'] = $filePath;
            }

            $event = Event::create($data);

            // Attach event to campaign
            if (isset($data['campaign_id'])) {
                $campaign = Campaign::findOrFail($data['campaign_id']);
                $campaign->events()->attach($event->id);
            }

            // Auto-generate QR codes for the event
            $generatedQrs = $this->qrCodeService->generateEventQrCodes($event);

            // Update event with the generated codes for public access (QR2 for checkin, QR3 for checkout)
            $event->update([
                'checkin_code' => $generatedQrs['QR2']->code ?? null,
                'checkout_code' => $generatedQrs['QR3']->code ?? null,
            ]);

            // Generate militant QR codes if U4 is in target universe
            if (isset($data['target_universes']) && is_array($data['target_universes']) && in_array('U4', $data['target_universes'])) {
                $this->militantQrService->generateEventMilitantQrs($event);
            }

            DB::commit();

            // Trigger n8n Invitation Workflow
            $this->triggerN8nWorkflow($event);

            return response()->json([
                'message' => 'Evento creado exitosamente con códigos QR',
                'data' => $event->load('qrCodes')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Event creation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear evento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Trigger n8n Notification Workflow for event invitations
     * 
     * Uses notification workflow that handles:
     * - Event invitations
     * - Militant QR distribution
     * - Other WhatsApp notifications
     */
    protected function triggerN8nWorkflow(Event $event)
    {
        $webhookUrl = config('services.n8n.notification_webhook_url');

        if (!$webhookUrl) {
            Log::warning('n8n notification webhook URL not configured');
            return;
        }

        try {
            Http::post($webhookUrl, [
                'message_type' => 'event_invitation',
                'event_id' => $event->id,
                'campaign_id' => $event->campaign_id,
                'api_base_url' => config('app.url') . '/api',
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger n8n notification workflow: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified event.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $event = Event::with('qrCodes')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $event
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Evento no encontrado'
            ], 404);
        }
    }

    /**
     * Get event QR codes
     */
    public function getQrCodes(string $id): JsonResponse
    {
        try {
            $event = Event::findOrFail($id);
            $qrCodes = QrCode::where('event_id', $id)
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'event' => $event,
                    'qr_codes' => $qrCodes
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener códigos QR'
            ], 500);
        }
    }

    /**
     * Update the specified event in storage.
     */
    public function update(EventRequest $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('pdf_path')) {
            // Eliminar archivo anterior si existe
            if ($event->pdf_path) {
                Storage::disk('public')->delete($event->pdf_path);
            }
            $filePath = $request->file('pdf_path')->store('event_files', 'public');
            $data['pdf_path'] = $filePath;
        }

        $event->update($data);

        return response()->json([
            'message' => 'Evento actualizado exitosamente',
            'event' => $event
        ]);
    }

    /**
     * Display a listing of all events.
     */
    public function allEvents(): JsonResponse
    {
        $events = Event::with('campaign')->get();
        return response()->json([
            'events' => $events
        ]);
    }

    /**
     * Display a listing of the events for a specific campaign.
     */
    public function index(string $campaignId): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);
        $events = $campaign->events()->get();

        return response()->json([
            'events' => $events
        ]);
    }

    /**
     * Display the specified event publicly by QR code data (checkin or checkout).
     */
    public function showPublic(string $code): JsonResponse
    {
        $event = Event::where('checkin_code', $code)
            ->orWhere('checkout_code', $code)
            ->with('campaign')
            ->firstOrFail();

        return response()->json([
            'event' => $event,
            'bonus_points_for_attendee' => $event->bonus_points_for_attendee ?? 0,
            'bonus_points_for_leader' => $event->bonus_points_for_leader ?? 0
        ]);
    }

    /**
     * Mark event documents as ready (for n8n integration).
     */
    public function documentsReady(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        // Optionally update a field, e.g., $event->update(['documents_processed' => true]);

        return response()->json([
            'message' => 'Event documents marked as ready',
            'event_id' => $id
        ]);
    }

    /**
     * Manually end an event and schedule post-event processing
     * 
     * This triggers the queue-based automation:
     * 1. Auto-checkout attendees after grace period
     * 2. Distribute points to attendees and leaders
     * 
     * @param string $id Event ID
     * @return JsonResponse
     */
    public function endEvent(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        // Check if already ended
        if ($event->ended_at) {
            return response()->json([
                'message' => 'Event already ended',
                'ended_at' => $event->ended_at,
                'auto_checkout_scheduled' => $event->auto_close_scheduled,
                'points_distribution_scheduled' => $event->points_distribution_scheduled,
            ], 400);
        }

        // Check if points already distributed
        if ($event->points_distributed) {
            return response()->json([
                'message' => 'Event already has points distributed',
                'points_distributed' => true,
            ], 400);
        }

        try {
            // Mark event as ended and schedule post-event processing
            $event->update([
                'ended_at' => now(),
                'status' => 'completed',
            ]);

            // Schedule queue jobs for auto-checkout and points distribution
            $event->schedulePostEventProcessing();

            $gracePeriodHours = $event->grace_period_hours ?? 1;
            $processingTime = now()->addHours($gracePeriodHours);

            return response()->json([
                'message' => 'Event ended successfully. Post-event processing scheduled.',
                'event_id' => $event->id,
                'ended_at' => $event->ended_at,
                'grace_period_hours' => $gracePeriodHours,
                'auto_checkout_scheduled_for' => $processingTime->toDateTimeString(),
                'points_distribution_will_follow' => true,
                'note' => 'Processing will occur automatically via queue jobs',
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to end event {$id}: {$e->getMessage()}");
            
            return response()->json([
                'message' => 'Failed to end event',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
