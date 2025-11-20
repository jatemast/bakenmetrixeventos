<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    /**
     * Store a newly created campaign in storage.
     */
    public function store(CampaignRequest $request): JsonResponse
    {
        $data = $request->validated();

        $fileFields = [
            'citizen_segmentation_file',
            'leader_segmentation_file',
            'militant_segmentation_file',
        ];

        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $filePath = $request->file($field)->store('campaign_files', 'public');
                $data[$field] = $filePath;
            }
        }

        $campaign = Campaign::create($data);

        return response()->json([
            'message' => 'Campaña creada exitosamente',
            'campaign' => $campaign
        ], 201);
    }

    /**
     * Display a listing of the campaigns.
     */
    public function index(): JsonResponse
    {
        $campaigns = Campaign::all(); // Carga todas las campañas sin los eventos
        return response()->json([
            'campaigns' => $campaigns
        ]);
    }

    /**
     * Display the specified campaign.
     */
    public function show(string $id): JsonResponse
    {
        $campaign = Campaign::with('events')->findOrFail($id);

        return response()->json([
            'campaign' => $campaign
        ]);
    }

    /**
     * Update the specified campaign in storage.
     */
    public function update(CampaignRequest $request, string $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $data = $request->validated();

        $fileFields = [
            'citizen_segmentation_file',
            'leader_segmentation_file',
            'militant_segmentation_file',
        ];

        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                // Eliminar archivo antiguo si existe
                if ($campaign->{$field} && Storage::disk('public')->exists($campaign->{$field})) {
                    Storage::disk('public')->delete($campaign->{$field});
                }
                $filePath = $request->file($field)->store('campaign_files', 'public');
                $data[$field] = $filePath;
            } elseif (isset($data[$field]) && $data[$field] === null) {
                // Si el campo se envía como null, eliminar el archivo existente
                if ($campaign->{$field} && Storage::disk('public')->exists($campaign->{$field})) {
                    Storage::disk('public')->delete($campaign->{$field});
                }
                $data[$field] = null;
            }
        }

        $campaign->update($data);

        return response()->json([
            'message' => 'Campaña actualizada exitosamente',
            'campaign' => $campaign
        ]);
    }

    /**
     * Remove the specified campaign from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $fileFields = [
            'citizen_segmentation_file',
            'leader_segmentation_file',
            'militant_segmentation_file',
        ];

        foreach ($fileFields as $field) {
            if ($campaign->{$field} && Storage::disk('public')->exists($campaign->{$field})) {
                Storage::disk('public')->delete($campaign->{$field});
            }
        }

        $campaign->delete();

        return response()->json([
            'message' => 'Campaña eliminada exitosamente'
        ], 204);
    }
}
