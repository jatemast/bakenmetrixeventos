<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            // Rename bonus_points to loyalty_balance for consistency
            $table->renameColumn('bonus_points', 'loyalty_balance');
            
            // Add region field for geographic filtering
            $table->string('region', 100)->nullable()->after('estado');
            
            // Add leader_id for tracking leader hierarchy
            if (!Schema::hasColumn('personas', 'leader_id')) {
                $table->foreignId('leader_id')->nullable()->after('referral_code')
                    ->constrained('personas')->onDelete('set null');
            }
            
            // Add last interaction tracking fields
            if (!Schema::hasColumn('personas', 'last_interacted_event_id')) {
                $table->foreignId('last_interacted_event_id')->nullable()->after('leader_id')
                    ->constrained('events')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('personas', 'last_interaction_at')) {
                $table->timestamp('last_interaction_at')->nullable()->after('last_interacted_event_id');
            }
            
            if (!Schema::hasColumn('personas', 'last_invited_event_id')) {
                $table->foreignId('last_invited_event_id')->nullable()->after('last_interaction_at')
                    ->constrained('events')->onDelete('set null');
            }
        });

        // Note: universe_type already exists from migration 2025_12_18_034330_add_universe_type_to_personas
        // We keep it as universe_type (VARCHAR) instead of renaming to avoid breaking existing code
        // Valid values: U1, U2, U3, U4
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            // Drop foreign keys first
            if (Schema::hasColumn('personas', 'last_invited_event_id')) {
                $table->dropForeign(['last_invited_event_id']);
                $table->dropColumn('last_invited_event_id');
            }
            
            if (Schema::hasColumn('personas', 'last_interaction_at')) {
                $table->dropColumn('last_interaction_at');
            }
            
            if (Schema::hasColumn('personas', 'last_interacted_event_id')) {
                $table->dropForeign(['last_interacted_event_id']);
                $table->dropColumn('last_interacted_event_id');
            }
            
            if (Schema::hasColumn('personas', 'leader_id')) {
                $table->dropForeign(['leader_id']);
                $table->dropColumn('leader_id');
            }
            
            if (Schema::hasColumn('personas', 'region')) {
                $table->dropColumn('region');
            }
            
            // Rename back loyalty_balance to bonus_points
            $table->renameColumn('loyalty_balance', 'bonus_points');
        });
    }
};
