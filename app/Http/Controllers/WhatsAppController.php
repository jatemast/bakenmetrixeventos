<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Persona;
use App\Models\EventAttendee;
use App\Services\EventContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function __construct(
        private readonly EventContextService $eventContextService
    ) {}

    /**
     * Handle incoming WhatsApp attendance keyword (ENTRADA/SALIDA)
     * OR generic registration query
     */
    public function handleAttendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'remitente' => 'required|string', // WhatsApp Number
            'texto' => 'required|string',
            'nombre' => 'nullable|string',
        ]);

        $phoneNumber = $data['remitente'];
        $text = strtoupper(trim($data['texto']));

        // 1. Resolve Persona
        $persona = Persona::where('numero_celular', $phoneNumber)->first();

        // 2. If it's a registration keyword, we actually handle it in n8n (FLOW 5 -> FLOW 6)
        // But if it reaches here, we can provide a fallback
        if (str_contains($text, 'REGISTRARME')) {
            return response()->json([
                'success' => true,
                'reply' => "¡Hola! Veo que quieres registrarte. Por favor, ayúdame con tu nombre completo y número de cédula para comenzar.",
                'action' => 'start_registration'
            ]);
        }

        // 3. Resolve context (which event?)
        $context = $this->eventContextService->resolveEventContext($phoneNumber, $text);

        if (!$context['resolved']) {
            if ($context['action'] === 'register_user') {
                return response()->json([
                    'success' => true,
                    'reply' => "Parece que aún no estás registrado en nuestro sistema. ¿Te gustaría registrarte ahora? Escribe 'REGISTRARME' para empezar.",
                    'action' => 'prompt_registration'
                ]);
            }

            if ($context['action'] === 'request_event_selection') {
                $options = collect($context['available_events'])->map(function($e) {
                    return "{$e['number']}. {$e['name']} ({$e['date']})";
                })->implode("\n");

                return response()->json([
                    'success' => true,
                    'reply' => "Veo que tienes varios eventos activos. ¿Sobre cuál deseas información?\n\n" . $options,
                    'action' => 'request_selection'
                ]);
            }

            return response()->json([
                'success' => false,
                'reply' => "Lo siento, no pude identificar a qué evento te refieres. ¿Puedes darme más detalles?",
                'action' => 'unknown'
            ]);
        }

        // 4. Handle Attendance Keywords (ENTRADA/SALIDA)
        $event = Event::find($context['event_id']);
        
        if ($text === 'ENTRADA') {
            return $this->processAttendance($persona, $event, 'entry');
        }

        if ($text === 'SALIDA') {
            return $this->processAttendance($persona, $event, 'exit');
        }

        // 5. Generic Query - Let AI handle it (but we return the context)
        return response()->json([
            'success' => true,
            'event_id' => $event->id,
            'event_name' => $event->name,
            'reply' => "Entiendo que estás interesado en el evento '{$event->name}'. ¿En qué puedo ayudarte hoy?",
            'action' => 'context_resolved'
        ]);
    }

    /**
     * Resolve event context from message or history (Used by n8n)
     */
    public function resolveEventContext(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp' => 'required|string',
            'text' => 'nullable|string'
        ]);

        $context = $this->eventContextService->resolveEventContext(
            $request->whatsapp, 
            $request->text
        );

        return response()->json($context);
    }

    /**
     * Get active events for a persona via WhatsApp number
     */
    public function getActiveEvents(Request $request): JsonResponse
    {
        $request->validate(['whatsapp' => 'required|string']);
        
        $persona = Persona::where('numero_celular', $request->whatsapp)->first();

        if (!$persona) {
            return response()->json(['success' => false, 'message' => 'Persona not found'], 404);
        }

        // Using the service logic for consistency
        $context = $this->eventContextService->resolveEventContext($persona->numero_celular);

        return response()->json([
            'success' => true,
            'persona' => [
                'id' => $persona->id,
                'nombre' => $persona->nombre,
            ],
            'context' => $context
        ]);
    }

    private function processAttendance(Persona $persona, Event $event, string $type): JsonResponse
    {
        // Simple attendance logic - typically we want QR scans, but via keyword we can also register it
        $attendee = EventAttendee::where('event_id', $event->id)
            ->where('persona_id', $persona->id)
            ->first();

        if (!$attendee) {
            $attendee = EventAttendee::create([
                'event_id' => $event->id,
                'persona_id' => $persona->id,
                'attendance_status' => 'registered',
            ]);
        }

        if ($type === 'entry') {
            if ($attendee->checkin_at) {
                return response()->json([
                    'success' => true,
                    'reply' => "¡Hola {$persona->nombre}! Ya registramos tu entrada al evento '{$event->name}' a las {$attendee->checkin_at->format('h:i A')}. ¡Disfruta!"
                ]);
            }

            $attendee->update([
                'checkin_at' => now(),
                'attendance_status' => 'entered'
            ]);

            return response()->json([
                'success' => true,
                'reply' => "¡Bienvenido {$persona->nombre}! Hemos registrado tu entrada al evento '{$event->name}'. No olvides registrar tu salida al final para acumular tus puntos."
            ]);
        }

        if ($type === 'exit') {
            if (!$attendee->checkin_at) {
                return response()->json([
                    'success' => true,
                    'reply' => "Hola {$persona->nombre}, para registrar tu salida primero debiste registrar tu entrada. Si crees que hay un error, contacta al personal del evento."
                ]);
            }

            if ($attendee->checkout_at) {
                return response()->json([
                    'success' => true,
                    'reply' => "Ya habías registrado tu salida de '{$event->name}'. ¡Gracias por asistir!"
                ]);
            }

            $attendee->update([
                'checkout_at' => now(),
                'attendance_status' => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'reply' => "¡Gracias por participar {$persona->nombre}! Hemos registrado tu salida de '{$event->name}'. Tus puntos han sido abonados a tu cuenta."
            ]);
        }

        return response()->json(['success' => false, 'reply' => 'Acción de asistencia no reconocida.']);
    }

    /**
     * Set conversation state for a WhatsApp number
     */
    public function setState(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'state' => 'required|string'
        ]);

        \Illuminate\Support\Facades\Cache::put('whatsapp_state_' . $request->phone, $request->state, 300); // 5 minutes

        return response()->json(['success' => true, 'message' => 'State updated']);
    }

    /**
     * Get conversation state for a WhatsApp number
     */
    public function getState(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);

        $state = \Illuminate\Support\Facades\Cache::get('whatsapp_state_' . $request->phone, 'IDLE');

        return response()->json(['success' => true, 'state' => $state]);
    }
}
