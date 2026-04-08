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
            $table->string('slot_unit_name')->default('Mesa')->after('max_capacity');
            $table->integer('bonus_points_per_referral')->default(0)->after('bonus_points_for_leader');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['slot_unit_name', 'bonus_points_per_referral']);
        });
    }
};
