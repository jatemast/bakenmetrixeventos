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
            $table->timestamp('cdz_expires_at')->after('cedula_digital')->nullable();
            $table->integer('cdz_version')->after('cdz_expires_at')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['cdz_expires_at', 'cdz_version']);
        });
    }
};
