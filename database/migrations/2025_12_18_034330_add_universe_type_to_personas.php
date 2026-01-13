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
            $table->enum('universe_type', ['U1', 'U2', 'U3', 'U4'])->default('U1')->after('bonus_points');
            $table->foreignId('leader_id')->nullable()->constrained('personas')->onDelete('set null')->after('universe_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['universe_type', 'leader_id']);
        });
    }
};
