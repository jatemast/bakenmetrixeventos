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
        Schema::create('event_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name')->comment('Repository name/title');
            $table->text('description')->nullable();
            $table->enum('scope', ['campaign', 'event'])->default('event')->comment('Repository applies to campaign or event');
            $table->string('pdf_path')->nullable()->comment('Path to original PDF file');
            $table->json('rules_data')->nullable()->comment('Parsed rules/FAQs as JSON for AI agents');
            $table->json('faqs')->nullable()->comment('Frequently asked questions');
            $table->json('qr_logic')->nullable()->comment('QR-specific logic and rules');
            $table->timestamps();
            
            $table->index(['campaign_id', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_repositories');
    }
};
