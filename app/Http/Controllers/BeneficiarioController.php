<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use App\Models\Persona;
use Illuminate\Http\Request;

class BeneficiarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Persona $persona)
    {
        return response()->json($persona->beneficiarios);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Persona $persona)
    {
        $validatedData = $request->validate([
            'tipo' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $beneficiario = $persona->beneficiarios()->create($validatedData);
        return response()->json($beneficiario, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Persona $persona, Beneficiario $beneficiario)
    {
        if ($beneficiario->persona_id !== $persona->id) {
            return response()->json(['message' => 'Beneficiario no pertenece a la persona especificada.'], 404);
        }
        return response()->json($beneficiario);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Persona $persona, Beneficiario $beneficiario)
    {
        if ($beneficiario->persona_id !== $persona->id) {
            return response()->json(['message' => 'Beneficiario no pertenece a la persona especificada.'], 404);
        }

        $validatedData = $request->validate([
            'tipo' => 'sometimes|required|string|max:255',
            'nombre' => 'sometimes|required|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $beneficiario->update($validatedData);
        return response()->json($beneficiario);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Persona $persona, Beneficiario $beneficiario)
    {
        if ($beneficiario->persona_id !== $persona->id) {
            return response()->json(['message' => 'Beneficiario no pertenece a la persona especificada.'], 404);
        }
        $beneficiario->delete();
        return response()->json(null, 204);
    }
}
