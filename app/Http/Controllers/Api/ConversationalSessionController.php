<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventContextService;
use App\Models\WhatsAppSession;
use App\Models\Persona;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ConversationalSessionController extends Controller
{
    public function __construct(
        private readonly EventContextService $eventContextService
    ) {}

    /**
     * Check if a session exists for the phone number or start a new one.
     */
    public function checkOrStart(Request $request): JsonResponse
    {
        $phone = $request->input('phone') ?? 
                 $request->input('whatsapp_number') ?? 
                 $request->input('sender') ?? 
                 $request->input('whatsapp') ??
                 $request->query('phone') ??
                 $request->query('whatsapp_number') ??
                 $request->query('whatsapp');

        if (!$phone) {
            return response()->json([
                'success' => false, 
                'message' => 'Phone number is required',
                'resolved' => false,
                'action' => 'none'
            ], 422);
        }

        $phone = $this->normalizePhoneNumber($phone);
        $message = $request->input('message') ?? $request->input('text') ?? $request->query('message');

        $persona = $this->findPersonaByPhone($phone);

        if (!$persona) {
            return response()->json([
                'resolved' => false,
                'reason' => 'persona_not_in_crm',
                'action' => 'none',
                'session' => null,
                'persona_exists' => false
            ]);
        }

        $session = WhatsAppSession::where('phone_number', $phone)
            ->where('expires_at', '>', now())
            ->first();

        if ($session && $session->conversation_state === 'active') {
            return response()->json([
                'resolved' => true,
                'action' => 'continue_conversation',
                'session' => $session->load('event'),
                'event_id' => $session->event_id ?? $session->current_event_id,
                'persona_exists' => true,
                'persona_id' => $persona->id
            ]);
        }

        $result = $this->eventContextService->resolveEventContext($phone, $message);
        $result['persona_exists'] = true;
        $result['persona_id'] = $persona->id;
        
        return response()->json($result);
    }

    /**
     * Update the current step of the conversation.
     */
    public function updateStep(Request $request): JsonResponse
    {
        $phone = $request->input('phone') ?? $request->input('from');
        $eventId = $request->input('event_id');
        $message = $request->input('message', '');
        $waitingFor = $request->input('waiting_for', 'start');

        if ($eventId && preg_match('/EVENT_(\d+)/', (string)$eventId, $matches)) {
            $eventId = $matches[1];
        }

        $phone = $this->normalizePhoneNumber($phone);

        $persona = $this->findPersonaByPhone($phone);
        
        if (!$persona) {
            return response()->json([
                'success' => false,
                'message' => 'Ciudadano no encontrado.',
                'prompt' => '👋 ¡Hola! Por favor regístrate primero para asistir.',
                'next_step' => 'register_required',
                'is_finished' => false
            ], 404);
        }

        $event = $eventId ? Event::find($eventId) : Event::where('status', 'active')->orderBy('id', 'desc')->first();

        if (!$event) {
            return response()->json([
                'success' => false,
                'prompt' => '⚠️ No hay eventos activos disponibles.'
            ], 404);
        }

        $session = WhatsAppSession::where('phone_number', $phone)->first();

        if (!$session || $waitingFor === 'start' || ($session->event_id != $event->id)) {
            $session = WhatsAppSession::updateOrCreate(
                ['phone_number' => $phone],
                [
                    'session_id' => 'wa_' . uniqid() . '_' . time(),
                    'persona_id' => $persona->id,
                    'event_id' => $event->id,
                    'conversation_state' => 'active',
                    'current_step' => 0,
                    'context_data' => [],
                    'last_message_at' => now(),
                    'expires_at' => now()->addHours(24)
                ]
            );
        }

        $formSchema = $event->form_schema;
        if (is_string($formSchema)) $formSchema = json_decode($formSchema, true);

        $questions = [];
        if ($formSchema && isset($formSchema['sections'])) {
            foreach ($formSchema['sections'] as $section) {
                foreach ($section['fields'] ?? [] as $field) $questions[] = $field;
            }
        }

        $currentStep = (int)$session->current_step;
        $contextData = is_string($session->context_data) ? json_decode($session->context_data, true) : ($session->context_data ?? []);

        if ($currentStep > 0 && $currentStep <= count($questions) && !empty($message) && $waitingFor !== 'start') {
            $prevQuestion = $questions[$currentStep - 1];
            $fieldName = $prevQuestion['key'] ?? $prevQuestion['name'] ?? "field_{$currentStep}";
            $contextData[$fieldName] = $message;
        }

        if ($currentStep < count($questions)) {
            $nextQuestion = $questions[$currentStep];
            $prompt = "✍️ *{$nextQuestion['label']}*";
            
            if (!empty($nextQuestion['options'])) {
                $optionsList = is_array($nextQuestion['options']) 
                    ? implode("\n", array_map(fn($opt) => "• " . (is_array($opt) ? ($opt['label'] ?? $opt['value'] ?? '') : $opt), $nextQuestion['options']))
                    : '';
                if ($optionsList) $prompt .= "\n\n{$optionsList}";
            }

            $nextStep = $currentStep + 1;
            $session->update(['current_step' => $nextStep, 'context_data' => $contextData, 'last_message_at' => now()]);

            return response()->json([
                'success' => true,
                'prompt' => $prompt,
                'next_step' => $nextStep,
                'is_finished' => false,
                'session_id' => $session->session_id
            ]);
        } else {
            $session->update([
                'context_data' => $contextData,
                'conversation_state' => 'active',
                'last_message_at' => now()
            ]);

            \App\Models\EventAttendee::updateOrCreate(
                ['event_id' => $event->id, 'persona_id' => $persona->id],
                ['attendance_status' => 'registered', 'registered_at' => now()]
            );

            return response()->json([
                'success' => true,
                'is_finished' => true,
                'prompt' => "🎉 ¡Registro completado exitosamente!",
                'session_id' => $session->session_id,
                'qr_url' => "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=EVT-{$event->id}-PER-{$persona->id}",
                'registration_position' => \App\Models\EventAttendee::where('event_id', $event->id)->count(),
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name ?? $event->detail,
                    'event_date' => $event->date ?? $event->event_date,
                    'event_time' => $event->time ?? $event->event_time,
                    'location' => $event->location ?? trim(($event->street ?? '') . ' ' . ($event->neighborhood ?? '')),
                    'confirmation_message' => $event->confirmation_message,
                    'logistic_instructions' => $event->logistic_instructions,
                    'form_schema' => $formSchema
                ],
                'context_data' => $contextData
            ]);
        }
    }

    /**
     * Mark a session as completed
     */
    public function complete(Request $request): JsonResponse
    {
        $phone = $request->input('phone');
        $sessionId = $request->input('session_id');

        $session = null;
        if ($sessionId) $session = WhatsAppSession::where('session_id', $sessionId)->first();
        if (!$session && $phone) {
            $phone = $this->normalizePhoneNumber($phone);
            $session = WhatsAppSession::where('phone_number', $phone)->first();
        }

        if (!$session) return response()->json(['success' => false], 404);

        $session->update([
            'conversation_state' => 'active', // Corregido para evitar restricción de DB
            'expires_at' => now()
        ]);

        $event = $session->event_id ? Event::find($session->event_id) : null;
        $qrUrl = $event ? "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=EVT-{$event->id}-PER-{$session->persona_id}" : null;

        return response()->json([
            'success' => true,
            'qr_url' => $qrUrl,
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->detail,
                'event_date' => $event->date,
                'event_time' => $event->time,
                'location' => trim(($event->street ?? '') . ' ' . ($event->neighborhood ?? '')),
            ] : null
        ]);
    }

    private function findPersonaByPhone(string $phone): ?Persona
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $variants = [$phone];
        if (strlen($phone) >= 12) $variants[] = substr($phone, 2);
        if (strlen($phone) == 10) {
            $variants[] = '57' . $phone;
            $variants[] = '52' . $phone;
        }
        return Persona::whereIn('numero_celular', array_unique($variants))->first();
    }

    private function normalizePhoneNumber($phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', (string)$phone);
        if (strlen($phone) >= 20) {
            $half = strlen($phone) / 2;
            if (substr($phone, 0, $half) === substr($phone, $half)) $phone = substr($phone, 0, $half);
        }
        if (strlen($phone) == 10) $phone = '52' . $phone;
        return $phone;
    }
}
