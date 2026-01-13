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
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_code', 50)->unique();
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            $table->decimal('points_redeemed', 10, 2);
            $table->text('reward_description');
            $table->string('qr_code_path')->nullable();
            $table->enum('status', ['pending', 'validated', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('redeemed_at');
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('voucher_code');
            $table->index('status');
            $table->index('persona_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
