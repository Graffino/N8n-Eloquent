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
        Schema::table('n8n_webhook_subscriptions', function (Blueprint $table) {
            // Add field to distinguish between model and event subscriptions
            $table->boolean('is_event_subscription')->default(false)->after('active');
            
            // Add index for event subscriptions
            $table->index(['is_event_subscription', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('n8n_webhook_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['is_event_subscription', 'active']);
            $table->dropColumn('is_event_subscription');
        });
    }
}; 