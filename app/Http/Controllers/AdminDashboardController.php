<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Tag;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $query = Persona::query();

        // Advanced Filters
        if ($request->search) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('nombre', 'LIKE', "%$s%")
                  ->orWhere('apellido_paterno', 'LIKE', "%$s%")
                  ->orWhere('curp', 'LIKE', "%$s%")
                  ->orWhere('codigo_ciudadano', 'LIKE', "%$s%")
                  ->orWhere('clave_elector', 'LIKE', "%$s%")
                  ->orWhere('numero_celular', 'LIKE', "%$s%");
            });
        }

        if ($request->category) {
            $query->where('categoria', $request->category);
        }

        if ($request->municipio) {
            $query->where('municipio', $request->municipio);
        }

        if ($request->is_leader !== null) {
            $query->where('is_leader', $request->is_leader);
        }

        // Tag Filtering (The "Branch" Logic for filtering)
        if ($request->tags && is_array($request->tags)) {
            $query->whereJsonContains('tags', $request->tags);
        }

        $personas = $query->latest()->paginate(25);
        $total = Persona::count();
        $stats = [
            'leaders' => Persona::where('is_leader', true)->count(),
            'by_gender' => Persona::select('sexo', DB::raw('count(*) as count'))->groupBy('sexo')->get(),
            'by_category' => Persona::select('categoria', DB::raw('count(*) as count'))->whereNotNull('categoria')->groupBy('categoria')->get()
        ];

        $allCategories = Persona::whereNotNull('categoria')->distinct()->pluck('categoria');
        $allMunicipios = Persona::whereNotNull('municipio')->distinct()->pluck('municipio');

        return view('admin_dashboard', compact('personas', 'total', 'stats', 'allCategories', 'allMunicipios'));
    }

    public function getMapData()
    {
        // Get coordinates for mapping
        return Persona::whereNotNull('location')
            ->select('id', 'nombre', 'apellido_paterno', 'location', 'categoria', 'codigo_ciudadano')
            ->get()
            ->map(function($p) {
                $coords = explode(',', $p->location);
                return [
                    'id' => $p->id,
                    'name' => $p->nombre . ' ' . $p->apellido_paterno,
                    'lat' => (float)$coords[0],
                    'lng' => (float)($coords[1] ?? 0),
                    'code' => $p->codigo_ciudadano,
                    'cat' => $p->categoria
                ];
            });
    }
}
