<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Services\MilitantQrService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * QrScanController
 * 
 * Handles QR code scanning with universe-specific logic:
 * - General QR codes (QR1, QR2, QR3): Standard flow
 * - Leader QR codes (QR2-L): Guest attribution
 * - Militant QR codes (QR-MILITANT): Fast-track entry/exit
 */
class QrScanController extends Controller
{
    protected MilitantQrService $militantQrService;

    public function __construct(MilitantQrService $militantQrService)
    {
        $this->militantQrService = $militantQrService;
    }

    /**
     * Universal QR scan endpoint
     * Automatically detects QR type and routes to appropriate handler
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
            'event_id' => 'required|exists:events,id',
            'scan_type' => 'required|in:entry,exit,registration',
        ]);

        $qrCodeString = $request->input('qr_code');
        $eventId = $request->input('event_id');
        $scanType = $request->input('scan_type');

        // Find QR code
        $qrCode = QrCode::where('code', $qrCodeString)
            ->where('is_active', true)
            ->first();

        if (!$qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive QR code',
            ], 404);
        }

        $event = Event::findOrFail($eventId);

        // Route to appropriate handler based on QR type
        return match($qrCode->type) {
            'QR-MILITANT' => $this->handleMilitantScan($qrCode, $event, $scanType),
            'QR2-L' => $this->handleLeaderGuestScan($qrCode, $event),
            'QR1' => $this->handleRegistrationScan($qrCode, $event),
            'QR2' => $this->handleEntryScan($qrCode, $event),
            'QR3' => $this->handleExitScan($qrCode, $event),
            default => response()->json([
                'success' => false,
                'message' => 'Unknown QR code type',
            ], 400),
        };
    }

    /**
     * Handle militant QR scan (fast-track)
     */
    private function handleMilitantScan(QrCode $qrCode, Event $event, string $scanType): JsonResponse
    {
        $result = $this->militantQrService->processMilitantScan(
            $qrCode->code,
            $event,
            $scanType
        );

        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    /**
     * Handle leader guest entry (QR2-L)
     */
    private function handleLeaderGuestScan(QrCode $qrCode, Event $event): JsonResponse
    {
        if (!$qrCode->persona_id) {
            return response()->json([
                'success' => false,
                'message' => 'Leader QR code not linked to a persona',
            ], 400);
        }

        $leader = $qrCode->persona;

        // This QR is for checking in guests invited by this leader
        // The actual guest needs to register first, then this QR attributes them to the leader
        
        return response()->json([
            'success' => true,
            'scan_type' => 'leader_guest_entry',
            'message' => 'Leader QR scanned - ready to check in guest',
            'leader' => [
                'id' => $leader->id,
                'nombre' => $leader->nombre,
                'referral_code' => $leader->referral_code,
            ],
            'event' => [
                'id' => $event->id,
                'detail' => $event->detail,
            ],
            'instructions' => 'Guest should now scan their own QR or register on-site',
        ]);
    }

    /**
     * Handle general registration QR (QR1)
     */
    private function handleRegistrationScan(QrCode $qrCode, Event $event): JsonResponse
    {
        // QR1 is for pre-event registration
        // This would typically show event details and allow persona to register interest
        
        $qrCode->increment('scan_count');

        return response()->json([
            'success' => true,
            'scan_type' => 'registration',
            'message' => 'Registration QR scanned',
            'event' => [
                'id' => $event->id,
                'detail' => $event->detail,
                'date' => $event->date,
                'time' => $event->time,
                'location' => $event->street . ', ' . $event->municipality,
                'capacity' => $event->max_capacity,
                'registered_count' => $event->registered_count,
            ],
            'action_required' => 'User should complete registration form',
        ]);
    }

    /**
     * Handle general entry QR (QR2)
     */
    private function handleEntryScan(QrCode $qrCode, Event $event): JsonResponse
    {
        $qrCode->increment('scan_count');

        return response()->json([
            'success' => true,
            'scan_type' => 'entry',
            'message' => 'Entry QR scanned - attendee should provide identification',
            'event' => [
                'id' => $event->id,
                'detail' => $event->detail,
            ],
            'action_required' => 'Collect cedula or phone number to complete check-in',
        ]);
    }

    /**
     * Handle general exit QR (QR3)
     */
    private function handleExitScan(QrCode $qrCode, Event $event): JsonResponse
    {
        $qrCode->increment('scan_count');

        return response()->json([
            'success' => true,
            'scan_type' => 'exit',
            'message' => 'Exit QR scanned - attendee should provide identification',
            'event' => [
                'id' => $event->id,
                'detail' => $event->detail,
            ],
            'action_required' => 'Collect cedula or phone number to complete check-out',
        ]);
    }

    /**
     * Manual check-in with persona details (for non-militant universes)
     */
    public function manualCheckIn(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'cedula' => 'nullable|string',
            'phone' => 'nullable|string',
            'qr_code' => 'nullable|string',
            'leader_id' => 'nullable|exists:personas,id',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        // Find persona by cedula or phone
        $persona = \App\Models\Persona::query()
            ->when($request->cedula, fn($q) => $q->where('cedula', $request->cedula))
            ->when($request->phone && !$request->cedula, fn($q) => $q->where('numero_celular', $request->phone))
            ->first();

        if (!$persona) {
            return response()->json([
                'success' => false,
                'message' => 'Persona not found. Please register first.',
            ], 404);
        }

        $event = Event::findOrFail($request->event_id);

        // Check if already checked in
        $attendee = EventAttendee::where('event_id', $event->id)
            ->where('persona_id', $persona->id)
            ->first();

        if ($attendee && $attendee->checkin_at) {
            return response()->json([
                'success' => false,
                'message' => 'Already checked in',
                'persona' => $persona->nombre,
                'checked_in_at' => $attendee->checkin_at->format('Y-m-d H:i:s'),
            ], 400);
        }

        // Create or update attendee record
        $attendee = EventAttendee::updateOrCreate(
            [
                'event_id' => $event->id,
                'persona_id' => $persona->id,
            ],
            [
                'checkin_at' => now(),
                'checkin_qr_code' => $request->input('qr_code'),
                'leader_id' => $request->input('leader_id'),
                'group_id' => $request->input('group_id'),
                'entry_timestamp' => now(),
                'attendance_status' => 'present',
                'last_qr_scan_type' => 'entry',
                'last_qr_scan_at' => now(),
            ]
        );

        $event->increment('checked_in_count');

        Log::info("Manual check-in completed", [
            'persona_id' => $persona->id,
            'event_id' => $event->id,
            'universe_type' => $persona->universe_type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Check-in successful',
            'persona' => [
                'id' => $persona->id,
                'nombre' => $persona->nombre,
                'cedula' => $persona->cedula,
                'universe_type' => $persona->universe_type,
            ],
            'checked_in_at' => $attendee->checkin_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Manual check-out
     */
    public function manualCheckOut(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'cedula' => 'nullable|string',
            'phone' => 'nullable|string',
            'qr_code' => 'nullable|string',
        ]);

        // Find persona
        $persona = \App\Models\Persona::query()
            ->when($request->cedula, fn($q) => $q->where('cedula', $request->cedula))
            ->when($request->phone && !$request->cedula, fn($q) => $q->where('numero_celular', $request->phone))
            ->first();

        if (!$persona) {
            return response()->json([
                'success' => false,
                'message' => 'Persona not found',
            ], 404);
        }

        $event = Event::findOrFail($request->event_id);

        // Find attendee record
        $attendee = EventAttendee::where('event_id', $event->id)
            ->where('persona_id', $persona->id)
            ->first();

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'No check-in record found',
            ], 404);
        }

        if (!$attendee->checkin_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot check out without checking in first',
            ], 400);
        }

        if ($attendee->checkout_at) {
            return response()->json([
                'success' => false,
                'message' => 'Already checked out',
                'persona' => $persona->nombre,
                'checked_out_at' => $attendee->checkout_at->format('Y-m-d H:i:s'),
            ], 400);
        }

        // Calculate duration
        $durationMinutes = $attendee->checkin_at->diffInMinutes(now());

        // Update attendee
        $attendee->update([
            'checkout_at' => now(),
            'checkout_qr_code' => $request->input('qr_code'),
            'exit_timestamp' => now(),
            'attendance_status' => 'completed',
            'attendance_duration_minutes' => $durationMinutes,
            'last_qr_scan_type' => 'exit',
            'last_qr_scan_at' => now(),
        ]);

        $event->increment('attended_count');

        Log::info("Manual check-out completed", [
            'persona_id' => $persona->id,
            'event_id' => $event->id,
            'duration_minutes' => $durationMinutes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Check-out successful',
            'persona' => [
                'id' => $persona->id,
                'nombre' => $persona->nombre,
                'cedula' => $persona->cedula,
                'universe_type' => $persona->universe_type,
            ],
            'checked_out_at' => $attendee->checkout_at->format('Y-m-d H:i:s'),
            'duration_minutes' => $durationMinutes,
        ]);
    }

    /**
     * Get QR code details
     */
    public function qrDetails(string $code): JsonResponse
    {
        $qrCode = QrCode::where('code', $code)
            ->with(['event', 'campaign', 'persona'])
            ->first();

        if (!$qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'qr_code' => $qrCode,
        ]);
    }
}
