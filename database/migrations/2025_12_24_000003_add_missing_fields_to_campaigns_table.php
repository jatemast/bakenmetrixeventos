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
        Schema::table('campaigns', function (Blueprint $table) {
            // Add objective field for campaign goals
            $table->text('objective')->nullable()->after('theme');
            
            // Add target_universes JSON field for universe segmentation
            // Example: ["U1", "U2", "U3", "U4"] or ["U3", "U4"] for leaders only
            $table->json('target_universes')->nullable()->after('target_citizen');
            
            // Add status field for campaign lifecycle management
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])
                ->default('active')->after('end_date');
            
            // Add created_by to track who created the campaign
            $table->foreignId('created_by')->nullable()->after('status')
                ->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['created_by']);
            
            // Drop columns
            $table->dropColumn([
                'objective',
                'target_universes',
                'status',
                'created_by'
            ]);
        });
    }
};
