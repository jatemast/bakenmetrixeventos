<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TerritoryController extends Controller
{
    /**
     * Get territory hierarchy (Municipios -> Colonias).
     * Cached for performance.
     */
    public function index()
    {
        return Cache::remember('territory_hierarchy', 3600, function () {
            // Get municipios and colonias with counts
            $data = Persona::select('municipio', 'colonia', \DB::raw('count(*) as count'))
                ->whereNotNull('municipio')
                ->whereNotNull('colonia')
                ->groupBy('municipio', 'colonia')
                ->orderBy('municipio')
                ->orderBy('colonia')
                ->get();

            $hierarchy = [];
            foreach ($data as $item) {
                if (!isset($hierarchy[$item->municipio])) {
                    $hierarchy[$item->municipio] = [
                        'name' => $item->municipio,
                        'count' => 0,
                        'children' => []
                    ];
                }
                
                $hierarchy[$item->municipio]['count'] += $item->count;
                $hierarchy[$item->municipio]['children'][] = [
                    'name' => $item->colonia,
                    'count' => $item->count
                ];
            }

            return response()->json([
                'success' => true,
                'data' => array_values($hierarchy)
            ]);
        });
    }

    /**
     * Refresh hierarchy cache.
     */
    public function refresh()
    {
        Cache::forget('territory_hierarchy');
        return $this->index();
    }
}
