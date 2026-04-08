<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade')->comment('The citizen responsible for this beneficiary');
            $table->string('nombre');
            $table->string('tipo')->comment('e.g. mascota, bebe, alumno, adulto_mayor');
            $table->json('metadata')->nullable()->comment('Specific attributes based on tipo (breed, age, blood type, etc.)');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiarios');
    }
};
