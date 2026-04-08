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
        if (!Schema::hasColumn('event_types', 'success_message')) {
            Schema::table('event_types', function (Blueprint $table) {
                $table->text('success_message')->nullable()->after('beneficiary_label');
            });
        }

        // events table already has it (confirmed via tinker)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->dropColumn('success_message');
        });
    }
};
