<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('qr1_image_path')->nullable()->after('checkout_code')->comment('QR1 Invitation image path');
            $table->string('qr2_image_path')->nullable()->after('qr1_image_path')->comment('QR2 Check-in image path');
            $table->string('qr3_image_path')->nullable()->after('qr2_image_path')->comment('QR3 Check-out image path');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['qr1_image_path', 'qr2_image_path', 'qr3_image_path']);
        });
    }
};
