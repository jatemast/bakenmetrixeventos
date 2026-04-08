<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_codes', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('cp', 10)->index();
            $blueprint->string('colonia')->index();
            $blueprint->string('municipio')->index();
            $blueprint->string('estado')->index();
            $blueprint->string('entidad_id', 10)->nullable();
            $blueprint->string('municipio_id', 10)->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_codes');
    }
};
