<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Http\Request;

class PersonaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Persona::query();

        // Búsqueda global (nombre, apellido paterno, apellido materno, número de celular, número de teléfono, ID)
        if ($request->has('search')) {
            $searchTerm = strtolower($request->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(apellido_paterno) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(apellido_materno) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(numero_celular) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(numero_telefono) LIKE ?', ['%' . $searchTerm . '%']);
                // Si el término de búsqueda es numérico, también buscamos por ID
                if (is_numeric($searchTerm)) {
                    $q->orWhere('id', $searchTerm);
                }
            });
        }

        // Filtros específicos (pueden combinarse con la búsqueda global o usarse solos)
        if ($request->has('nombre') && !$request->has('search')) {
            $nombre = strtolower($request->nombre);
            $query->whereRaw('LOWER(nombre) LIKE ?', ['%' . $nombre . '%']);
        }

        if ($request->has('apellido_paterno') && !$request->has('search')) {
            $apellidoPaterno = strtolower($request->apellido_paterno);
            $query->whereRaw('LOWER(apellido_paterno) LIKE ?', ['%' . $apellidoPaterno . '%']);
        }

        if ($request->has('apellido_materno') && !$request->has('search')) {
            $apellidoMaterno = strtolower($request->apellido_materno);
            $query->whereRaw('LOWER(apellido_materno) LIKE ?', ['%' . $apellidoMaterno . '%']);
        }

        if ($request->has('numero_celular') && !$request->has('search')) {
            $numeroCelular = strtolower($request->numero_celular);
            $query->whereRaw('LOWER(numero_celular) LIKE ?', ['%' . $numeroCelular . '%']);
        }

        if ($request->has('numero_telefono') && !$request->has('search')) {
            $numeroTelefono = strtolower($request->numero_telefono);
            $query->whereRaw('LOWER(numero_telefono) LIKE ?', ['%' . $numeroTelefono . '%']);
        }

        $personas = $query->get();

        if ($personas->isEmpty()) {
            return response()->json(['message' => 'Registros no encontrados'], 404);
        }

        return response()->json($personas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        \Log::info('Datos recibidos para Persona:', $request->all());

        try {
            $validatedData = $request->validate([
                'nombre' => 'required|string|max:255',
                'apellido_paterno' => 'required|string|max:255',
                'apellido_materno' => 'required|string|max:255',
                'edad' => 'required|integer|min:0',
                'sexo' => 'required|in:H,M',
                'calle' => 'required|string|max:255',
                'numero_exterior' => 'required|string|max:255',
                'numero_interior' => 'nullable|string|max:255',
                'colonia' => 'required|string|max:255',
                'codigo_postal' => 'required|string|max:10',
                'municipio' => 'required|string|max:255',
                'estado' => 'required|string|max:255',
                'numero_celular' => 'nullable|string|max:20',
                'numero_telefono' => 'nullable|string|max:20',
            ]);

            \Log::info('Datos validados para Persona:', $validatedData);

            $persona = Persona::create($validatedData);
            \Log::info('Persona creada exitosamente:', ['id' => $persona->id]);
            return response()->json($persona, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación al crear Persona:', ['errors' => $e->errors(), 'request' => $request->all()]);
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Error inesperado al crear Persona:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'request' => $request->all()]);
            return response()->json(['message' => 'Error interno del servidor', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Persona $persona)
    {
        return response()->json($persona);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Persona $persona)
    {
        $validatedData = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'apellido_paterno' => 'sometimes|required|string|max:255',
            'apellido_materno' => 'sometimes|required|string|max:255',
            'edad' => 'sometimes|required|integer|min:0',
            'sexo' => 'sometimes|required|in:H,M',
            'calle' => 'sometimes|required|string|max:255',
            'numero_exterior' => 'sometimes|required|string|max:255',
            'numero_interior' => 'nullable|string|max:255',
            'colonia' => 'sometimes|required|string|max:255',
            'codigo_postal' => 'sometimes|required|string|max:10',
            'municipio' => 'sometimes|required|string|max:255',
            'estado' => 'sometimes|required|string|max:255',
            'numero_celular' => 'nullable|string|max:20',
            'numero_telefono' => 'nullable|string|max:20',
        ]);

        $persona->update($validatedData);
        return response()->json($persona);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Persona $persona)
    {
        $persona->delete();
        return response()->json(null, 204);
    }
}
