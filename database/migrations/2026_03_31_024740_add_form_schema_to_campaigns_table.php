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
            // form_schema: Guardaremos un JSON con las preguntas dinámicas
            // Ejemplo: {"pet_name": "Nombre?", "pet_breed": "Raza?"}
            if (!Schema::hasColumn('campaigns', 'form_schema')) {
                $table->jsonb('form_schema')->nullable()->after('description');
            }
            
            // Mensaje final personalizado al terminar el flujo
            if (!Schema::hasColumn('campaigns', 'success_message')) {
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
    }
};
