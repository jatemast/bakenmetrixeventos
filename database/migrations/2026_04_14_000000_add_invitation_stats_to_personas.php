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
            if (!Schema::hasColumn('personas', 'last_invited_event_id')) {
                $table->foreignId('last_invited_event_id')->nullable()->constrained('events')->onDelete('set null');
            }
            if (!Schema::hasColumn('personas', 'last_invited_at')) {
                $table->timestamp('last_invited_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['last_invited_event_id']);
            $table->dropColumn(['last_invited_event_id', 'last_invited_at']);
        });
    }
};
