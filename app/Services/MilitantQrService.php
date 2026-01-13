<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Event;
use App\Models\Persona;
use App\Models\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for managing militant (U4) personalized QR codes
 * 
 * Militants get:
 * - One unique QR per campaign (reusable across all events)
 * - Pre-linked to their CRM identity
 * - Fast-track entry/exit (no on-site registration)
 * - Automatic check-in when scanned
 */
class MilitantQrService
{
    /**
     * Generate campaign-level personalized QR codes for all militants
     * 
     * @param Campaign $campaign
     * @return array Statistics and QR code data
     */
    public function generateCampaignMilitantQrs(Campaign $campaign): array
    {
        // Get all militants (U4) in the system
        $militants = Persona::where('universe_type', 'U4')->get();

        $stats = [
            'total_militants' => $militants->count(),
            'qrs_created' => 0,
            'qrs_existing' => 0,
            'qr_codes' => [],
        ];

        foreach ($militants as $militant) {
            // Check if QR already exists for this militant + campaign
            $existingQr = QrCode::where('campaign_id', $campaign->id)
                ->where('persona_id', $militant->id)
                ->where('type', 'QR-MILITANT')
                ->whereNull('event_id') // Campaign-level QR (not event-specific)
                ->first();

            if ($existingQr) {
                $stats['qrs_existing']++;
                $stats['qr_codes'][$militant->id] = $existingQr;
                continue;
            }

            // Generate new campaign-level QR
            $code = $this->generateMilitantCode($campaign, $militant);
            
            $qrCode = QrCode::create([
                'campaign_id' => $campaign->id,
                'event_id' => null, // Campaign-level, works for all events
                'type' => 'QR-MILITANT',
                'code' => $code,
                'persona_id' => $militant->id,
                'is_active' => true,
            ]);

            $stats['qrs_created']++;
            $stats['qr_codes'][$militant->id] = $qrCode;

            Log::info("Created campaign-level militant QR for persona {$militant->id} in campaign {$campaign->id}");
        }

        return $stats;
    }

    /**
     * Generate event-specific militant QRs (if needed for specific events)
     * 
     * @param Event $event
     * @return array
     */
    public function generateEventMilitantQrs(Event $event): array
    {
        // First, check if campaign-level QRs exist (they should be used instead)
        $campaignQrs = QrCode::where('campaign_id', $event->campaign_id)
            ->where('type', 'QR-MILITANT')
            ->whereNull('event_id')
            ->count();

        if ($campaignQrs > 0) {
            return [
                'success' => true,
                'message' => 'Using campaign-level militant QRs (reusable)',
                'qrs_count' => $campaignQrs,
            ];
        }

        // If no campaign QRs exist, generate event-specific ones
        $militants = Persona::where('universe_type', 'U4')->get();

        $stats = [
            'total_militants' => $militants->count(),
            'qrs_created' => 0,
            'qr_codes' => [],
        ];

        foreach ($militants as $militant) {
            $existingQr = QrCode::where('event_id', $event->id)
                ->where('persona_id', $militant->id)
                ->where('type', 'QR-MILITANT')
                ->first();

            if ($existingQr) {
                continue;
            }

            $code = $this->generateMilitantCode($event->campaign, $militant, $event);
            
            $qrCode = QrCode::create([
                'campaign_id' => $event->campaign_id,
                'event_id' => $event->id,
                'type' => 'QR-MILITANT',
                'code' => $code,
                'persona_id' => $militant->id,
                'is_active' => true,
            ]);

            $stats['qrs_created']++;
            $stats['qr_codes'][$militant->id] = $qrCode;
        }

        return $stats;
    }

    /**
     * Generate unique QR code for militant
     * 
     * Format: QRM-C{campaignId}-P{personaId}-{hash}
     * or: QRM-C{campaignId}-E{eventId}-P{personaId}-{hash}
     * 
     * @param Campaign $campaign
     * @param Persona $militant
     * @param Event|null $event
     * @return string
     */
    private function generateMilitantCode(Campaign $campaign, Persona $militant, ?Event $event = null): string
    {
        $prefix = sprintf('QRM-C%d', $campaign->id);
        
        if ($event) {
            $prefix .= sprintf('-E%d', $event->id);
        }
        
        $prefix .= sprintf('-P%d', $militant->id);
        
        $hash = strtoupper(Str::random(8));
        
        return $prefix . '-' . $hash;
    }

    /**
     * Distribute militant QR codes via n8n workflow (WhatsApp integration)
     * 
     * This triggers the n8n workflow which will:
     * 1. Generate QR code images
     * 2. Send personalized WhatsApp messages with QR codes
     * 3. Track delivery status
     * 
     * @param Campaign $campaign
     * @return array
     */
    public function distributeMilitantQrs(Campaign $campaign): array
    {
        $qrCodes = QrCode::where('campaign_id', $campaign->id)
            ->where('type', 'QR-MILITANT')
            ->whereNull('event_id') // Campaign-level QRs only
            ->with('persona')
            ->get();

        $stats = [
            'total_qrs' => $qrCodes->count(),
            'prepared_for_distribution' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $qrDataForN8n = [];

        foreach ($qrCodes as $qrCode) {
            if (!$qrCode->persona) {
                $stats['skipped']++;
                $stats['errors'][] = "QR {$qrCode->id} has no associated persona";
                continue;
            }

            if (!$qrCode->persona->numero_celular) {
                $stats['skipped']++;
                $stats['errors'][] = "Persona {$qrCode->persona_id} has no phone number";
                continue;
            }

            try {
                // Generate QR code image
                $qrImagePath = $this->generateQrCodeImage($qrCode);
                
                // Prepare data for n8n workflow
                $qrDataForN8n[] = [
                    'qr_code_id' => $qrCode->id,
                    'qr_code' => $qrCode->code,
                    'persona_id' => $qrCode->persona_id,
                    'persona_name' => $qrCode->persona->nombre,
                    'phone_number' => $qrCode->persona->numero_celular,
                    'qr_image_path' => $qrImagePath,
                    'qr_image_url' => asset('storage/' . $qrImagePath),
                ];
                
                $stats['prepared_for_distribution']++;
                
            } catch (\Exception $e) {
                $stats['skipped']++;
                $stats['errors'][] = "Failed to prepare QR for persona {$qrCode->persona_id}: {$e->getMessage()}";
                Log::error("Failed to prepare militant QR", [
                    'qr_id' => $qrCode->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Trigger n8n workflow for WhatsApp distribution
        if (!empty($qrDataForN8n)) {
            try {
                $this->triggerN8nMilitantQrWorkflow($campaign, $qrDataForN8n);
                Log::info("Triggered n8n workflow for {$stats['prepared_for_distribution']} militant QRs", [
                    'campaign_id' => $campaign->id,
                    'total_qrs' => $stats['total_qrs'],
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to trigger n8n workflow for militant QRs", [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
                $stats['errors'][] = "Failed to trigger n8n workflow: {$e->getMessage()}";
            }
        }

        return $stats;
    }

    /**
     * Trigger n8n notification workflow for militant QR distribution
     * 
     * Uses the same notification workflow as event invitations,
     * with different message_type to distinguish QR distribution
     * 
     * @param Campaign $campaign
     * @param array $qrData
     * @return void
     */
    protected function triggerN8nMilitantQrWorkflow(Campaign $campaign, array $qrData): void
    {
        // Use the same webhook as event invitations (unified notification workflow)
        $webhookUrl = config('services.n8n.notification_webhook_url');

        if (!$webhookUrl) {
            Log::warning('n8n notification webhook URL not configured');
            return;
        }

        \Illuminate\Support\Facades\Http::post($webhookUrl, [
            'message_type' => 'militant_qr_distribution',
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'notifications' => $qrData,
            'api_base_url' => config('app.url') . '/api',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Generate QR code image and save to storage
     * 
     * @param QrCode $qrCode
     * @return string Path to generated image
     */
    private function generateQrCodeImage(QrCode $qrCode): string
    {
        // Generate QR code image using SimpleSoftwareIO/simple-qrcode
        $qrImage = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(400)
            ->errorCorrection('H')
            ->generate($qrCode->code);

        // Save to storage
        $filename = "militant_qrs/campaign_{$qrCode->campaign_id}/qr_{$qrCode->persona_id}.png";
        Storage::disk('public')->put($filename, $qrImage);

        return $filename;
    }

    /**
     * Build WhatsApp message template for militant QR distribution
     * 
     * NOTE: This message template is used by n8n workflow.
     * The actual message sending is handled by n8n, not Laravel.
     * 
     * @param Campaign $campaign
     * @param QrCode $qrCode
     * @return string
     */
    public function buildMilitantQrMessage(Campaign $campaign, QrCode $qrCode): string
    {
        $persona = $qrCode->persona;
        
        return sprintf(
            "¡Hola %s! 🎟️\n\n" .
            "Tu código QR personalizado para la campaña *%s* está listo.\n\n" .
            "✅ *Acceso rápido (Fast-Track)*\n" .
            "✅ *Sin registro en sitio*\n" .
            "✅ *Válido para todos los eventos*\n\n" .
            "Código: `%s`\n\n" .
            "Descarga tu QR adjunto y preséntalo en la entrada de cualquier evento de esta campaña.\n\n" .
            "¡Te esperamos! 🚀",
            $persona->nombre,
            $campaign->name,
            $qrCode->code
        );
    }

    /**
     * Process militant QR scan (fast-track entry)
     * 
     * @param string $qrCode
     * @param Event $event
     * @param string $scanType 'entry' or 'exit'
     * @return array
     */
    public function processMilitantScan(string $qrCode, Event $event, string $scanType = 'entry'): array
    {
        // Find the QR code
        $qr = QrCode::where('code', $qrCode)
            ->where('type', 'QR-MILITANT')
            ->where('is_active', true)
            ->with('persona')
            ->first();

        if (!$qr) {
            return [
                'success' => false,
                'message' => 'Invalid or inactive militant QR code',
            ];
        }

        // Validate QR belongs to this campaign
        if ($qr->campaign_id !== $event->campaign_id) {
            return [
                'success' => false,
                'message' => 'QR code not valid for this campaign',
            ];
        }

        $persona = $qr->persona;

        if (!$persona) {
            return [
                'success' => false,
                'message' => 'Persona not found for this QR code',
            ];
        }

        // Check if attendee record exists
        $attendee = \App\Models\EventAttendee::firstOrCreate(
            [
                'event_id' => $event->id,
                'persona_id' => $persona->id,
            ],
            [
                'registered_at' => now(),
                'registration_qr_code' => $qrCode,
                'attendance_status' => 'registered',
            ]
        );

        // Process scan based on type
        if ($scanType === 'entry') {
            if ($attendee->checkin_at) {
                return [
                    'success' => false,
                    'message' => 'Militant already checked in',
                    'persona' => $persona->nombre,
                    'checked_in_at' => $attendee->checkin_at->format('Y-m-d H:i:s'),
                ];
            }

            // Fast-track check-in
            $attendee->update([
                'checkin_at' => now(),
                'checkin_qr_code' => $qrCode,
                'entry_timestamp' => now(),
                'entry_qr_id' => $qr->id,
                'attendance_status' => 'present',
                'last_qr_scan_type' => 'entry',
                'last_qr_scan_at' => now(),
            ]);

            $qr->increment('scan_count');

            Log::info("Militant fast-track entry", [
                'persona_id' => $persona->id,
                'event_id' => $event->id,
                'qr_code' => $qrCode,
            ]);

            return [
                'success' => true,
                'message' => '✅ Entrada registrada (Fast-Track)',
                'scan_type' => 'entry',
                'persona' => [
                    'id' => $persona->id,
                    'nombre' => $persona->nombre,
                    'cedula' => $persona->cedula,
                    'universe_type' => $persona->universe_type,
                ],
                'checked_in_at' => $attendee->checkin_at->format('Y-m-d H:i:s'),
            ];
            
        } else if ($scanType === 'exit') {
            if (!$attendee->checkin_at) {
                return [
                    'success' => false,
                    'message' => 'Cannot check out without checking in first',
                ];
            }

            if ($attendee->checkout_at) {
                return [
                    'success' => false,
                    'message' => 'Militant already checked out',
                    'persona' => $persona->nombre,
                    'checked_out_at' => $attendee->checkout_at->format('Y-m-d H:i:s'),
                ];
            }

            // Calculate attendance duration
            $durationMinutes = $attendee->checkin_at->diffInMinutes(now());

            // Fast-track check-out
            $attendee->update([
                'checkout_at' => now(),
                'checkout_qr_code' => $qrCode,
                'exit_timestamp' => now(),
                'exit_qr_id' => $qr->id,
                'attendance_status' => 'completed',
                'attendance_duration_minutes' => $durationMinutes,
                'last_qr_scan_type' => 'exit',
                'last_qr_scan_at' => now(),
            ]);

            $qr->increment('scan_count');

            Log::info("Militant fast-track exit", [
                'persona_id' => $persona->id,
                'event_id' => $event->id,
                'qr_code' => $qrCode,
                'duration_minutes' => $durationMinutes,
            ]);

            return [
                'success' => true,
                'message' => '✅ Salida registrada (Fast-Track)',
                'scan_type' => 'exit',
                'persona' => [
                    'id' => $persona->id,
                    'nombre' => $persona->nombre,
                    'cedula' => $persona->cedula,
                    'universe_type' => $persona->universe_type,
                ],
                'checked_out_at' => $attendee->checkout_at->format('Y-m-d H:i:s'),
                'duration_minutes' => $durationMinutes,
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid scan type',
        ];
    }

    /**
     * Get all militant QRs for a campaign
     * 
     * @param Campaign $campaign
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCampaignMilitantQrs(Campaign $campaign)
    {
        return QrCode::where('campaign_id', $campaign->id)
            ->where('type', 'QR-MILITANT')
            ->whereNull('event_id')
            ->with('persona')
            ->orderBy('persona_id')
            ->get();
    }

    /**
     * Deactivate all militant QRs for a campaign
     * 
     * @param Campaign $campaign
     * @return int Number of QRs deactivated
     */
    public function deactivateCampaignMilitantQrs(Campaign $campaign): int
    {
        return QrCode::where('campaign_id', $campaign->id)
            ->where('type', 'QR-MILITANT')
            ->update(['is_active' => false]);
    }

    /**
     * Reactivate all militant QRs for a campaign
     * 
     * @param Campaign $campaign
     * @return int Number of QRs reactivated
     */
    public function reactivateCampaignMilitantQrs(Campaign $campaign): int
    {
        return QrCode::where('campaign_id', $campaign->id)
            ->where('type', 'QR-MILITANT')
            ->update(['is_active' => true]);
    }
}
