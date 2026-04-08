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
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // current_step: Nos dice en qué paso del formulario va (ej: 'awaiting_pet_name')
            if (!Schema::hasColumn('whatsapp_sessions', 'current_step')) {
                $table->string('current_step', 100)->nullable()->after('session_status');
            }
            
            // metadata: Guarda datos temporales (Nombre, Raza, Edad) antes del registro final
            if (!Schema::hasColumn('whatsapp_sessions', 'metadata')) {
                $table->jsonb('metadata')->nullable()->after('current_step');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn(['current_step', 'metadata']);
        });
    }
};
