<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\WhatsAppSession;
use App\Models\AiConversation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EventContextService
{
    /**
     * Resolve which event a WhatsApp message is about
     * 
     * This is the CORE method that determines event context for AI queries
     */
    public function resolveEventContext(string $phoneNumber, ?string $messageContent = null): array
    {
        // Step 1: Get persona by phone number
        $persona = Persona::where('numero_celular', $phoneNumber)->first();
        
        if (!$persona) {
            return $this->buildResponse(false, 'persona_not_found', 'register_user');
        }

        // Step 2: Get or create WhatsApp session
        $session = $this->getOrCreateSession($persona, $phoneNumber);
        
        // Step 3: If awaiting event selection, process user's choice
        if ($session->conversation_state === 'awaiting_event_selection') {
            return $this->processEventSelection($session, $messageContent);
        }

        // Step 4: If session has current event and it's still active, use it
        if ($session->current_event_id) {
            $event = Event::find($session->current_event_id);
            if ($event && $this->isEventActive($event)) {
                return $this->buildEventResponse($event, $session, 'session');
            }
        }

        // Step 5: Check last QR interaction (within 7 days)
        if ($persona->last_interacted_event_id && $persona->last_interaction_at) {
            $lastInteraction = Carbon::parse($persona->last_interaction_at);
            
            if ($lastInteraction->diffInDays(now()) <= 7) {
                $event = Event::find($persona->last_interacted_event_id);
                
                if ($event && $this->isEventActive($event)) {
                    // Update session with this event
                    $session->update([
                        'current_event_id' => $event->id,
                        'conversation_state' => 'resolved'
                    ]);
                    
                    return $this->buildEventResponse($event, $session, 'last_interaction');
                }
            }
        }

        // Step 6: Get all active events for persona
        $activeEvents = $this->getActiveEventsForPersona($persona);

        // Step 7: No active events - provide general assistance
        if ($activeEvents->isEmpty()) {
            return $this->buildResponse(false, 'no_active_events', 'provide_general_info', [
                'persona_name' => $persona->nombre,
                'total_points' => $persona->loyalty_balance ?? 0
            ]);
        }

        // Step 8: Only one active event - auto-select it
        if ($activeEvents->count() === 1) {
            $event = $activeEvents->first();
            
            $session->update([
                'current_event_id' => $event->id,
                'conversation_state' => 'resolved'
            ]);
            
            return $this->buildEventResponse($event, $session, 'single_active_event');
        }

        // Step 9: Multiple active events - request disambiguation
        return $this->requestEventSelection($session, $activeEvents);
    }

    /**
     * Process user's event selection when multiple events are available
     */
    private function processEventSelection(WhatsAppSession $session, ?string $message): array
    {
        if (!$message) {
            return $this->buildResponse(false, 'awaiting_selection', 'request_event_selection');
        }

        $contextData = $session->context_data ?? [];
        $availableEvents = $contextData['available_events'] ?? [];

        if (empty($availableEvents)) {
            // Session corrupted, reset it
            return $this->resolveEventContext($session->phone_number);
        }

        // Try to match user input to an event
        $selectedEvent = $this->matchEventSelection($message, $availableEvents);

        if ($selectedEvent) {
            // Valid selection
            $session->update([
                'current_event_id' => $selectedEvent['id'],
                'conversation_state' => 'resolved',
                'context_data' => null
            ]);

            $event = Event::find($selectedEvent['id']);
            return $this->buildEventResponse($event, $session, 'user_selection');
        }

        // Invalid selection - ask again
        return $this->buildResponse(false, 'invalid_selection', 'request_event_selection_again', [
            'available_events' => $availableEvents,
            'session_id' => $session->session_id
        ]);
    }

    /**
     * Match user input to available events
     * Supports: numbers (1, 2, 3), event names, partial matches
     */
    private function matchEventSelection(string $input, array $availableEvents): ?array
    {
        $input = trim(strtolower($input));

        // Try numeric selection (1, 2, 3)
        if (is_numeric($input)) {
            $index = (int)$input - 1;
            if (isset($availableEvents[$index])) {
                return $availableEvents[$index];
            }
        }

        // Try exact name match
        foreach ($availableEvents as $event) {
            if (strtolower($event['name']) === $input) {
                return $event;
            }
        }

        // Try partial name match
        foreach ($availableEvents as $event) {
            if (stripos($event['name'], $input) !== false) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Request event selection from user (multiple events scenario)
     */
    private function requestEventSelection(WhatsAppSession $session, Collection $events): array
    {
        $eventOptions = $events->map(function($event, $index) {
            return [
                'id' => $event->id,
                'name' => $event->name,
                'date' => $event->date,
                'number' => $index + 1
            ];
        })->values()->toArray();

        $session->update([
            'conversation_state' => 'awaiting_event_selection',
            'context_data' => [
                'available_events' => $eventOptions,
                'requested_at' => now()->toISOString()
            ]
        ]);

        return $this->buildResponse(false, 'multiple_active_events', 'request_event_selection', [
            'available_events' => $eventOptions,
            'session_id' => $session->session_id
        ]);
    }

    /**
     * Get or create WhatsApp session for a persona
     */
    private function getOrCreateSession(Persona $persona, string $phoneNumber): WhatsAppSession
    {
        // Find active session
        $session = WhatsAppSession::where('phone_number', $phoneNumber)
            ->where('expires_at', '>', now())
            ->first();

        if ($session) {
            // Extend existing session
            $session->extend();
            return $session;
        }

        // Create new session
        return WhatsAppSession::create([
            'session_id' => $this->generateSessionId(),
            'persona_id' => $persona->id,
            'phone_number' => $phoneNumber,
            'conversation_state' => 'active',
            'last_message_at' => now(),
            'expires_at' => now()->addHours(24)
        ]);
    }

    /**
     * Get active events for a persona (registered events happening soon)
     */
    private function getActiveEventsForPersona(Persona $persona): Collection
    {
        $events = EventAttendee::where('persona_id', $persona->id)
            ->whereHas('event', function($query) {
                $query->where('date', '>=', now()->subDay()) // Events from yesterday onwards
                      ->where('date', '<=', now()->addDays(30)); // Up to 30 days ahead
            })
            ->with('event')
            ->get()
            ->pluck('event')
            ->filter(); // Remove nulls

        // Sort by priority (today > this week > future)
        return $events->sortByDesc(function($event) {
            return $this->calculateEventPriority($event);
        })->values();
    }

    /**
     * Calculate event priority score
     * Higher score = higher priority
     */
    private function calculateEventPriority(Event $event): int
    {
        $date = Carbon::parse($event->date);
        $score = 0;

        // Happening today = highest priority
        if ($date->isToday()) {
            $score += 1000;
        }
        // Happening this week
        elseif ($date->isCurrentWeek()) {
            $score += 500;
        }
        // Future events
        else {
            $daysUntil = $date->diffInDays(now(), false);
            $score += max(0, 100 - $daysUntil);
        }

        return $score;
    }

    /**
     * Check if event is still active/relevant
     */
    private function isEventActive(Event $event): bool
    {
        $eventDate = Carbon::parse($event->date);
        
        // Event is active if:
        // - It's in the future
        // - It's today
        // - It was yesterday (for post-event queries)
        return $eventDate->isFuture() || 
               $eventDate->isToday() || 
               $eventDate->greaterThanOrEqualTo(now()->subDay());
    }

    /**
     * Update persona's last interaction when QR is scanned
     */
    public function updateLastInteraction(int $personaId, int $eventId, string $qrType): void
    {
        // Update persona's last interaction
        Persona::where('id', $personaId)->update([
            'last_interacted_event_id' => $eventId,
            'last_interaction_at' => now()
        ]);

        // Update event attendee's QR scan history
        EventAttendee::updateOrCreate(
            [
                'persona_id' => $personaId,
                'event_id' => $eventId
            ],
            [
                'last_qr_scan_type' => $qrType,
                'last_qr_scan_at' => now()
            ]
        );

        // Update session if exists
        $session = WhatsAppSession::where('persona_id', $personaId)
            ->where('expires_at', '>', now())
            ->first();

        if ($session) {
            $session->update([
                'current_event_id' => $eventId,
                'conversation_state' => 'resolved'
            ]);
        }
    }

    /**
     * Log AI conversation for analytics
     */
    public function logConversation(array $data): void
    {
        AiConversation::create([
            'persona_id' => $data['persona_id'],
            'event_id' => $data['event_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'user_query' => $data['user_query'],
            'ai_response' => $data['ai_response'],
            'context_source' => $data['context_source'] ?? null,
            'metadata' => $data['metadata'] ?? null
        ]);
    }

    /**
     * Clean expired sessions (run via scheduler)
     */
    public function cleanExpiredSessions(): int
    {
        return WhatsAppSession::expired()->delete();
    }

    /**
     * Generate unique session ID
     */
    private function generateSessionId(): string
    {
        return 'wa_' . uniqid() . '_' . time() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Build standardized response array
     */
    private function buildResponse(
        bool $resolved, 
        string $reason, 
        string $action, 
        array $additionalData = []
    ): array {
        return array_merge([
            'resolved' => $resolved,
            'reason' => $reason,
            'action' => $action
        ], $additionalData);
    }

    /**
     * Build event-resolved response
     */
    private function buildEventResponse(Event $event, WhatsAppSession $session, string $source): array
    {
        return [
            'resolved' => true,
            'event_id' => $event->id,
            'event_name' => $event->name,
            'event_date' => $event->date,
            'vector_collection' => "event_{$event->id}",
            'session_id' => $session->session_id,
            'context_source' => $source,
            'metadata' => [
                'event_details' => $event->detail ?? '',
                'campaign_id' => $event->campaign_id ?? null
            ]
        ];
    }
}
