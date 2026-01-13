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
            $table->timestamp('entry_timestamp')->nullable()->after('event_id');
            $table->timestamp('exit_timestamp')->nullable()->after('entry_timestamp');
            $table->enum('attendance_status', ['registered', 'entered', 'exited', 'full_attendance'])->default('registered')->after('exit_timestamp');
            $table->foreignId('entry_qr_id')->nullable()->constrained('qr_codes')->onDelete('set null')->after('attendance_status');
            $table->foreignId('exit_qr_id')->nullable()->constrained('qr_codes')->onDelete('set null')->after('entry_qr_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropColumn(['entry_timestamp', 'exit_timestamp', 'attendance_status', 'entry_qr_id', 'exit_qr_id']);
        });
    }
};
