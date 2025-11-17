<?php

namespace App\Http\Controllers;

use App\Models\Mascota;
use Illuminate\Http\Request;

class MascotaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Persona $persona)
    {
        return response()->json($persona->mascotas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Persona $persona)
    {
        $validatedData = $request->validate([
            'reino_animal' => 'required|string|max:255',
            'edad' => 'required|numeric|min:0',
            'nombre' => 'required|string|max:255',
        ]);

        $mascota = $persona->mascotas()->create($validatedData);
        return response()->json($mascota, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Persona $persona, Mascota $mascota)
    {
        if ($mascota->persona_id !== $persona->id) {
            return response()->json(['message' => 'Mascota no pertenece a la persona especificada.'], 404);
        }
        return response()->json($mascota);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Persona $persona, Mascota $mascota)
    {
        if ($mascota->persona_id !== $persona->id) {
            return response()->json(['message' => 'Mascota no pertenece a la persona especificada.'], 404);
        }

        $validatedData = $request->validate([
            'reino_animal' => 'sometimes|required|string|max:255',
            'edad' => 'sometimes|required|numeric|min:0',
            'nombre' => 'sometimes|required|string|max:255',
        ]);

        $mascota->update($validatedData);
        return response()->json($mascota);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Persona $persona, Mascota $mascota)
    {
        if ($mascota->persona_id !== $persona->id) {
            return response()->json(['message' => 'Mascota no pertenece a la persona especificada.'], 404);
        }
        $mascota->delete();
        return response()->json(null, 204);
    }
}
