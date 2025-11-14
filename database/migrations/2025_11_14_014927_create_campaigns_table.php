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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('theme')->nullable();
            $table->string('target_citizen')->nullable();
            $table->text('special_observations')->nullable();
            $table->string('citizen_segmentation_file')->nullable();
            $table->string('leader_segmentation_file')->nullable();
            $table->string('militant_segmentation_file')->nullable();
            $table->string('requesting_dependency')->nullable();
            $table->string('campaign_manager')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('number_of_events')->nullable();
            $table->integer('campaign_number')->unique()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
