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
        // Verificamos si la tabla existe
        if (Schema::hasTable('whatsapp_sessions')) {
            Schema::table('whatsapp_sessions', function (Blueprint $table) {
                // Agregamos event_id si no existe
                if (!Schema::hasColumn('whatsapp_sessions', 'event_id')) {
                    $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
                }

                // Agregamos persona_id si no existe
                if (!Schema::hasColumn('whatsapp_sessions', 'persona_id')) {
                    $table->foreignId('persona_id')->nullable()->constrained()->onDelete('set null');
                }

                // Agregamos context_namespace si no existe
                if (!Schema::hasColumn('whatsapp_sessions', 'context_namespace')) {
                    $table->string('context_namespace', 255)->nullable();
                }

                // Agregamos ai_agent_enabled si no existe
                if (!Schema::hasColumn('whatsapp_sessions', 'ai_agent_enabled')) {
                    $table->boolean('ai_agent_enabled')->default(true);
                }

                // Agregamos session_status si no existe
                if (!Schema::hasColumn('whatsapp_sessions', 'session_status')) {
                    $table->enum('session_status', ['active', 'expired', 'closed'])
                        ->default('active');
                }

                // Agregamos columnas adicionales si no existen
                if (!Schema::hasColumn('whatsapp_sessions', 'last_message_at')) {
                    $table->timestamp('last_message_at')->nullable();
                }
                if (!Schema::hasColumn('whatsapp_sessions', 'message_count')) {
                    $table->integer('message_count')->default(0);
                }
            });
        } else {
            // Creamos la tabla si no existe
            Schema::create('whatsapp_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('persona_id')->nullable()->constrained()->onDelete('set null');
                $table->string('whatsapp_number', 20);
                $table->string('context_namespace', 255)->nullable();
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
            // Lista de columnas actuales
            $columns = Schema::getColumnListing('whatsapp_sessions');

            if (count($columns) <= 10) {
                // Probablemente la tabla fue creada por esta migración
                Schema::dropIfExists('whatsapp_sessions');
            } else {
                // Eliminamos solo las columnas nuevas
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
                    if (Schema::hasColumn('whatsapp_sessions', 'last_message_at')) {
                        $table->dropColumn('last_message_at');
                    }
                    if (Schema::hasColumn('whatsapp_sessions', 'message_count')) {
                        $table->dropColumn('message_count');
                    }
                    // NOTA: No eliminamos event_id ni persona_id en down
                });
            }
        }
    }
};
