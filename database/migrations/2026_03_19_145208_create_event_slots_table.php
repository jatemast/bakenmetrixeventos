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
        Schema::create('event_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->time('start_time'); // Ej: 10:00:00
            $table->time('end_time');   // Ej: 10:20:00
            $table->integer('capacity')->default(4); // 4 mesas de vacunación
            $table->integer('booked_count')->default(0); // Para evitar consultas COUNT() costosas
            $table->enum('status', ['available', 'full', 'locked'])->default('available');
            $table->timestamps();
            
            // Un evento no puede tener dos slots que empiecen a la misma hora exacta
            $table->unique(['event_id', 'start_time']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_slots');
    }
};
