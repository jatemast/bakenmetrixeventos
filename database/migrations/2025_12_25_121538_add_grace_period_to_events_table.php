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
            $table->integer('grace_period_hours')->default(1)->after('points_distributed');
            $table->timestamp('ended_at')->nullable()->after('grace_period_hours');
            $table->boolean('auto_close_scheduled')->default(false)->after('ended_at');
            $table->boolean('points_distribution_scheduled')->default(false)->after('auto_close_scheduled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['grace_period_hours', 'ended_at', 'auto_close_scheduled', 'points_distribution_scheduled']);
        });
    }
};
