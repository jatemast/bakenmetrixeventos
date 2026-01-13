<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update bonus_points_history table to support groups and enhanced tracking
     */
    public function up(): void
    {
        Schema::table('bonus_points_history', function (Blueprint $table) {
            // Add group_id for group-level bonuses
            $table->foreignId('group_id')
                ->nullable()
                ->after('persona_id')
                ->constrained('groups')
                ->onDelete('cascade')
                ->comment('For group-level bonus points');
            
            // Add standardized points column (keeping points_awarded for compatibility)
            $table->integer('points')
                ->nullable()
                ->after('group_id')
                ->comment('Bonus points amount (standardized)');
            
            // Add reason field for categorization
            $table->string('reason', 100)
                ->nullable()
                ->after('points')
                ->comment('Reason code: event_attendance, leader_referral_bonus, group_bonus, etc.');
            
            // Add metadata JSON field
            $table->json('metadata')
                ->nullable()
                ->after('description')
                ->comment('Additional structured data about the bonus');
            
            // Add index for efficient querying
            $table->index('reason');
            $table->index(['persona_id', 'reason']);
            $table->index(['group_id', 'reason']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonus_points_history', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['group_id', 'points', 'reason', 'metadata']);
            $table->dropIndex(['reason']);
            $table->dropIndex(['persona_id', 'reason']);
            $table->dropIndex(['group_id', 'reason']);
        });
    }
};
