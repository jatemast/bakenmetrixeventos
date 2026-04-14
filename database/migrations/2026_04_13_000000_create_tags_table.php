<?php
/**
 * Migration for Universal Tagging System (USCA)
 * Allows tagging Personas and Events for hyper-segmentation.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Force drop if the table exists but is broken (e.g. has tag_id instead of id)
        if (Schema::hasTable('tags') && !Schema::hasColumn('tags', 'id')) {
            DB::statement('DROP TABLE IF EXISTS persona_tags CASCADE');
            DB::statement('DROP TABLE IF EXISTS tags CASCADE');
        }

        if (!Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->string('type')->default('general'); // e.g., 'interest', 'demographic', 'political'
                $table->string('color')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table) {
                $table->foreignId('tag_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('taggable_id');
                $table->string('taggable_type');
                $table->timestamps();

                $table->index(['taggable_id', 'taggable_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
