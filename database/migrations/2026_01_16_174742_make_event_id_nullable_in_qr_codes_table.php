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
        // SQLite doesn't support modifying columns easily
        // We need to recreate the table
        Schema::create('qr_codes_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['QR1', 'QR2', 'QR3', 'QR2-L', 'QR-MILITANT'])->comment('QR1=Registration, QR2=Entry, QR3=Exit, QR2-L=Leader Guest Entry, QR-MILITANT=Personalized Militant');
            $table->string('code', 255)->unique()->comment('Unique QR code string');
            $table->foreignId('persona_id')->nullable()->constrained('personas')->onDelete('set null')->comment('For personalized QRs (militants/leaders)');
            $table->foreignId('leader_id')->nullable()->constrained('personas')->onDelete('set null')->comment('Leader ID for QR2-L codes');
            $table->timestamp('expires_at')->nullable()->comment('Optional expiry');
            $table->boolean('is_active')->default(true);
            $table->integer('scan_count')->default(0)->comment('Track usage');
            $table->timestamps();
            
            $table->index(['event_id', 'type']);
            $table->index('code');
        });
        
        // Copy data from old table
        DB::statement('INSERT INTO qr_codes_new SELECT * FROM qr_codes');
        
        // Drop old table
        Schema::dropIfExists('qr_codes');
        
        // Rename new table
        Schema::rename('qr_codes_new', 'qr_codes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback not needed for testing
    }
};
