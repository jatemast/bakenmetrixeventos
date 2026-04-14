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
            // Remove redundant fields
            $table->dropColumn([
                'objective',
                'target_citizen',
                'target_universes',
                'special_observations',
                'citizen_segmentation_file',
                'leader_segmentation_file',
                'militant_segmentation_file'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('objective')->nullable();
            $table->string('target_citizen')->nullable();
            $table->json('target_universes')->nullable();
            $table->text('special_observations')->nullable();
            $table->string('citizen_segmentation_file')->nullable();
            $table->string('leader_segmentation_file')->nullable();
            $table->string('militant_segmentation_file')->nullable();
        });
    }
};
