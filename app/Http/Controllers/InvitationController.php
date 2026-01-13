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
            'persona_id' => 'required|exists:personas,id',
            'event_id' => 'required|exists:events,id',
            'status' => 'required|string',
        ]);

        $invitation = Invitation::create($validated);

        // Update persona's last_invited_event_id
        $persona = Persona::find($validated['persona_id']);
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
