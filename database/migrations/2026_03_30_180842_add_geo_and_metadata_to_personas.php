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
            // PostGIS Location (Point)
            if (!Schema::hasColumn('personas', 'location')) {
                $table->geography('location', 'point', 4326)->nullable();
            }

            // JSONB for Modular CRM (Universes: Pets, Sports, Health, etc.)
            if (!Schema::hasColumn('personas', 'universes')) {
                $table->jsonb('universes')->nullable()->comment('Modular segments data');
            }

            // JSONB for +100 Dynamic Tags
            if (!Schema::hasColumn('personas', 'tags')) {
                $table->jsonb('tags')->nullable()->comment('Dynamic attributes and interests');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['location', 'universes', 'tags']);
        });
    }
};
