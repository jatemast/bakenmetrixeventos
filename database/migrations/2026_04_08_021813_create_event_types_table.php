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
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->json('default_form_schema')->nullable(); // Default registration form
            $table->json('default_slot_config')->nullable(); // {"interval_minutes": 20, "capacity_per_slot": 4}
            $table->json('default_qr_config')->nullable();   // {"types": ["QR1", "QR2", "QR3"]}
            $table->json('default_points_config')->nullable(); // {"attendee": 5, "leader": 3, "referral": 2}
            $table->boolean('requires_appointment')->default(false);
            $table->boolean('has_beneficiaries')->default(false);
            $table->string('beneficiary_label')->nullable(); // "Mascota", "Hijo", "Vehículo"
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
