<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    /**
     * Get n8n workflow health and status.
     * Proxies requests to n8n Public API to avoid leaking keys to the frontend.
     */
    public function n8nStatus()
    {
        $apiUrl = config('services.n8n.api_url', 'https://n8n.soymetrix.com/api/v1');
        $apiKey = config('services.n8n.api_key', env('N8N_API_KEY'));

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'n8n API Key not configured'
            ], 500);
        }

        try {
            // Fetch workflows
            $response = Http::withHeaders([
                'X-N8N-API-KEY' => $apiKey
            ])->get("{$apiUrl}/workflows", [
                'limit' => 50
            ]);

            if ($response->failed()) {
                throw new \Exception("n8n API unreachable: " . $response->status());
            }

            $workflows = $response->json();

            // Fetch recent executions to calculate health
            $execResponse = Http::withHeaders([
                'X-N8N-API-KEY' => $apiKey
            ])->get("{$apiUrl}/executions", [
                'limit' => 100
            ]);

            $executions = $execResponse->json()['data'] ?? [];
            
            // Group health by workflow
            $stats = [];
            foreach ($executions as $exec) {
                $wid = $exec['workflowId'];
                if (!isset($stats[$wid])) {
                    $stats[$wid] = ['success' => 0, 'error' => 0];
                }
                if ($exec['status'] === 'success') {
                    $stats[$wid]['success']++;
                } else {
                    $stats[$wid]['error']++;
                }
            }

            // Merge stats into workflows
            $data = collect($workflows['data'])->map(function ($w) use ($stats) {
                $wStats = $stats[$w['id']] ?? ['success' => 0, 'error' => 0];
                $total = $wStats['success'] + $wStats['error'];
                $w['health_score'] = $total > 0 ? round(($wStats['success'] / $total) * 100) : 100;
                $w['recent_success'] = $wStats['success'];
                $w['recent_errors'] = $wStats['error'];
                return $w;
            });

            return response()->json([
                'success' => true,
                'workflows' => $data,
                'timestamp' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error("Monitoring Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
