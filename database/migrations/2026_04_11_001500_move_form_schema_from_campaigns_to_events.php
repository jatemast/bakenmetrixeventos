<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate form_schema from campaigns to their events, then remove from campaigns.
     * 
     * Rationale: form_schema is event-specific (vaccination forms, health census, etc.)
     * and should not live at the campaign level.
     */
    public function up(): void
    {
        // Step 1: Copy any existing campaign form_schema to events that don't have their own
        $campaigns = DB::table('campaigns')
            ->whereNotNull('form_schema')
            ->get(['id', 'form_schema', 'success_message']);

        foreach ($campaigns as $campaign) {
            DB::table('events')
                ->where('campaign_id', $campaign->id)
                ->whereNull('form_schema')
                ->update([
                    'form_schema' => $campaign->form_schema,
                    'success_message' => $campaign->success_message,
                ]);
        }

        // Step 2: Remove form_schema and success_message columns from campaigns
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'form_schema')) {
                $table->dropColumn('form_schema');
            }
            if (Schema::hasColumn('campaigns', 'success_message')) {
                $table->dropColumn('success_message');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'form_schema')) {
                $table->json('form_schema')->nullable()->after('status');
            }
            if (!Schema::hasColumn('campaigns', 'success_message')) {
                $table->text('success_message')->nullable()->after('form_schema');
            }
        });
    }
};
