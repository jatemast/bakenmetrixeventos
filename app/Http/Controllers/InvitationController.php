<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Event;
use App\Models\Persona;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function log(Request $request)
    {
        $validated = $request->validate([
            'persona_id' => 'nullable|exists:personas,id',
            'whatsapp' => 'nullable|string',
            'event_id' => 'required|exists:events,id',
            'status' => 'required|string',
        ]);

        if (empty($validated['persona_id']) && empty($validated['whatsapp'])) {
            return response()->json(['message' => 'The persona id or whatsapp field is required.'], 422);
        }

        $personaId = $validated['persona_id'] ?? null;

        if (!$personaId && !empty($validated['whatsapp'])) {
            $normalizedWhatsapp = preg_replace('/[^0-9]/', '', $validated['whatsapp']);
            // A veces envían con el 52 o 57 por delante
            $persona = Persona::where('numero_celular', 'LIKE', '%' . substr($normalizedWhatsapp, -10))->first();
            
            if (!$persona) {
                return response()->json(['message' => 'Persona not found for whatsapp: ' . $validated['whatsapp']], 404);
            }
            $personaId = $persona->id;
        } else {
            $persona = Persona::find($personaId);
        }

        $invitation = Invitation::create([
            'persona_id' => $personaId,
            'event_id' => $validated['event_id'],
            'status' => $validated['status']
        ]);

        // Update persona's last_invited_event_id
        if ($persona) {
            $persona->update([
                'last_invited_event_id' => $validated['event_id']
            ]);
        }

        return response()->json([
            'message' => 'Invitation logged successfully',
            'invitation' => $invitation
        ]);
    }

    public function complete($id)
    {
        $event = Event::findOrFail($id);
        
        // Update event status if needed
        // $event->update(['status' => 'invitations_sent']);
        
        return response()->json([
            'message' => 'Invitations marked as complete for event',
            'event_id' => $id
        ]);
    }
}
