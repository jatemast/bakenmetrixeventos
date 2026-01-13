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
        // Check if whatsapp_sessions table exists
        if (Schema::hasTable('whatsapp_sessions')) {
            Schema::table('whatsapp_sessions', function (Blueprint $table) {
                // CRITICAL: Add context_namespace for AI agent isolation
                // Format: campaign_{id}_event_{id}
                if (!Schema::hasColumn('whatsapp_sessions', 'context_namespace')) {
                    $table->string('context_namespace', 255)->nullable()->after('event_id');
                }
                
                // Add AI agent control flag
                if (!Schema::hasColumn('whatsapp_sessions', 'ai_agent_enabled')) {
                    $table->boolean('ai_agent_enabled')->default(true)->after('context_namespace');
                }
                
                // Add session status tracking
                if (!Schema::hasColumn('whatsapp_sessions', 'session_status')) {
                    $table->enum('session_status', ['active', 'expired', 'closed'])
                        ->default('active')->after('ai_agent_enabled');
                }
            });
        } else {
            // Create the table if it doesn't exist
            Schema::create('whatsapp_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->onDelete('cascade');
                $table->foreignId('persona_id')->nullable()->constrained()->onDelete('set null');
                $table->string('whatsapp_number', 20);
                $table->string('context_namespace', 255); // campaign_{id}_event_{id}
                $table->boolean('ai_agent_enabled')->default(true);
                $table->enum('session_status', ['active', 'expired', 'closed'])->default('active');
                $table->timestamp('last_message_at')->nullable();
                $table->integer('message_count')->default(0);
                $table->timestamps();
                
                // Indexes
                $table->index('whatsapp_number');
                $table->index('context_namespace');
                $table->index('session_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('whatsapp_sessions')) {
            // Check if we created the table or just modified it
            // If the table has very few columns, we created it
            $columns = Schema::getColumnListing('whatsapp_sessions');
            
            if (count($columns) > 10) {
                // We created it, so drop the whole table
                Schema::dropIfExists('whatsapp_sessions');
            } else {
                // We only modified it, so just drop the new columns
                Schema::table('whatsapp_sessions', function (Blueprint $table) {
                    if (Schema::hasColumn('whatsapp_sessions', 'session_status')) {
                        $table->dropColumn('session_status');
                    }
                    if (Schema::hasColumn('whatsapp_sessions', 'ai_agent_enabled')) {
                        $table->dropColumn('ai_agent_enabled');
                    }
                    if (Schema::hasColumn('whatsapp_sessions', 'context_namespace')) {
                        $table->dropColumn('context_namespace');
                    }
                });
            }
        }
    }
};
