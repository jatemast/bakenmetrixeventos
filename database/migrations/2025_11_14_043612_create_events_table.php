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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->string('detail');
            $table->date('date');
            $table->string('responsible');
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->text('dynamic')->nullable();
            $table->string('ai_agent_info_file')->nullable(); // Para el archivo PDF
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('municipality')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('qr_code_data')->nullable(); // Para almacenar los datos del QR
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
