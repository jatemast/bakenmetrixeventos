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
        // 1. Añadimos a CAMPAIGNS (El Estándar)
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'form_schema')) {
                $table->jsonb('form_schema')->nullable()->after('description');
            }
            if (!Schema::hasColumn('campaigns', 'success_message')) {
                $table->text('success_message')->nullable()->after('form_schema');
            }
        });

        // 2. Añadimos a EVENTS (La Excepción / Sobreescritura)
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'form_schema')) {
                $table->jsonb('form_schema')->nullable()->after('location');
            }
            if (!Schema::hasColumn('events', 'success_message')) {
                $table->text('success_message')->nullable()->after('form_schema');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['form_schema', 'success_message']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['form_schema', 'success_message']);
        });
    }
};
