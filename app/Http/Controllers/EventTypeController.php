<?php

namespace App\Http\Controllers;

use App\Models\EventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventTypeController extends Controller
{
    /**
     * Display a listing of the event types.
     */
    public function index(): JsonResponse
    {
        $types = EventType::all();
        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Store a newly created event type in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:event_types,name',
            'tenant_id' => 'required|exists:tenants,id',
            'icon' => 'nullable|string',
            'requires_appointment' => 'boolean',
            'has_beneficiaries' => 'boolean',
            'beneficiary_label' => 'nullable|string'
        ]);

        $type = EventType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de evento creado exitosamente',
            'data' => $type
        ], 201);
    }
}
