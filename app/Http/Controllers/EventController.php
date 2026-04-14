<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\QrCode;
use App\Services\QrCodeService;
use App\Services\MilitantQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Persona;
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

            // Decode form_schema if sent as JSON string
            if (isset($data['form_schema']) && is_string($data['form_schema'])) {
                $decoded = json_decode($data['form_schema'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['form_schema'] = $decoded;
                }
            }

            $event = Event::create($data);

            // ── Auto-apply contextual template from EventType ──────────────
            // If no custom form_schema was provided, inherit from EventType
            $eventType = $event->eventType;
            if ($eventType) {
                $templateUpdates = [];

                // Inherit form_schema from template if event doesn't have its own
                if (empty($data['form_schema']) && !empty($eventType->default_form_schema)) {
                    $templateUpdates['form_schema'] = $eventType->default_form_schema;
                }

                // Inherit success_message from template if not set
                if (empty($data['success_message']) && !empty($eventType->success_message)) {
                    $templateUpdates['success_message'] = $eventType->success_message;
                }

                // Auto-apply points configuration from template if not explicitly set
                if (!empty($eventType->default_points_config)) {
                    $pointsConfig = $eventType->default_points_config;
                    if (empty($data['bonus_points_for_attendee']) && isset($pointsConfig['attendee'])) {
                        $templateUpdates['bonus_points_for_attendee'] = $pointsConfig['attendee'];
                    }
                    if (empty($data['bonus_points_for_leader']) && isset($pointsConfig['leader'])) {
                        $templateUpdates['bonus_points_for_leader'] = $pointsConfig['leader'];
                    }
                    if (empty($data['bonus_points_per_referral']) && isset($pointsConfig['referral'])) {
                        $templateUpdates['bonus_points_per_referral'] = $pointsConfig['referral'];
                    }
                }

                if (!empty($templateUpdates)) {
                    $event->update($templateUpdates);
                    Log::info("Applied contextual template from EventType '{$eventType->name}' to event {$event->id}", array_keys($templateUpdates));
                }
            }

            // Auto-generate QR codes for the event
            $generatedQrs = $this->qrCodeService->generateEventQrCodes($event);

            // Update event with the generated codes for public access (QR2 for checkin, QR3 for checkout)
            $event->update([
                'checkin_code' => $generatedQrs['QR2']->code ?? null,
                'checkout_code' => $generatedQrs['QR3']->code ?? null,
            ]);

            // Generate and store QR code images
            $qrImagePaths = $this->qrCodeService->generateAndStoreQrImages($event);
            $event->update($qrImagePaths);

            // 1.3 Slots Dinámicos por Tipo de Evento (Pilar 1)
            $eventType = $event->eventType;
            if ($eventType && $eventType->requires_appointment) {
                $slotConfig = $eventType->default_slot_config ?? [
                    'interval_minutes' => 20,
                    'capacity_per_slot' => 4
                ];

                $startTime = $request->input('time', '10:00');
                $durationHours = $request->input('duration_hours', 8);
                // Convert to minutes to support fractions (e.g., 3.5 hours)
                $endTime = \Carbon\Carbon::parse($startTime)->addMinutes((int)($durationHours * 60))->format('H:i');
                
                $slotService = app(\App\Services\EventSlotService::class);
                $slotService->generateSlots(
                    $event,
                    $startTime,
                    $endTime,
                    $slotConfig['interval_minutes'],
                    $slotConfig['capacity_per_slot']
                );
            }

            // Generate militant QR codes if U4 is in target universe
            if (isset($data['target_universes']) && is_array($data['target_universes']) && in_array('U4', $data['target_universes'])) {
                $this->militantQrService->generateEventMilitantQrs($event);
            }

            // Sync tags if provided
            if ($request->has('tag_ids')) {
                $event->tags()->sync($request->input('tag_ids'));
            }

            DB::commit();

            if ($request->input('send_invitations')) {
                // Trigger n8n Invitation Workflow
                $this->triggerN8nWorkflow($event);
            }

            \Illuminate\Support\Facades\Cache::flush();

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
     * Trigger n8n FLOW 7 for mass event invitations
     * 
     * Automatically sends invitations to all targeted personas
     * when a new event is created.
     */
    protected function triggerN8nWorkflow(Event $event)
    {
        $webhookUrl = config('services.n8n.webhook_flow7_broadcast_url');

        if (!$webhookUrl) {
            Log::warning('n8n broadcast webhook URL not configured (FLOW 7). Skipping auto-invitations.');
            return;
        }

        try {
            // Reload event with campaign, eventType and tags for full context
            $event->load(['campaign', 'eventType', 'tags']);
            $tagNames = $event->tags->pluck('name')->toArray();

            Http::timeout(10)->post($webhookUrl, [
                'message_type' => 'event_invitation',
                'event_id' => $event->id,
                'campaign_id' => $event->campaign_id,
                'event_detail' => $event->detail,
                'event_date' => $event->date,
                'event_time' => $event->time,
                'event_location' => trim("{$event->street} {$event->number}, {$event->neighborhood}, {$event->municipality}"),
                'form_schema' => $event->form_schema,
                'success_message' => $event->success_message,
                'event_type' => $event->eventType?->name,
                'campaign_name' => $event->campaign?->name,
                'filters' => [
                    'municipio' => $event->municipality ?? '',
                    'colonia' => $event->neighborhood ?? '',
                    'universe' => is_array($event->target_universes) ? implode(',', $event->target_universes) : '',
                    'gender' => $event->gender_target ?? 'Ambos',
                    'min_age' => $event->min_age ?? 0,
                    'max_age' => $event->max_age ?? 100,
                    'tags' => $tagNames,
                ],
                'api_base_url' => config('app.url') . '/api',
                'timestamp' => now()->toIso8601String(),
            ]);

            Log::info("FLOW 7 broadcast triggered for event {$event->id} with form_schema and success_message");
        } catch (\Exception $e) {
            Log::error('Failed to trigger FLOW 7 broadcast: ' . $e->getMessage());
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

        // Sync tags if provided
        if ($request->has('tag_ids')) {
            $event->tags()->sync($request->input('tag_ids'));
        }
        \Illuminate\Support\Facades\Cache::flush();

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
        return \Illuminate\Support\Facades\Cache::remember('events_all', 600, function () {
            $events = Event::with('campaign')->get();
            return response()->json([
                'events' => $events
            ]);
        });
    }

    /**
     * Display a listing of the events for a specific campaign.
     */
    public function index(string $campaignId): JsonResponse
    {
        return \Illuminate\Support\Facades\Cache::remember("events_campaign_{$campaignId}", 600, function () use ($campaignId) {
            $campaign = Campaign::findOrFail($campaignId);
            $events = $campaign->events()->get();

            return response()->json([
                'events' => $events
            ]);
        });
    }

    /**
     * Display the specified event publicly by QR code data (checkin or checkout).
     */
    public function showPublic(string $code): JsonResponse
    {
        // 1. Try direct columns (fastest)
        $event = Event::where('checkin_code', $code)
            ->orWhere('checkout_code', $code)
            ->with('campaign')
            ->first();

        // 2. Fallback: Search the qr_codes table
        if (!$event) {
            $qrCode = QrCode::where('code', $code)->with('event.campaign')->first();
            if ($qrCode && $qrCode->event) {
                $event = $qrCode->event;
                $event->temp_leader_id = $qrCode->leader_id;
            }
        }

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Código de evento no encontrado'
            ], 404);
        }

        return response()->json([
            'event' => $event,
            'leader_id' => $event->temp_leader_id ?? null,
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
     * Get targeted audience for an event based on proximity and universe.
     * Used by n8n or admin dashboard for invitations.
     */
    public function getTargetedAudience(string $id, Request $request): JsonResponse
    {
        $event = Event::findOrFail($id);
        
        $query = Persona::query();
        
        // 1. Proximity Filter (Critical request)
        // If event has municipality, filter personas in that municipality
        if ($event->municipality) {
            $query->where('municipio', 'like', "%{$event->municipality}%");
        }
        
        // If event has neighborhood/colonia, prioritize it or use it as a secondary filter
        if ($event->neighborhood) {
            $query->where(fn($q) => $q->where('colonia', 'like', "%{$event->neighborhood}%")
                  ->orWhere('region', 'like', "%{$event->neighborhood}%"));
        }

        // 2. Universe Filter (Groups I, II, III, IV)
        if ($event->target_universes && count($event->target_universes) > 0) {
            $query->whereIn('universe_type', $event->target_universes);
        }

        // 3. Optional Status/Engagement Filters
        $query->orderByDesc('loyalty_balance');

        $personas = $query->paginate($request->input('per_page', 100));

        // 4. Attach Event and Leader Specific QRs for n8n to send
        $personas->getCollection()->transform(function($persona) use ($event) {
            $leaderQr = null;
            if ($persona->is_leader && $persona->universe_type === 'U3') {
                $qr = \App\Models\QrCode::where('event_id', $event->id)
                    ->where('leader_id', $persona->id)
                    ->where('type', 'QR2-L')
                    ->first();
                $leaderQr = $qr ? $qr->code : null;
            }
            
            $persona->invitation_data = [
                'event_detail' => $event->detail,
                'event_date' => $event->date,
                'event_time' => $event->time,
                'event_location' => $event->street . ' ' . $event->number . ', ' . $event->neighborhood,
                'checkin_code' => $event->checkin_code,
                'checkout_code' => $event->checkout_code,
                'leader_qr_code' => $leaderQr,
                'leader_invitation_url' => $leaderQr ? url("/invitation/{$leaderQr}") : null,
                'personal_checkin_url' => url("/events/public/{$event->checkin_code}"),
                'personal_checkout_url' => url("/events/checkout/{$event->checkout_code}"),
            ];
            
            return $persona;
        });

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'detail' => $event->detail,
                'checkin_code' => $event->checkin_code,
                'checkout_code' => $event->checkout_code,
                'location' => [
                    'municipality' => $event->municipality,
                    'neighborhood' => $event->neighborhood,
                    'state' => $event->state
                ]
            ],
            'personas' => $personas
        ]);
    }

    /**
     * Manually end an event and schedule post-event processing
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

            // Trigger n8n Feedback Workflow (FLOW 11)
            $this->triggerFeedbackWorkflow($event);

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

    /**
     * Trigger massive invitations for an event with proximity filters.
     */
    public function sendEventInvitations(string $id, Request $request): JsonResponse
    {
        $event = Event::with('tags')->findOrFail($id);
        $tagNames = $event->tags->pluck('name')->toArray();
        
        $webhookUrl = config('services.n8n.webhook_flow7_broadcast_url');

        if (!$webhookUrl) {
            return response()->json(['success' => false, 'message' => 'n8n notification URL not configured'], 500);
        }

        try {
            // Priority 1: Direct specialized notification for Leaders (U3)
            // They need their personal QRs AND their unique guest registration link
            if ($event->target_universes && in_array('U3', $event->target_universes)) {
                $leaders = \App\Models\Persona::where('universe_type', 'U3')
                    ->where('is_leader', true)
                    ->where('municipio', 'like', "%{$event->municipality}%")
                    ->get();
                
                $whatsappService = app(\App\Services\WhatsAppNotificationService::class);
                foreach ($leaders as $leader) {
                    $qr = \App\Models\QrCode::where('event_id', $event->id)
                        ->where('leader_id', $leader->id)
                        ->where('type', 'QR2-L')
                        ->first();
                    
                    if ($qr) {
                        try {
                            $whatsappService->sendLeaderEventInvitation($leader, $event, $qr->code);
                        } catch (\Exception $e) {
                            Log::warning("Could not invite leader {$leader->id}: " . $e->getMessage());
                        }
                    }
                }
            }

            // Proclivity filters based on event's own data
            $filters = [
                'municipio' => $event->municipality,
                'colonia' => $event->neighborhood,
                'universe' => array_diff($event->target_universes ?: [], ['U3']), // Exclude U3 from mass broadcast as they were notified above
                'gender' => $event->gender_target,
                'min_age' => $event->min_age,
                'max_age' => $event->max_age,
                'tags' => $tagNames,
            ];

            // Disparo asíncrono simulado: Si n8n tarda o da timeout, 
            // no bloqueamos al usuario porque el proceso ya inició.
            try {
                Http::timeout(3)->post($webhookUrl, [
                    'message_type' => 'event_broadcast',
                    'event_id' => $event->id,
                    'campaign_id' => $event->campaign_id,
                    'filters' => $filters,
                    'event_type' => $event->eventType?->name,
                    'api_base_url' => config('app.url') . '/api',
                    'timestamp' => now()->toIso8601String(),
                ]);
            } catch (\Exception $e) {
                // Si hay timeout, lo ignoramos porque n8n ya recibió el webhook
                \Log::info("N8N respondió lento pero la invitación fue enviada: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Broadcast triggered in n8n with proximity filters: ' . ($event->municipality ?: 'N/A')
            ]);

        } catch (\Exception $e) {
            Log::error('Event broadcast error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $event = Event::findOrFail($id);
            
            // Optional: Check for existing attendees before deleting
            // if ($event->attendees()->count() > 0) {
            //     return response()->json(['message' => 'No se puede borrar un evento con asistentes registrados'], 400);
            // }

            // Delete associated QR codes or other dependencies if needed
            // QrCode::where('event_id', $id)->delete();

            $event->delete();
            \Illuminate\Support\Facades\Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Evento eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendees for events starting in approx 24 hours.
     * Used by n8n FLOW 10 (Reminders).
     */
    public function getRemindersDue(): JsonResponse
    {
        $targetDate = now()->addDay()->format('Y-m-d');
        
        $events = Event::where('date', $targetDate)
            ->where('status', 'scheduled')
            ->with(['appointments.persona'])
            ->get();

        $reminders = [];
        
        foreach ($events as $event) {
            foreach ($event->appointments as $appointment) {
                if ($appointment->persona && $appointment->persona->numero_celular) {
                    $reminders[] = [
                        'whatsapp_number' => $appointment->persona->numero_celular,
                        'nombre_ciudadano' => $appointment->persona->nombre,
                        'nombre_evento' => $event->detail,
                        'fecha_evento' => $event->date . ' ' . $event->time,
                        'phone_number_id' => config('services.meta.phone_id') ?? '109489541525540'
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'attendees' => $reminders
        ]);
    }

    /**
     * Trigger n8n FLOW 11 for post-event feedback.
     */
    protected function triggerFeedbackWorkflow(Event $event)
    {
        $webhookUrl = config('services.n8n.webhook_flow11_feedback_url');

        try {
            // Load attendees that checked in
            $attendees = $event->attendees()->whereNotNull('check_in_time')->with('persona')->get();

            foreach ($attendees as $attendee) {
                if ($attendee->persona && $attendee->persona->numero_celular) {
                    \Illuminate\Support\Facades\Http::timeout(5)->post($webhookUrl, [
                        'event_id' => $event->id,
                        'nombre_evento' => $event->detail,
                        'whatsapp_number' => $attendee->persona->numero_celular,
                        'nombre_ciudadano' => $attendee->persona->nombre,
                        'phone_number_id' => config('services.meta.phone_id') ?? '109489541525540'
                    ]);
                }
            }

            Log::info("FLOW 11 feedback reminders triggered for event {$event->id}");
        } catch (\Exception $e) {
            Log::error('Failed to trigger FLOW 11 feedback: ' . $e->getMessage());
        }
    }

    /**
     * Get all available tags for event segmentation.
     */
    public function getAllTags(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Tag::all()
        ]);
    }
}
