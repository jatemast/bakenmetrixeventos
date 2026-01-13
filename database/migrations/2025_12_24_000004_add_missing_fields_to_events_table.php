<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * CRITICAL: This migration adds essential fields for event scheduling and capacity management
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // CRITICAL: Add time field for event scheduling
            $table->time('time')->nullable()->after('date');
            
            // Add duration for event length tracking
            $table->decimal('duration_hours', 3, 1)->default(3.0)->after('time');
            
            // Add capacity management fields
            $table->integer('max_capacity')->default(1000)->after('duration_hours');
            
            // Add target universes for event segmentation
            // Example: ["U1", "U2"] or ["U3"] for leader-only events
            $table->json('target_universes')->nullable()->after('max_capacity');
            
            // Add status for event lifecycle management
            $table->enum('status', ['scheduled', 'active', 'completed', 'cancelled'])
                ->default('scheduled')->after('target_universes');
            
            // Add metrics fields for real-time dashboards
            $table->integer('registered_count')->default(0)->after('status');
            $table->integer('checked_in_count')->default(0)->after('registered_count');
            $table->integer('attended_count')->default(0)->after('checked_in_count');
            
            // Rename ai_agent_info_file to pdf_path for clarity
            // This will be handled separately to avoid data loss
            
            // Add workflow tracking flags
            $table->boolean('ai_knowledge_ready')->default(false)->after('attended_count');
            $table->boolean('invitations_sent')->default(false)->after('ai_knowledge_ready');
            $table->boolean('points_distributed')->default(false)->after('invitations_sent');
        });

        // Rename ai_agent_info_file to pdf_path if it exists
        if (Schema::hasColumn('events', 'ai_agent_info_file')) {
            Schema::table('events', function (Blueprint $table) {
                $table->renameColumn('ai_agent_info_file', 'pdf_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename pdf_path back to ai_agent_info_file
        if (Schema::hasColumn('events', 'pdf_path')) {
            Schema::table('events', function (Blueprint $table) {
                $table->renameColumn('pdf_path', 'ai_agent_info_file');
            });
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'time',
                'duration_hours',
                'max_capacity',
                'target_universes',
                'status',
                'registered_count',
                'checked_in_count',
                'attended_count',
                'ai_knowledge_ready',
                'invitations_sent',
                'points_distributed'
            ]);
        });
    }
};
