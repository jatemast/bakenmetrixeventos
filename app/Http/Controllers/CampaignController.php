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
}
