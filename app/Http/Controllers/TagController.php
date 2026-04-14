<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    /**
     * List all tags with counts
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Tag::withCount(['personas', 'events'])->orderBy('name')->get()
        ]);
    }

    /**
     * Store a new tag
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:tags,name',
            'type' => 'nullable|string',
            'color' => 'nullable|string'
        ]);

        $tag = Tag::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => $validated['type'] ?? 'general',
            'color' => $validated['color'] ?? '#6366f1'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tag creado exitosamente',
            'data' => $tag
        ], 201);
    }

    /**
     * Show a tag
     */
    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => Tag::with(['personas', 'events'])->findOrFail($id)
        ]);
    }

    /**
     * Update a tag
     */
    public function update(Request $request, $id)
    {
        $tag = Tag::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|unique:tags,name,' . $id,
            'type' => 'nullable|string',
            'color' => 'nullable|string'
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $tag->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tag actualizado',
            'data' => $tag
        ]);
    }

    /**
     * Delete a tag
     */
    public function destroy($id)
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();
        return response()->json(null, 204);
    }

    /**
     * Get tags by type (useful for PWA grouping)
     */
    public function getByCategory()
    {
        $tags = Tag::all()->groupBy('type');
        return response()->json([
            'success' => true,
            'data' => $tags
        ]);
    }
}
