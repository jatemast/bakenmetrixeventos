<?php

namespace App\Http\Controllers;

use App\Services\EventContextService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    protected $eventContextService;

    public function __construct(EventContextService $eventContextService)
    {
        $this->eventContextService = $eventContextService;
    }

    /**
     * Resolve event context for AI queries (called by n8n)
     * This is the CORE API endpoint that n8n calls before querying the vector store
     */
    public function resolveEventContext(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'required|string',
            'message' => 'nullable|string'
        ]);

        try {
            $context = $this->eventContextService->resolveEventContext(
                $request->phone_number,
                $request->message
            );

            return response()->json([
                'success' => true,
                'context' => $context
            ], 200);

        } catch (\Exception $e) {
            Log::error('Event context resolution error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to resolve event context',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log AI conversation (called by n8n after response is sent)
     */
    public function logAiConversation(Request $request): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|integer|exists:personas,id',
            'event_id' => 'nullable|integer|exists:events,id',
            'session_id' => 'nullable|string',
            'user_query' => 'required|string',
            'ai_response' => 'required|string',
            'context_source' => 'nullable|string',
            'metadata' => 'nullable|array'
        ]);

        try {
            $this->eventContextService->logConversation($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Conversation logged successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('AI conversation logging error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to log conversation'
            ], 500);
        }
    }
}
