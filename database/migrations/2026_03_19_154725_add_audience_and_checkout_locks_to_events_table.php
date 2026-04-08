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
        Schema::table('events', function (Blueprint $table) {
            $table->json('target_audience_filters')->nullable()->after('max_capacity');
            $table->boolean('is_checkout_active')->default(false)->after('target_audience_filters');
            $table->integer('minimum_minutes_for_points')->default(60)->after('is_checkout_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'target_audience_filters',
                'is_checkout_active',
                'minimum_minutes_for_points'
            ]);
        });
    }
};
