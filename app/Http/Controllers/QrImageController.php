<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class QrImageController extends Controller
{
    /**
     * Get QR data for an event - returns stored QR image URLs
     * Simple endpoint that just fetches from storage
     */
    public function getEventQrData(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);
        
        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        $baseUrl = url('storage');
        
        return response()->json([
            'success' => true,
            'event_id' => $event->id,
            'qr_codes' => [
                'qr1_invitation' => [
                    'type' => 'QR1',
                    'purpose' => 'Pre-event registration',
                    'url' => url("/invitation/event-{$event->id}"),
                    'image_url' => $event->qr1_image_path ? "{$baseUrl}/{$event->qr1_image_path}" : null,
                ],
                'qr2_checkin' => [
                    'type' => 'QR2',
                    'purpose' => 'Event check-in',
                    'url' => url("/events/public/{$event->checkin_code}"),
                    'image_url' => $event->qr2_image_path ? "{$baseUrl}/{$event->qr2_image_path}" : null,
                ],
                'qr3_checkout' => [
                    'type' => 'QR3',
                    'purpose' => 'Event check-out',
                    'url' => url("/events/checkout/{$event->checkout_code}"),
                    'image_url' => $event->qr3_image_path ? "{$baseUrl}/{$event->qr3_image_path}" : null,
                ],
            ]
        ]);
    }

    /**
     * Get the general CRM registration QR (to attract NEW users)
     */
    public function getCrmRegistrationQr(): JsonResponse
    {
        $baseUrl = config('app.frontend_url', 'http://localhost:5173');
        $url = "{$baseUrl}/registro/ciudadano";
        
        return response()->json([
            'success' => true,
            'qr_purpose' => 'CRM Public Registration',
            'registration_url' => $url,
            'whatsapp_url' => $url, // Keep for legacy FE compatibility
            'qr_image_url' => "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($url),
            'note' => 'Escanea para registrarte directamente en nuestro censo digital.'
        ]);
    }

    /**
     * Serve a QR code as an image directly (PNG)
     */
    public function serveQrImage(string $code): \Illuminate\Http\Response
    {
        $qr = \App\Models\QrCode::where('code', $code)->firstOrFail();
        
        $image = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(500)
            ->errorCorrection('H')
            ->generate($code);
            
        return response($image)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
