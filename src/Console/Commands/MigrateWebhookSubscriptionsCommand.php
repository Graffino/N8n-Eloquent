<?php

namespace Shortinc\N8nEloquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Shortinc\N8nEloquent\Models\WebhookSubscription;
use Shortinc\N8nEloquent\Services\WebhookService;

class MigrateWebhookSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:migrate-subscriptions 
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Force migration even if database has existing subscriptions}
                            {--backup : Create backup of cache data before migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate webhook subscriptions from cache to database storage';

    /**
     * The webhook service instance.
     *
     * @var WebhookService
     */
    protected WebhookService $webhookService;

    /**
     * Create a new command instance.
     *
     * @param  WebhookService  $webhookService
     */
    public function __construct(WebhookService $webhookService)
    {
        parent::__construct();
        $this->webhookService = $webhookService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🔄 Starting webhook subscription migration...');

        // Check if migration is needed
        if (!$this->migrationNeeded()) {
            return Command::SUCCESS;
        }

        // Get cache subscriptions
        $cacheSubscriptions = $this->getCacheSubscriptions();
        
        if (empty($cacheSubscriptions)) {
            $this->info('✅ No subscriptions found in cache. Migration not needed.');
            return Command::SUCCESS;
        }

        $this->info("📊 Found " . count($cacheSubscriptions) . " subscriptions in cache");

        // Show what will be migrated
        $this->showMigrationPreview($cacheSubscriptions);

        // Dry run mode
        if ($this->option('dry-run')) {
            $this->info('🔍 Dry run mode - no changes made');
            return Command::SUCCESS;
        }

        // Confirm migration
        if (!$this->option('force') && !$this->confirm('Do you want to proceed with the migration?')) {
            $this->info('❌ Migration cancelled');
            return Command::FAILURE;
        }

        // Create backup if requested
        if ($this->option('backup')) {
            $this->createBackup($cacheSubscriptions);
        }

        // Perform migration
        return $this->performMigration($cacheSubscriptions);
    }

    /**
     * Check if migration is needed.
     *
     * @return bool
     */
    protected function migrationNeeded(): bool
    {
        $existingCount = WebhookSubscription::count();
        
        if ($existingCount > 0 && !$this->option('force')) {
            $this->warn("⚠️  Database already contains {$existingCount} webhook subscriptions");
            $this->info('Use --force to migrate anyway or --dry-run to see what would be migrated');
            return false;
        }

        return true;
    }

    /**
     * Get subscriptions from cache.
     *
     * @return array
     */
    protected function getCacheSubscriptions(): array
    {
        $cacheKey = 'n8n_webhook_subscriptions';
        return Cache::get($cacheKey, []);
    }

    /**
     * Show migration preview.
     *
     * @param  array  $subscriptions
     * @return void
     */
    protected function showMigrationPreview(array $subscriptions): void
    {
        $this->info("\n📋 Migration Preview:");
        
        $headers = ['ID', 'Model', 'Events', 'URL', 'Active', 'Created'];
        $rows = [];

        foreach ($subscriptions as $subscription) {
            $rows[] = [
                substr($subscription['id'], 0, 8) . '...',
                class_basename($subscription['model']),
                implode(', ', $subscription['events']),
                substr($subscription['webhook_url'], 0, 30) . '...',
                isset($subscription['active']) ? ($subscription['active'] ? 'Yes' : 'No') : 'Yes',
                isset($subscription['created_at']) 
                    ? \Carbon\Carbon::parse($subscription['created_at'])->format('Y-m-d H:i')
                    : 'Unknown',
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Create backup of cache data.
     *
     * @param  array  $subscriptions
     * @return void
     */
    protected function createBackup(array $subscriptions): void
    {
        $backupFile = storage_path('app/n8n-webhook-subscriptions-backup-' . date('Y-m-d-H-i-s') . '.json');
        
        file_put_contents($backupFile, json_encode($subscriptions, JSON_PRETTY_PRINT));
        
        $this->info("💾 Backup created: {$backupFile}");
    }

    /**
     * Perform the migration.
     *
     * @param  array  $subscriptions
     * @return int
     */
    protected function performMigration(array $subscriptions): int
    {
        $migrated = 0;
        $errors = 0;

        $this->info("\n🚀 Starting migration...");
        
        $progressBar = $this->output->createProgressBar(count($subscriptions));
        $progressBar->start();

        DB::beginTransaction();

        try {
            foreach ($subscriptions as $subscriptionData) {
                try {
                    // Validate subscription data
                    if (!$this->validateSubscriptionData($subscriptionData)) {
                        $errors++;
                        $progressBar->advance();
                        continue;
                    }

                    // Check if subscription already exists
                    if (WebhookSubscription::find($subscriptionData['id'])) {
                        $progressBar->advance();
                        continue;
                    }

                    // Create subscription in database
                    WebhookSubscription::create([
                        'id' => $subscriptionData['id'],
                        'model_class' => $subscriptionData['model'],
                        'events' => $subscriptionData['events'],
                        'webhook_url' => $subscriptionData['webhook_url'],
                        'properties' => $subscriptionData['properties'] ?? [],
                        'active' => $subscriptionData['active'] ?? true,
                        'created_at' => isset($subscriptionData['created_at']) 
                            ? \Carbon\Carbon::parse($subscriptionData['created_at'])
                            : now(),
                    ]);

                    $migrated++;
                } catch (\Throwable $e) {
                    $this->error("\n❌ Error migrating subscription {$subscriptionData['id']}: " . $e->getMessage());
                    $errors++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();

            if ($errors === 0) {
                DB::commit();
                
                // Clear cache after successful migration
                Cache::forget('n8n_webhook_subscriptions');
                $this->webhookService->clearSubscriptionsCache();

                $this->info("\n✅ Migration completed successfully!");
                $this->info("📊 Migrated: {$migrated} subscriptions");
                $this->info("🗑️  Cache cleared");
                
                return Command::SUCCESS;
            } else {
                DB::rollBack();
                $this->error("\n❌ Migration failed with {$errors} errors. No changes made.");
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\n💥 Migration failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Validate subscription data.
     *
     * @param  array  $data
     * @return bool
     */
    protected function validateSubscriptionData(array $data): bool
    {
        $required = ['id', 'model', 'events', 'webhook_url'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->error("❌ Invalid subscription data: missing {$field}");
                return false;
            }
        }

        if (!is_array($data['events']) || empty($data['events'])) {
            $this->error("❌ Invalid subscription data: events must be non-empty array");
            return false;
        }

        if (!filter_var($data['webhook_url'], FILTER_VALIDATE_URL)) {
            $this->error("❌ Invalid subscription data: invalid webhook URL");
            return false;
        }

        return true;
    }
} 