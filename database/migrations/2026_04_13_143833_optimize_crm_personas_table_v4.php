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
        Schema::table('personas', function (Blueprint $table) {
            // Uniqueness (The best way to prevent duplicates)
            // Note: We use numero_celular instead of whatsapp for DB consistency
            $table->string('numero_celular')->unique()->change();
            
            // Performance Indexes for n8n/Fast Filters
            $table->index('colonia');
            $table->index('seccion');
            $table->index('municipio');
            $table->index('codigo_postal');
            
            // CRM Auditability
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropUnique(['numero_celular']);
            $table->dropIndex(['colonia']);
            $table->dropIndex(['seccion']);
            $table->dropIndex(['municipio']);
            $table->dropIndex(['codigo_postal']);
            $table->dropSoftDeletes();
        });
    }
};
