<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            if (!Schema::hasColumn('event_attendees', 'attended_full_event')) {
                $table->boolean('attended_full_event')->default(false)->after('points_earned');
            }
        });

        Schema::table('personas', function (Blueprint $table) {
            if (!Schema::hasColumn('personas', 'universe_group')) {
                $table->enum('universe_group', ['I', 'II', 'III', 'IV'])->nullable()->after('universe_type');
            }
            if (!Schema::hasColumn('personas', 'tags')) {
                $table->json('tags')->nullable()->after('universe_group')->comment('Additional labels for segmentation');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropColumn('attended_full_event');
        });

        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['universe_group', 'tags']);
        });
    }
};
