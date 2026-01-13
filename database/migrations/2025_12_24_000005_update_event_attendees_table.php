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
        Schema::table('event_attendees', function (Blueprint $table) {
            // Add registration tracking
            $table->timestamp('registered_at')->nullable()->after('persona_id');
            $table->string('registration_qr_code', 100)->nullable()->after('registered_at');
            
            // Add QR code tracking for check-in and check-out
            $table->string('checkin_qr_code', 100)->nullable()->after('checkin_at');
            $table->string('checkout_qr_code', 100)->nullable()->after('checkout_at');
            
            // CRITICAL: Add referred_by for leader referral tracking
            $table->foreignId('referred_by')->nullable()->after('leader_id')
                ->constrained('personas')->onDelete('set null');
            
            // Add referral QR code tracking
            $table->string('referral_qr_code', 100)->nullable()->after('referred_by');
            
            // Add attendance duration calculation
            $table->integer('attendance_duration_minutes')->nullable()->after('checkout_at');
            
            // Add points tracking
            $table->integer('points_earned')->default(0)->after('attendance_duration_minutes');
            $table->boolean('points_distributed')->default(false)->after('points_earned');
        });

        // Update attendance_status enum if it exists to include all statuses
        // Note: This might require recreating the column in MySQL
        // For safety, we'll add a comment about valid values
        // Valid values: registered, checked_in, attended, no_show, full, partial, full_grace
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['referred_by']);
            
            // Drop columns
            $table->dropColumn([
                'registered_at',
                'registration_qr_code',
                'checkin_qr_code',
                'checkout_qr_code',
                'referred_by',
                'referral_qr_code',
                'attendance_duration_minutes',
                'points_earned',
                'points_distributed'
            ]);
        });
    }
};
