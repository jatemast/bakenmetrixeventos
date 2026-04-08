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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_slot_id')->constrained('event_slots')->restrictOnDelete();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
            
            // RELACIÓN GENÉRICA (POLIMÓRFICA)
            // Permite que la cita sea vinculada a cualquier "objeto" (Mascota, Trámite, etc)
            $table->nullableMorphs('target'); 
            
            // Para el QR único de la cita
            $table->string('qr_code_token')->unique(); 
            $table->string('assigned_location')->nullable()->comment('Ej: Mesa 2, Cubículo 5');
            
            $table->enum('status', ['pending', 'in_site', 'completed', 'cancelled', 'no_show'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
