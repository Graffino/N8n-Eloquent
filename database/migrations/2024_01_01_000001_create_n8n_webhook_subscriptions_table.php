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
        Schema::create('n8n_webhook_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('model_class')->index();
            $table->json('events');
            $table->string('webhook_url');
            $table->json('properties')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->json('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['model_class', 'active']);
            $table->index(['active', 'created_at']);
            $table->index('last_triggered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('n8n_webhook_subscriptions');
    }
}; 