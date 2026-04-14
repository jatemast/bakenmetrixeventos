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
        // En PostgreSQL los ENUMS son tipos de datos. Para actualizarlos necesitamos recrear la columna o usar sentencias ALTER.
        // Como estamos usando Laravel DB, podemos simplemente ejecutar el SQL raw.
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_status_check");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE campaigns ALTER COLUMN status TYPE VARCHAR(255)");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE campaigns ALTER COLUMN status SET DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No es estrictamente necesario volver al enum en el rollback si queremos mantener la flexibilidad
    }
};
