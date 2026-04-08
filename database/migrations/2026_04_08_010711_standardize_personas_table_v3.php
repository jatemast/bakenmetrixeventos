<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            // General Fields
            if (!Schema::hasColumn('personas', 'curp')) $table->string('curp', 18)->unique()->nullable()->after('id');
            if (!Schema::hasColumn('personas', 'email')) $table->string('email')->unique()->nullable()->after('numero_celular');
            
            // Special Fields
            if (!Schema::hasColumn('personas', 'codigo_ciudadano')) $table->string('codigo_ciudadano', 20)->unique()->nullable()->after('curp');
            if (!Schema::hasColumn('personas', 'geom')) $table->geometry('geom')->nullable()->after('location'); // Requires PostGIS or MySQL 5.7+
            
            // Electoral Fields
            if (!Schema::hasColumn('personas', 'clave_elector')) $table->string('clave_elector', 18)->unique()->nullable()->after('codigo_ciudadano');
            if (!Schema::hasColumn('personas', 'seccion')) $table->string('seccion', 10)->nullable()->after('clave_elector');
            if (!Schema::hasColumn('personas', 'vigencia')) $table->string('vigencia', 4)->nullable()->after('seccion');
            
            // Additional Fields
            if (!Schema::hasColumn('personas', 'tipo_sangre')) $table->string('tipo_sangre', 5)->nullable();
            if (!Schema::hasColumn('personas', 'servicios')) $table->text('servicios')->nullable();
            if (!Schema::hasColumn('personas', 'tarifa')) $table->decimal('tarifa', 10, 2)->nullable();
            if (!Schema::hasColumn('personas', 'categoria')) $table->string('categoria')->nullable();
        });

        // Tags and Directory Structure (Enterprise Tree/Branches)
        Schema::create('tags', function (Blueprint $table) {
            $table->id('tag_id');
            $table->string('nombre')->index();
            $table->string('slug')->unique();
            $table->string('categoria')->index(); // Branches
            $table->timestamps();
        });

        Schema::create('persona_tags', function (Blueprint $table) {
            $table->id('directorio_id');
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->unsignedBigInteger('tag_id');
            $table->foreign('tag_id')->references('tag_id')->on('tags')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_tags');
        Schema::dropIfExists('tags');
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn([
                'curp', 'email', 'codigo_ciudadano', 'geom', 
                'clave_elector', 'seccion', 'vigencia', 
                'tipo_sangre', 'servicios', 'tarifa', 'categoria'
            ]);
        });
    }
};
