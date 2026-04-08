<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $cacheKey = 'personas_list_search_' . md5($search . serialize($request->all()));

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () use ($request) {
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
            return response()->json($personas);
        });
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        \Log::info('Datos recibidos para Persona:', $request->all());

        try {
            $validatedData = $request->validate([
                'cedula' => 'required|string|max:255|unique:personas,cedula',
                'curp' => 'nullable|string|max:18',
                'clave_elector' => 'nullable|string|max:18',
                'nombre' => 'required|string|max:255',
                'apellido_paterno' => 'required|string|max:255',
                'apellido_materno' => 'required|string|max:255',
                'edad' => 'required|integer|min:0',
                'sexo' => 'required|in:H,M,O',
                'calle' => 'required|string|max:255',
                'numero_exterior' => 'required|string|max:255',
                'numero_interior' => 'nullable|string|max:255',
                'colonia' => 'required|string|max:255',
                'codigo_postal' => 'required|string|max:10',
                'municipio' => 'required|string|max:255',
                'estado' => 'required|string|max:255',
                'numero_celular' => 'nullable|string|max:20',
                'numero_telefono' => 'nullable|string|max:20',
                'tags' => 'nullable|array',
                'universes' => 'nullable|array',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
            ]);

            // Normalizar celular si viene
            if (!empty($validatedData['numero_celular'])) {
                $validatedData['numero_celular'] = $this->normalizePhoneNumber($validatedData['numero_celular']);
                $validatedData['numero_telefono'] = $validatedData['numero_celular'];
            }

            \Log::info('Datos validados para Persona:', $validatedData);

            $persona = Persona::create($validatedData);
            
            // Actualizar Geo (PostGIS) si hay coordenadas
            if (!empty($validatedData['latitude']) && !empty($validatedData['longitude'])) {
                $lat = $validatedData['latitude'];
                $lng = $validatedData['longitude'];
                
                DB::table('personas')
                    ->where('id', $persona->id)
                    ->update([
                        'location' => DB::raw("ST_SetSRID(ST_MakePoint($lng, $lat), 4326)")
                    ]);
            }

            \Log::info('Persona creada exitosamente:', ['id' => $persona->id]);

            // Invalidad Cache
            \Illuminate\Support\Facades\Cache::flush(); 

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
            'cedula' => 'sometimes|required|string|max:255|unique:personas,cedula,' . $persona->id,
            'curp' => 'nullable|string|max:18',
            'clave_elector' => 'nullable|string|max:18',
            'nombre' => 'sometimes|required|string|max:255',
            'apellido_paterno' => 'sometimes|required|string|max:255',
            'apellido_materno' => 'sometimes|required|string|max:255',
            'edad' => 'sometimes|required|integer|min:0',
            'sexo' => 'sometimes|required|in:H,M,O',
            'calle' => 'sometimes|required|string|max:255',
            'numero_exterior' => 'sometimes|required|string|max:255',
            'numero_interior' => 'nullable|string|max:255',
            'colonia' => 'sometimes|required|string|max:255',
            'codigo_postal' => 'sometimes|required|string|max:10',
            'municipio' => 'sometimes|required|string|max:255',
            'estado' => 'sometimes|required|string|max:255',
            'numero_celular' => 'nullable|string|max:20',
            'numero_telefono' => 'nullable|string|max:20',
            'tags' => 'nullable|array',
            'universes' => 'nullable|array',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if (!empty($validatedData['numero_celular'])) {
            $validatedData['numero_celular'] = $this->normalizePhoneNumber($validatedData['numero_celular']);
            $validatedData['numero_telefono'] = $validatedData['numero_celular'];
        }

        $persona->update($validatedData);

        // Invalidate Cache
        \Illuminate\Support\Facades\Cache::flush();

        // Actualizar Geo (PostGIS) si hay coordenadas
        if (!empty($validatedData['latitude']) && !empty($validatedData['longitude'])) {
            $lat = $validatedData['latitude'];
            $lng = $validatedData['longitude'];
            
            DB::table('personas')
                ->where('id', $persona->id)
                ->update([
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint($lng, $lat), 4326)")
                ]);
        }

        return response()->json($persona);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Persona $persona)
    {
        $persona->delete();
        \Illuminate\Support\Facades\Cache::flush();
        return response()->json(null, 204);
    }

    /**
     * Get bonus points for a specific persona.
     */
    public function bonusPoints(Persona $persona)
    {
        $balance = $persona->loyalty_balance;
        $history = $persona->bonusPointsHistory()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'persona' => $persona,
            'persona_id' => $persona->id,
            'current_balance' => $balance,
            'history' => $history
        ]);
    /**
     * Normalize phone number format
     */
    private function normalizePhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 15) {
            $phone = substr($phone, 0, 12);
        }
        if (strlen($phone) == 10) {
            $phone = '52' . $phone;
        }
        return $phone;
    }
}
