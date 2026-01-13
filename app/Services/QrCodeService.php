<?php

namespace App\Services;

use App\Models\QrCode;
use App\Models\Event;
use App\Models\Persona;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use Illuminate\Support\Str;

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
        
        // QR2-L: Leader guest entry codes (generate for all leaders in event)
        $leaders = Persona::where('is_leader', true)->get();
        $qrCodes['QR2-L'] = [];
        
        foreach ($leaders as $leader) {
            $qrCodes['QR2-L'][$leader->id] = $this->createLeaderQrCode($event, $leader);
        }
        
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
}
