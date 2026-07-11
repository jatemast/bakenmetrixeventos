<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Central account scope on all Events Hub domain tables (same id as tbl_cuentas.id).
     */
    public function up(): void
    {
        $tables = [
            'users',
            'campaigns',
            'events',
            'personas',
            'event_attendees',
            'qr_codes',
            'invitations',
            'event_repositories',
            'bonus_points_history',
            'redemptions',
            'groups',
            'group_members',
            'group_attendances',
            'mascotas',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'cuenta_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('cuenta_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'campaigns',
            'events',
            'personas',
            'event_attendees',
            'qr_codes',
            'invitations',
            'event_repositories',
            'bonus_points_history',
            'redemptions',
            'groups',
            'group_members',
            'group_attendances',
            'mascotas',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'cuenta_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['cuenta_id']);
                $table->dropColumn('cuenta_id');
            });
        }
    }
};
