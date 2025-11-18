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
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido_paterno');
            $table->string('apellido_materno');
            $table->integer('edad');
            $table->char('sexo', 1); // 'H' for Hombre, 'M' for Mujer
            $table->string('calle');
            $table->string('numero_exterior');
            $table->string('numero_interior')->nullable(); // Puede ser NA
            $table->string('colonia');
            $table->string('codigo_postal');
            $table->string('municipio');
            $table->string('estado');
            $table->string('numero_celular', 20)->nullable();
            $table->string('numero_telefono', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
