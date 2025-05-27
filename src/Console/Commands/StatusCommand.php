<?php

namespace N8n\Eloquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use N8n\Eloquent\Services\ModelDiscoveryService;
use N8n\Eloquent\Services\WebhookService;

class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:status 
                            {--detailed : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the status of n8n Eloquent integration';

    /**
     * The model discovery service.
     *
     * @var \N8n\Eloquent\Services\ModelDiscoveryService
     */
    protected $modelDiscovery;

    /**
     * The webhook service.
     *
     * @var \N8n\Eloquent\Services\WebhookService
     */
    protected $webhookService;

    /**
     * Create a new command instance.
     *
     * @param  \N8n\Eloquent\Services\ModelDiscoveryService  $modelDiscovery
     * @param  \N8n\Eloquent\Services\WebhookService  $webhookService
     * @return void
     */
    public function __construct(
        ModelDiscoveryService $modelDiscovery,
        WebhookService $webhookService
    ) {
        parent::__construct();
        $this->modelDiscovery = $modelDiscovery;
        $this->webhookService = $webhookService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔍 n8n Eloquent Integration Status');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        // Configuration Status
        $this->showConfigurationStatus();
        
        // Model Discovery Status
        $this->showModelDiscoveryStatus();
        
        // Webhook Status
        $this->showWebhookStatus();
        
        // Event Configuration Status
        $this->showEventStatus();
        
        if ($this->option('detailed')) {
            $this->showDetailedInformation();
        }
        
        return 0;
    }

    /**
     * Show configuration status.
     *
     * @return void
     */
    protected function showConfigurationStatus(): void
    {
        $this->newLine();
        $this->info('⚙️  Configuration Status:');
        
        $apiSecret = config('n8n-eloquent.api.secret');
        $this->line('API Secret: ' . ($apiSecret ? '✅ Configured' : '❌ Not configured'));
        
        $n8nUrl = config('n8n-eloquent.n8n.url');
        $this->line('n8n URL: ' . ($n8nUrl ? "✅ {$n8nUrl}" : '❌ Not configured'));
        
        $loggingEnabled = config('n8n-eloquent.logging.enabled');
        $this->line('Logging: ' . ($loggingEnabled ? '✅ Enabled' : '⚠️  Disabled'));
        
        $eventsEnabled = config('n8n-eloquent.events.enabled');
        $this->line('Events: ' . ($eventsEnabled ? '✅ Enabled' : '❌ Disabled'));
    }

    /**
     * Show model discovery status.
     *
     * @return void
     */
    protected function showModelDiscoveryStatus(): void
    {
        $this->newLine();
        $this->info('📊 Model Discovery Status:');
        
        try {
            $models = $this->modelDiscovery->getModels();
            $mode = config('n8n-eloquent.models.mode', 'all');
            
            $this->line("Discovery Mode: {$mode}");
            $this->line("Models Found: {$models->count()}");
            
            if ($models->count() > 0) {
                $this->line('Models:');
                foreach ($models->take(5) as $model) {
                    $this->line("  • {$model}");
                }
                
                if ($models->count() > 5) {
                    $this->line("  ... and " . ($models->count() - 5) . " more");
                }
            }
        } catch (\Throwable $e) {
            $this->error("❌ Error discovering models: {$e->getMessage()}");
        }
    }

    /**
     * Show webhook status.
     *
     * @return void
     */
    protected function showWebhookStatus(): void
    {
        $this->newLine();
        $this->info('🔗 Webhook Status:');
        
        try {
            $stats = $this->webhookService->getWebhookStats();
            
            $this->line("Total Subscriptions: {$stats['total_subscriptions']}");
            $this->line("Active Subscriptions: {$stats['active_subscriptions']}");
            $this->line("Inactive Subscriptions: {$stats['inactive_subscriptions']}");
            
            if (!empty($stats['models'])) {
                $this->line('Subscribed Models:');
                foreach ($stats['models'] as $model => $count) {
                    $modelName = class_basename($model);
                    $this->line("  • {$modelName}: {$count} subscription(s)");
                }
            }
        } catch (\Throwable $e) {
            $this->error("❌ Error fetching webhook stats: {$e->getMessage()}");
        }
    }

    /**
     * Show event configuration status.
     *
     * @return void
     */
    protected function showEventStatus(): void
    {
        $this->newLine();
        $this->info('🎯 Event Configuration:');
        
        $defaultEvents = config('n8n-eloquent.events.default', []);
        $this->line('Default Events: ' . implode(', ', $defaultEvents));
        
        $propertyEventsEnabled = config('n8n-eloquent.events.property_events.enabled');
        $this->line('Property Events: ' . ($propertyEventsEnabled ? '✅ Enabled' : '❌ Disabled'));
        
        $queueEnabled = config('n8n-eloquent.events.queue.enabled');
        $this->line('Queue Processing: ' . ($queueEnabled ? '✅ Enabled' : '❌ Disabled'));
        
        $transactionsEnabled = config('n8n-eloquent.events.transactions.enabled');
        $this->line('Transactions: ' . ($transactionsEnabled ? '✅ Enabled' : '❌ Disabled'));
    }

    /**
     * Show detailed information.
     *
     * @return void
     */
    protected function showDetailedInformation(): void
    {
        $this->newLine();
        $this->info('🔍 Detailed Information:');
        
        // Show model configurations
        $modelConfigs = config('n8n-eloquent.models.config', []);
        if (!empty($modelConfigs)) {
            $this->line('Model-specific Configurations:');
            foreach ($modelConfigs as $model => $config) {
                $modelName = class_basename($model);
                $this->line("  • {$modelName}:");
                if (isset($config['events'])) {
                    $this->line("    Events: " . implode(', ', $config['events']));
                }
                if (isset($config['watched_attributes'])) {
                    $this->line("    Watched Attributes: " . implode(', ', $config['watched_attributes']));
                }
            }
        }
        
        // Show rate limiting configuration
        $rateLimiting = config('n8n-eloquent.api.rate_limiting', []);
        if ($rateLimiting['enabled'] ?? false) {
            $this->line('Rate Limiting:');
            $this->line("  Max Attempts: {$rateLimiting['max_attempts']}");
            $this->line("  Decay Minutes: {$rateLimiting['decay_minutes']}");
        }
        
        // Show cache information
        $cacheKey = 'n8n_eloquent_webhook_subscriptions';
        $cacheExists = Cache::has($cacheKey);
        $this->line('Cache Status: ' . ($cacheExists ? '✅ Active' : '⚠️  Empty'));
    }
} 