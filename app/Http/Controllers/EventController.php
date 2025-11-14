<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Campaign;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    /**
     * Store a newly created event in storage.
     */
    public function store(EventRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('ai_agent_info_file')) {
            $filePath = $request->file('ai_agent_info_file')->store('event_files', 'public');
            $data['ai_agent_info_file'] = $filePath;
        }

        $data['qr_code_data'] = (string) Str::uuid(); // Generar un código único para el QR

        $event = Event::create($data);

        return response()->json([
            'message' => 'Evento creado exitosamente',
            'event' => $event
        ], 201);
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
}