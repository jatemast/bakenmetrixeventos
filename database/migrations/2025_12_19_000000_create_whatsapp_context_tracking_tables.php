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
        // Create whatsapp_sessions table for conversation context tracking
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique()->comment('Unique session identifier');
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->string('phone_number', 20)->index();
            $table->foreignId('current_event_id')->nullable()->constrained('events')->onDelete('set null')->comment('Currently active event in conversation');
            $table->enum('conversation_state', ['active', 'awaiting_event_selection', 'resolved'])->default('active');
            $table->json('context_data')->nullable()->comment('Additional context like available events');
            $table->timestamp('last_message_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['phone_number', 'expires_at']);
            $table->index(['persona_id', 'conversation_state']);
        });

        // Add last interaction tracking to personas table
        Schema::table('personas', function (Blueprint $table) {
            $table->foreignId('last_interacted_event_id')->nullable()->after('bonus_points')->constrained('events')->onDelete('set null')->comment('Last event the persona interacted with via QR');
            $table->timestamp('last_interaction_at')->nullable()->after('last_interacted_event_id')->comment('When the last interaction occurred');
            $table->foreignId('last_invited_event_id')->nullable()->after('last_interaction_at')->constrained('events')->onDelete('set null')->comment('Last event persona was invited to');
            
            $table->index('last_interacted_event_id');
            $table->index('last_interaction_at');
        });

        // Add QR scan tracking to event_attendees table
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->enum('last_qr_scan_type', ['QR1', 'QR2', 'QR2-L', 'QR3'])->nullable()->after('exit_time')->comment('Type of last QR code scanned');
            $table->timestamp('last_qr_scan_at')->nullable()->after('last_qr_scan_type')->comment('When the last QR scan occurred');
            
            $table->index(['persona_id', 'last_qr_scan_at']);
        });

        // Create ai_conversations table for logging
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null');
            $table->string('session_id')->nullable();
            $table->text('user_query');
            $table->text('ai_response');
            $table->string('context_source')->nullable()->comment('How event was resolved: session, last_interaction, single_active_event, user_selection');
            $table->json('metadata')->nullable()->comment('Additional context like vector store collection used');
            $table->timestamps();

            $table->index(['persona_id', 'created_at']);
            $table->index(['event_id', 'created_at']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
        
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropColumn(['last_qr_scan_type', 'last_qr_scan_at']);
        });

        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['last_interacted_event_id']);
            $table->dropForeign(['last_invited_event_id']);
            $table->dropColumn(['last_interacted_event_id', 'last_interaction_at', 'last_invited_event_id']);
        });

        Schema::dropIfExists('whatsapp_sessions');
    }
};
