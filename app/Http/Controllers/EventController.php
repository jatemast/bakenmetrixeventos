<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Campaign;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        // Los campos checkin_code y checkout_code se validan en EventRequest y no requieren generación aquí
        // Los campos bonus_points_for_attendee y bonus_points_for_leader también son validados por EventRequest

        $event = Event::create($data);

        // Adjuntar el evento a la campaña si campaign_id está presente y es válido
        if (isset($data['campaign_id'])) {
            $campaign = Campaign::findOrFail($data['campaign_id']);
            $campaign->events()->attach($event->id);
        }

        return response()->json([
            'message' => 'Evento creado exitosamente',
            'event' => $event
        ], 201);
    }

    /**
     * Update the specified event in storage.
     */
    public function update(EventRequest $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('ai_agent_info_file')) {
            // Eliminar archivo anterior si existe
            if ($event->ai_agent_info_file) {
                Storage::disk('public')->delete($event->ai_agent_info_file);
            }
            $filePath = $request->file('ai_agent_info_file')->store('event_files', 'public');
            $data['ai_agent_info_file'] = $filePath;
        }

        $event->update($data);

        return response()->json([
            'message' => 'Evento actualizado exitosamente',
            'event' => $event
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
     * Display the specified event publicly by QR code data.
     */
    public function showPublic(string $checkinCode): JsonResponse
    {
        $event = Event::where('checkin_code', $checkinCode)->with('campaign')->firstOrFail();

        return response()->json([
            'event' => $event
        ]);
    }
}