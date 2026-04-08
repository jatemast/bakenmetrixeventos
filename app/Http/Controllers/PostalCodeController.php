<?php

namespace App\Http\Controllers;

use App\Models\PostalCode;
use Illuminate\Http\Request;

class PostalCodeController extends Controller
{
    /**
     * Lookup neighborhoods by CP from Database.
     */
    public function lookup($cp)
    {
        $matches = PostalCode::where('cp', $cp)->get();

        if ($matches->count() > 0) {
            $first = $matches->first();
            return response()->json([
                'success' => true,
                'data' => [
                    'cp' => $cp,
                    'municipio' => $first->municipio,
                    'estado' => $first->estado,
                    'colonias' => $matches->pluck('colonia')->unique()->values()->all()
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Código Postal no encontrado'
        ], 404);
    }

}
