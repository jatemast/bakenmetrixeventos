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
        Schema::table('users', function (Blueprint $table) {
            // Add is_active field for account status management
            $table->boolean('is_active')->default(true)->after('persona_id');
        });

        // Note: role field already exists and can handle various role types
        // No need to change from VARCHAR to ENUM as Laravel uses string types
        // Valid roles: super_admin, campaign_manager, event_coordinator, viewer, user
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
