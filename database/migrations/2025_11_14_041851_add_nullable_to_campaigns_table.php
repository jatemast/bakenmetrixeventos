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
            $table->string('name')->nullable()->change();
            $table->string('theme')->nullable()->change();
            $table->string('target_citizen')->nullable()->change();
            $table->string('requesting_dependency')->nullable()->change();
            $table->string('campaign_manager')->nullable()->change();
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
            $table->integer('number_of_events')->nullable()->change();
            $table->integer('campaign_number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('theme')->nullable(false)->change();
            $table->string('target_citizen')->nullable(false)->change();
            $table->string('requesting_dependency')->nullable(false)->change();
            $table->string('campaign_manager')->nullable(false)->change();
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
            $table->integer('number_of_events')->nullable(false)->change();
            $table->integer('campaign_number')->nullable(false)->change();
        });
    }
};
