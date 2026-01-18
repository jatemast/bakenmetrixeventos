<?php

namespace App\Services;

use App\Models\QrCode;
use App\Models\Event;
use App\Models\Persona;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\EpsImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    /**
     * Generate all QR codes for an event
     * QR1: Registration
     * QR2: Entry
     * QR3: Exit
     * QR2-L: Leader guest entry (one per leader)
     * QR-MILITANT: Personalized militant QRs
     */
    public function generateEventQrCodes(Event $event)
    {
        $qrCodes = [];
        
        // QR1: Pre-event registration
        $qrCodes['QR1'] = $this->createQrCode($event, 'QR1');
        
        // QR2: Entry validation
        $qrCodes['QR2'] = $this->createQrCode($event, 'QR2');
        
        // QR3: Exit validation
        $qrCodes['QR3'] = $this->createQrCode($event, 'QR3');
        
        // QR2-L: Leader guest entry codes - generate for leaders with universe U3
        $leaders = Persona::where('is_leader', true)
            ->where('universe_type', 'U3')
            ->get();
        
        $qrCodes['QR2-L'] = [];
        
        foreach ($leaders as $leader) {
            $existing = QrCode::where('event_id', $event->id)
                ->where('leader_id', $leader->id)
                ->where('type', 'QR2-L')
                ->first();
            
            if ($existing) {
                $qrCodes['QR2-L'][$leader->id] = $existing;
            } else {
                $qrCodes['QR2-L'][$leader->id] = $this->createLeaderQrCode($event, $leader);
            }
        }
        
        \Log::info('Generated QR codes for event', [
            'event_id' => $event->id,
            'qr1' => $qrCodes['QR1']->code,
            'qr2' => $qrCodes['QR2']->code,
            'qr3' => $qrCodes['QR3']->code,
            'leader_qrs_count' => count($qrCodes['QR2-L']),
        ]);
        
        return $qrCodes;
    }

    /**
     * Create a QR code record
     */
    private function createQrCode(Event $event, string $type, ?Persona $persona = null, ?Persona $leader = null)
    {
        $code = $this->generateUniqueCode($event, $type, $persona);
        
        return QrCode::create([
            'campaign_id' => $event->campaign_id,
            'event_id' => $event->id,
            'type' => $type,
            'code' => $code,
            'persona_id' => $persona?->id,
            'leader_id' => $leader?->id,
            'is_active' => true,
        ]);
    }

    /**
     * Create leader-specific QR code for guest entry
     */
    private function createLeaderQrCode(Event $event, Persona $leader)
    {
        return $this->createQrCode($event, 'QR2-L', null, $leader);
    }
    
    /**
     * Generate leader-specific QR codes for an event
     */
    public function generateLeaderQrCodes(Event $event)
    {
        $leaders = Persona::where('is_leader', true)
            ->where('universe_type', 'U3')
            ->get();
        
        $qrCodes = [];
        
        foreach ($leaders as $leader) {
            // Check if leader QR already exists
            $existing = QrCode::where('event_id', $event->id)
                ->where('persona_id', $leader->id)
                ->where('type', 'QR2-L')
                ->first();

            if ($existing) {
                $qrCodes[$leader->id] = $existing;
                continue;
            }

            $code = sprintf('QR2L-E%d-P%d-%s', $event->id, $leader->id, strtoupper(Str::random(6)));
            
            $qrCodes[$leader->id] = QrCode::create([
                'campaign_id' => $event->campaign_id,
                'event_id' => $event->id,
                'type' => 'QR2-L',
                'code' => $code,
                'persona_id' => $leader->id,
                'is_active' => true,
            ]);
        }
        
        return $qrCodes;
    }

    /**
     * Generate unique QR code string
     */
    private function generateUniqueCode(Event $event, string $type, ?Persona $persona = null): string
    {
        $prefix = sprintf('E%d-C%d-%s', $event->id, $event->campaign_id, $type);
        
        if ($persona) {
            $prefix .= '-P' . $persona->id;
        }
        
        $uniqueHash = Str::random(12);
        
        return strtoupper($prefix . '-' . $uniqueHash);
    }

    /**
     * Validate a QR code
     */
    public function validateQrCode(string $code): array
    {
        $qr = QrCode::where('code', $code)->first();
        
        if (!$qr) {
            return [
                'valid' => false,
                'message' => 'QR code not found',
            ];
        }
        
        if (!$qr->isValid()) {
            return [
                'valid' => false,
                'message' => 'QR code is expired or inactive',
            ];
        }
        
        return [
            'valid' => true,
            'qr' => $qr,
            'event' => $qr->event,
            'type' => $qr->type,
            'persona' => $qr->persona,
            'leader' => $qr->leader,
        ];
    }

    /**
     * Generate QR code image (PNG/SVG)
     */
    public function generateQrImage(QrCode $qrCode, string $format = 'png'): string
    {
        $qrData = json_encode([
            'code' => $qrCode->code,
            'event_id' => $qrCode->event_id,
            'campaign_id' => $qrCode->campaign_id,
            'type' => $qrCode->type,
        ]);
        
        if ($format === 'svg') {
            return QrCodeGenerator::format('svg')->size(300)->generate($qrData);
        }
        
        return QrCodeGenerator::format('png')->size(300)->generate($qrData);
    }

    /**
     * Get all QR codes for an event
     */
    public function getEventQrCodes(Event $event)
    {
        return QrCode::where('event_id', $event->id)
            ->with(['persona', 'leader'])
            ->get();
    }

    /**
     * Deactivate all QR codes for an event
     */
    public function deactivateEventQrCodes(Event $event)
    {
        QrCode::where('event_id', $event->id)->update(['is_active' => false]);
    }

    /**
     * Generate and store QR code images when event is created
     * Returns paths to store in database
     */
    public function generateAndStoreQrImages(Event $event): array
    {
        $paths = [];
        
        // Ensure storage directory exists
        Storage::disk('public')->makeDirectory('qrcodes');
        
        // Create QR code writer with SVG backend (works without imagick or GD)
        $renderer = new ImageRenderer(
            new RendererStyle(500),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        
        // QR1: Invitation - Uses event ID
        $qr1Url = url("/invitation/event-{$event->id}");
        $qr1Image = $writer->writeString($qr1Url);
        $qr1Path = "qrcodes/event-{$event->id}-qr1-invitation.svg";
        Storage::disk('public')->put($qr1Path, $qr1Image);
        $paths['qr1_image_path'] = $qr1Path;
        
        // QR2: Check-in - Uses checkin_code
        $qr2Url = url("/events/public/{$event->checkin_code}");
        $qr2Image = $writer->writeString($qr2Url);
        $qr2Path = "qrcodes/event-{$event->id}-qr2-checkin.svg";
        Storage::disk('public')->put($qr2Path, $qr2Image);
        $paths['qr2_image_path'] = $qr2Path;
        
        // QR3: Check-out - Uses checkout_code
        $qr3Url = url("/events/checkout/{$event->checkout_code}");
        $qr3Image = $writer->writeString($qr3Url);
        $qr3Path = "qrcodes/event-{$event->id}-qr3-checkout.svg";
        Storage::disk('public')->put($qr3Path, $qr3Image);
        $paths['qr3_image_path'] = $qr3Path;
        
        \Log::info('Generated and stored QR images for event', [
            'event_id' => $event->id,
            'qr1_path' => $qr1Path,
            'qr2_path' => $qr2Path,
            'qr3_path' => $qr3Path,
        ]);
        
        return $paths;
    }
}
