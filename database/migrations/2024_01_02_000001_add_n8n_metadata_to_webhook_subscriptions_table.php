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
            $table->string('node_id')->nullable()->after('webhook_url');
            $table->string('workflow_id')->nullable()->after('node_id');
            $table->boolean('verify_hmac')->default(true)->after('workflow_id');
            $table->boolean('require_timestamp')->default(true)->after('verify_hmac');
            $table->string('expected_source_ip')->nullable()->after('require_timestamp');
            
            // Add indexes for better performance
            $table->index(['node_id', 'workflow_id']);
            $table->index('webhook_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('n8n_webhook_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['node_id', 'workflow_id']);
            $table->dropIndex(['webhook_url']);
            $table->dropColumn([
                'node_id',
                'workflow_id',
                'verify_hmac',
                'require_timestamp',
                'expected_source_ip'
            ]);
        });
    }
}; 