<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            // Add missing column for campaign tracking
            if (!Schema::hasColumn('personas', 'last_invited_at')) {
                $table->timestamp('last_invited_at')->nullable()->after('last_invited_event_id');
            }
            
            // Basic performance indexes
            if (!Schema::hasColumn('personas', 'tenant_id')) {
                 $table->uuid('tenant_id')->nullable()->index();
            }

            // Performance index for universe classification - Laravel 11/12 syntax
            $indexes = Schema::getIndexes('personas');
            $hasIndex = false;
            foreach ($indexes as $index) {
                if ($index['name'] === 'personas_universe_type_index') {
                    $hasIndex = true;
                    break;
                }
            }
            
            if (!$hasIndex) {
                 $table->index('universe_type');
            }
        });

        // GIN indexes for JSONB columns — PostgreSQL ONLY
        // These are critical for the "Super Filtro" matching tags and universes
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE personas ALTER COLUMN tags TYPE JSONB USING tags::JSONB');
            DB::statement('ALTER TABLE personas ALTER COLUMN universes TYPE JSONB USING universes::JSONB');
            DB::statement('ALTER TABLE personas ALTER COLUMN metadata TYPE JSONB USING metadata::JSONB');
            
            DB::statement('CREATE INDEX IF NOT EXISTS personas_tags_gin ON personas USING GIN (tags)');
            DB::statement('CREATE INDEX IF NOT EXISTS personas_universes_gin ON personas USING GIN (universes)');
            DB::statement('CREATE INDEX IF NOT EXISTS personas_metadata_gin ON personas USING GIN (metadata)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['last_invited_at']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['universe_type']);
        });

        DB::statement('DROP INDEX IF EXISTS personas_tags_gin');
        DB::statement('DROP INDEX IF EXISTS personas_universes_gin');
        DB::statement('DROP INDEX IF EXISTS personas_metadata_gin');
    }
};
