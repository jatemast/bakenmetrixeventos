<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add indexes for improved query performance
     */
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            // Index for universe filtering
            if (!$this->indexExists('personas', 'personas_universe_type_index')) {
                $table->index('universe_type', 'personas_universe_type_index');
            }
            
            // Index for loyalty balance sorting (leaderboards)
            if (!$this->indexExists('personas', 'personas_loyalty_balance_index')) {
                $table->index('loyalty_balance', 'personas_loyalty_balance_index');
            }
            
            // Index for leader lookups
            if (!$this->indexExists('personas', 'personas_is_leader_index')) {
                $table->index('is_leader', 'personas_is_leader_index');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            // Index for status filtering
            if (!$this->indexExists('events', 'events_status_index')) {
                $table->index('status', 'events_status_index');
            }
            
            // Composite index for date and time queries
            if (!$this->indexExists('events', 'events_date_time_index')) {
                $table->index(['date', 'time'], 'events_date_time_index');
            }
            
            // Index for campaign lookups (might already exist as foreign key)
            if (!$this->indexExists('events', 'events_campaign_id_index') && 
                !$this->indexExists('events', 'events_campaign_id_foreign')) {
                $table->index('campaign_id', 'events_campaign_id_index');
            }
        });

        Schema::table('event_attendees', function (Blueprint $table) {
            // Index for referred_by lookups
            if (!$this->indexExists('event_attendees', 'event_attendees_referred_by_index')) {
                $table->index('referred_by', 'event_attendees_referred_by_index');
            }
            
            // Index for attendance status filtering
            if (!$this->indexExists('event_attendees', 'event_attendees_attendance_status_index')) {
                $table->index('attendance_status', 'event_attendees_attendance_status_index');
            }
            
            // Index for points distribution tracking
            if (!$this->indexExists('event_attendees', 'event_attendees_points_distributed_index')) {
                $table->index('points_distributed', 'event_attendees_points_distributed_index');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            // Index for status filtering
            if (!$this->indexExists('campaigns', 'campaigns_status_index')) {
                $table->index('status', 'campaigns_status_index');
            }
            
            // Composite index for date range queries
            if (!$this->indexExists('campaigns', 'campaigns_start_end_date_index')) {
                $table->index(['start_date', 'end_date'], 'campaigns_start_end_date_index');
            }
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            // Index for QR code lookups
            if (!$this->indexExists('qr_codes', 'qr_codes_code_index')) {
                $table->index('code', 'qr_codes_code_index');
            }
            
            // Index for active QR codes
            if (!$this->indexExists('qr_codes', 'qr_codes_is_active_index')) {
                $table->index('is_active', 'qr_codes_is_active_index');
            }
            
            // Index for type filtering
            if (!$this->indexExists('qr_codes', 'qr_codes_type_index')) {
                $table->index('type', 'qr_codes_type_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropIndex('personas_universe_type_index');
            $table->dropIndex('personas_loyalty_balance_index');
            $table->dropIndex('personas_is_leader_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_status_index');
            $table->dropIndex('events_date_time_index');
        });

        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropIndex('event_attendees_referred_by_index');
            $table->dropIndex('event_attendees_attendance_status_index');
            $table->dropIndex('event_attendees_points_distributed_index');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('campaigns_status_index');
            $table->dropIndex('campaigns_start_end_date_index');
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropIndex('qr_codes_code_index');
            $table->dropIndex('qr_codes_is_active_index');
            $table->dropIndex('qr_codes_type_index');
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $indexInfo) {
            if ($indexInfo['name'] === $index) {
                return true;
            }
        }
        return false;
    }
};
