<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\MilitantQrService;
use Illuminate\Console\Command;

class GenerateCampaignMilitantQrs extends Command
{
    protected $signature = 'campaign:generate-militant-qr {campaign_id}';
    protected $description = 'Generate campaign-level militant QR codes for all U4 personas';

    protected MilitantQrService $militantQrService;

    public function __construct(MilitantQrService $militantQrService)
    {
        parent::__construct();
        $this->militantQrService = $militantQrService;
    }

    public function handle()
    {
        $campaignId = $this->argument('campaign_id');
        $campaign = Campaign::find($campaignId);

        if (!$campaign) {
            $this->error("Campaign {$campaignId} not found");
            return 1;
        }

        $this->info("Generating militant QR codes for Campaign #{$campaign->id}: {$campaign->name}");

        try {
            $stats = $this->militantQrService->generateCampaignMilitantQrs($campaign);

            $this->newLine();
            $this->info("📊 Statistics:");
            $this->line("  Total militants (U4): {$stats['total_militants']}");
            $this->line("  QR codes created: {$stats['qrs_created']}");
            $this->line("  QR codes existing: {$stats['qrs_existing']}");

            if ($stats['qrs_created'] > 0 || $stats['qrs_existing'] > 0) {
                $this->newLine();
                $this->info("✅ Generated QR codes:");
                foreach ($stats['qr_codes'] as $personaId => $qr) {
                    $persona = $qr->persona;
                    $status = $qr->wasRecentlyCreated ? 'NEW' : 'EXISTING';
                    $this->line("  [{$status}] Persona #{$personaId} ({$persona->nombre}): {$qr->code}");
                }
            }

            $this->newLine();
            $this->info("✅ Successfully generated campaign-level militant QR codes");
            $this->line("These QR codes work for ALL events in this campaign");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to generate militant QR codes: {$e->getMessage()}");
            return 1;
        }
    }
}
