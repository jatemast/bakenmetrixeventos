<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_id');
            $table->string('label');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['cuenta_id', 'revoked_at']);
        });

        Schema::create('event_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_id');
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('es_entrega')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index('cuenta_id');
        });

        Schema::create('sync_batch_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_id');
            $table->foreignId('sync_device_id')->nullable()->constrained('sync_devices')->nullOnDelete();
            $table->string('tipo', 50);
            $table->string('batch_id')->nullable();
            $table->unsignedInteger('accepted')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        Schema::table('personas', function (Blueprint $table) {
            if (! Schema::hasColumn('personas', 'external_id')) {
                $table->string('external_id', 64)->nullable()->after('id');
                $table->unique(['cuenta_id', 'external_id']);
            }

            if (! Schema::hasColumn('personas', 'curp')) {
                $table->string('curp', 18)->nullable()->after('cedula');
            }
        });

        Schema::table('event_attendees', function (Blueprint $table) {
            if (! Schema::hasColumn('event_attendees', 'source_key')) {
                $table->string('source_key', 191)->nullable()->after('id');
                $table->unique(['cuenta_id', 'source_key']);
            }

            if (! Schema::hasColumn('event_attendees', 'staff_user_id')) {
                $table->foreignId('staff_user_id')->nullable()->after('checkin_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            if (Schema::hasColumn('event_attendees', 'staff_user_id')) {
                $table->dropConstrainedForeignId('staff_user_id');
            }

            if (Schema::hasColumn('event_attendees', 'source_key')) {
                $table->dropUnique(['cuenta_id', 'source_key']);
                $table->dropColumn('source_key');
            }
        });

        Schema::table('personas', function (Blueprint $table) {
            if (Schema::hasColumn('personas', 'curp')) {
                $table->dropColumn('curp');
            }

            if (Schema::hasColumn('personas', 'external_id')) {
                $table->dropUnique(['cuenta_id', 'external_id']);
                $table->dropColumn('external_id');
            }
        });

        Schema::dropIfExists('sync_batch_logs');
        Schema::dropIfExists('event_staff_assignments');
        Schema::dropIfExists('sync_devices');
    }
};
