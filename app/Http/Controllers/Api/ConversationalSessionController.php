<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventContextService;
use App\Models\WhatsAppSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConversationalSessionController extends Controller
{
    public function __construct(
        private readonly EventContextService $eventContextService
    ) {}

    /**
     * Check if a session exists for the phone number or start a new one.
     * Resolves event context automatically.
     */
    public function checkOrStart(Request $request): JsonResponse
    {
        // Handle both GET (query) and POST (body)
        // Check multiple possible parameter names for maximum n8n compatibility
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

        $message = $request->input('message') ?? $request->input('text') ?? $request->query('message');

        $result = $this->eventContextService->resolveEventContext($phone, $message);

        return response()->json($result);
    }

    /**
     * Update the current step of the conversation
     */
    public function updateStep(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'step' => 'required|string',
            'metadata' => 'nullable|array'
        ]);

        $session = WhatsAppSession::where('session_id', $request->session_id)->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        $session->update([
            'current_step' => $request->step,
            'metadata' => array_merge($session->metadata ?? [], $request->metadata ?? [])
        ]);

        return response()->json([
            'success' => true, 
            'session' => $session
        ]);
    }

    /**
     * Mark a session as completed
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        $session = WhatsAppSession::where('session_id', $request->session_id)->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        $session->update([
            'conversation_state' => 'completed',
            'expires_at' => now() // End it immediately
        ]);

        return response()->json(['success' => true]);
    }
}
