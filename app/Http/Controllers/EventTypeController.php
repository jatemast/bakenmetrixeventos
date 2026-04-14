<?php

namespace App\Http\Controllers;

use App\Models\EventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventTypeController extends Controller
{
    /**
     * Display a listing of the event types with their template info.
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
     * Get a single event type with its full template.
     */
    public function show(string $id): JsonResponse
    {
        $type = EventType::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $type,
            'template_preview' => [
                'form_fields_count' => $this->countFormFields($type->default_form_schema),
                'has_slots' => !empty($type->default_slot_config),
                'has_points' => !empty($type->default_points_config),
                'requires_appointment' => $type->requires_appointment,
                'has_beneficiaries' => $type->has_beneficiaries,
            ],
        ]);
    }

    /**
     * Store a newly created event type in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:event_types,name',
            'icon' => 'nullable|string',
            'requires_appointment' => 'boolean',
            'has_beneficiaries' => 'boolean',
            'beneficiary_label' => 'nullable|string',
            'success_message' => 'nullable|string',
            'default_form_schema' => 'nullable|array',
            'default_slot_config' => 'nullable|array',
            'default_qr_config' => 'nullable|array',
            'default_points_config' => 'nullable|array',
        ]);

        $type = EventType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de evento creado exitosamente',
            'data' => $type
        ], 201);
    }

    /**
     * Update an existing event type.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $type = EventType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:event_types,name,' . $id,
            'icon' => 'nullable|string',
            'requires_appointment' => 'boolean',
            'has_beneficiaries' => 'boolean',
            'beneficiary_label' => 'nullable|string',
            'success_message' => 'nullable|string',
            'default_form_schema' => 'nullable|array',
            'default_slot_config' => 'nullable|array',
            'default_qr_config' => 'nullable|array',
            'default_points_config' => 'nullable|array',
        ]);

        $type->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de evento actualizado',
            'data' => $type
        ]);
    }

    /**
     * Delete an event type.
     */
    public function destroy(string $id): JsonResponse
    {
        $type = EventType::findOrFail($id);

        // Check if any events use this type
        $eventsCount = $type->events()->count();
        if ($eventsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar: {$eventsCount} eventos usan este tipo.",
            ], 409);
        }

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tipo de evento eliminado',
        ]);
    }

    /**
     * Preview the form template for an event type.
     * Useful for the frontend to show a preview before creating the event.
     */
    public function previewTemplate(string $id): JsonResponse
    {
        $type = EventType::findOrFail($id);

        return response()->json([
            'success' => true,
            'event_type' => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'icon' => $type->icon,
            ],
            'template' => [
                'form_schema' => $type->default_form_schema,
                'slot_config' => $type->default_slot_config,
                'points_config' => $type->default_points_config,
                'qr_config' => $type->default_qr_config,
                'requires_appointment' => $type->requires_appointment,
                'has_beneficiaries' => $type->has_beneficiaries,
                'beneficiary_label' => $type->beneficiary_label,
                'success_message' => $type->success_message,
            ],
            'stats' => [
                'form_sections' => count($type->default_form_schema['sections'] ?? []),
                'form_fields_count' => $this->countFormFields($type->default_form_schema),
                'events_using_this_type' => $type->events()->count(),
            ],
        ]);
    }

    /**
     * Count total form fields in a schema.
     */
    private function countFormFields(?array $schema): int
    {
        if (!$schema || empty($schema['sections'])) {
            return 0;
        }

        $count = 0;
        foreach ($schema['sections'] as $section) {
            $count += count($section['fields'] ?? []);
        }
        return $count;
    }
}
