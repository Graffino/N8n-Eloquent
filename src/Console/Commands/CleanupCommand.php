<?php

namespace Shortinc\N8nEloquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Shortinc\N8nEloquent\Models\WebhookSubscription;
use Shortinc\N8nEloquent\Services\WebhookService;
use Shortinc\N8nEloquent\Services\SubscriptionRecoveryService;
use Carbon\Carbon;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:cleanup 
                            {--type=all : Type of cleanup (inactive, errors, never-triggered, all)}
                            {--dry-run : Show what would be cleaned without making changes}
                            {--force : Skip confirmation prompts}
                            {--batch-size= : Number of records to process in each batch}
                            {--archive : Archive records instead of deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old, inactive, or problematic webhook subscriptions';

    /**
     * The webhook service instance.
     *
     * @var WebhookService
     */
    protected WebhookService $webhookService;

    /**
     * The recovery service instance.
     *
     * @var SubscriptionRecoveryService
     */
    protected SubscriptionRecoveryService $recoveryService;

    /**
     * Create a new command instance.
     *
     * @param  WebhookService  $webhookService
     * @param  SubscriptionRecoveryService  $recoveryService
     */
    public function __construct(WebhookService $webhookService, SubscriptionRecoveryService $recoveryService)
    {
        parent::__construct();
        $this->webhookService = $webhookService;
        $this->recoveryService = $recoveryService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $archive = $this->option('archive');

        $this->info('🧹 Starting subscription cleanup...');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Create backup before cleanup if not dry run
            if (!$dryRun && config('n8n-eloquent.webhooks.backup.enabled')) {
                $this->info('💾 Creating backup before cleanup...');
                $backupPath = $this->recoveryService->createBackup('pre_cleanup');
                $this->line("Backup created: {$backupPath}");
            }

            $results = match ($type) {
                'inactive' => $this->cleanupInactive($dryRun, $force, $archive),
                'errors' => $this->cleanupWithErrors($dryRun, $force, $archive),
                'never-triggered' => $this->cleanupNeverTriggered($dryRun, $force, $archive),
                'all' => $this->cleanupAll($dryRun, $force, $archive),
                default => $this->handleInvalidType($type),
            };

            if ($results === false) {
                return Command::FAILURE;
            }

            $this->displayResults($results, $dryRun, $archive);

            // Clear cache after cleanup
            if (!$dryRun && $results['total_processed'] > 0) {
                $this->webhookService->clearSubscriptionsCache();
                $this->line('🗑️  Cache cleared');
            }

            $this->info('✅ Cleanup completed');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
            
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Subscription cleanup failed', [
                    'type' => $type,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Clean up inactive subscriptions.
     *
     * @param  bool  $dryRun
     * @param  bool  $force
     * @param  bool  $archive
     * @return array
     */
    protected function cleanupInactive(bool $dryRun, bool $force, bool $archive): array
    {
        $days = config('n8n-eloquent.webhooks.cleanup.inactive_days', 30);
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("🔍 Finding inactive subscriptions older than {$days} days...");

        $query = WebhookSubscription::where('active', false)
            ->where('updated_at', '<', $cutoffDate);

        return $this->processCleanup($query, 'inactive', $dryRun, $force, $archive);
    }

    /**
     * Clean up subscriptions with persistent errors.
     *
     * @param  bool  $dryRun
     * @param  bool  $force
     * @param  bool  $archive
     * @return array
     */
    protected function cleanupWithErrors(bool $dryRun, bool $force, bool $archive): array
    {
        $days = config('n8n-eloquent.webhooks.cleanup.error_days', 7);
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("🔍 Finding subscriptions with errors older than {$days} days...");

        $query = WebhookSubscription::whereNotNull('last_error')
            ->where('updated_at', '<', $cutoffDate);

        return $this->processCleanup($query, 'with errors', $dryRun, $force, $archive);
    }

    /**
     * Clean up subscriptions that have never been triggered.
     *
     * @param  bool  $dryRun
     * @param  bool  $force
     * @param  bool  $archive
     * @return array
     */
    protected function cleanupNeverTriggered(bool $dryRun, bool $force, bool $archive): array
    {
        $days = config('n8n-eloquent.webhooks.cleanup.never_triggered_days', 14);
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("🔍 Finding subscriptions never triggered for {$days} days...");

        $query = WebhookSubscription::whereNull('last_triggered_at')
            ->where('created_at', '<', $cutoffDate);

        return $this->processCleanup($query, 'never triggered', $dryRun, $force, $archive);
    }

    /**
     * Clean up all types of problematic subscriptions.
     *
     * @param  bool  $dryRun
     * @param  bool  $force
     * @param  bool  $archive
     * @return array
     */
    protected function cleanupAll(bool $dryRun, bool $force, bool $archive): array
    {
        $results = [
            'total_processed' => 0,
            'total_archived' => 0,
            'total_deleted' => 0,
            'by_type' => [],
        ];

        // Clean up each type
        $types = ['inactive', 'errors', 'never-triggered'];
        
        foreach ($types as $type) {
            $typeResults = match ($type) {
                'inactive' => $this->cleanupInactive($dryRun, true, $archive), // Force = true for batch operation
                'errors' => $this->cleanupWithErrors($dryRun, true, $archive),
                'never-triggered' => $this->cleanupNeverTriggered($dryRun, true, $archive),
            };

            $results['by_type'][$type] = $typeResults;
            $results['total_processed'] += $typeResults['total_processed'];
            $results['total_archived'] += $typeResults['total_archived'];
            $results['total_deleted'] += $typeResults['total_deleted'];
        }

        return $results;
    }

    /**
     * Process cleanup for a given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @param  bool  $dryRun
     * @param  bool  $force
     * @param  bool  $archive
     * @return array
     */
    protected function processCleanup($query, string $type, bool $dryRun, bool $force, bool $archive): array
    {
        $count = $query->count();
        
        if ($count === 0) {
            $this->line("No {$type} subscriptions found for cleanup.");
            return [
                'total_processed' => 0,
                'total_archived' => 0,
                'total_deleted' => 0,
            ];
        }

        $this->line("Found {$count} {$type} subscription(s) for cleanup.");

        if ($dryRun) {
            // Show what would be processed
            $subscriptions = $query->limit(10)->get();
            $this->displayPreview($subscriptions, $type);
            
            return [
                'total_processed' => $count,
                'total_archived' => $archive ? $count : 0,
                'total_deleted' => $archive ? 0 : $count,
            ];
        }

        // Confirm action unless forced
        if (!$force) {
            $action = $archive ? 'archive' : 'delete';
            if (!$this->confirm("Are you sure you want to {$action} {$count} {$type} subscription(s)?")) {
                $this->info('Operation cancelled.');
                return [
                    'total_processed' => 0,
                    'total_archived' => 0,
                    'total_deleted' => 0,
                ];
            }
        }

        // Process in batches
        $batchSize = $this->option('batch-size') ?? config('n8n-eloquent.webhooks.cleanup.batch_size', 100);
        $processed = 0;
        $archived = 0;
        $deleted = 0;

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $query->chunk($batchSize, function ($subscriptions) use (&$processed, &$archived, &$deleted, $archive, $progressBar) {
            foreach ($subscriptions as $subscription) {
                try {
                    if ($archive && config('n8n-eloquent.webhooks.archiving.enabled')) {
                        // Archive the subscription (implementation would depend on archiving strategy)
                        $this->archiveSubscription($subscription);
                        $archived++;
                    } else {
                        // Delete the subscription
                        $subscription->forceDelete();
                        $deleted++;
                    }
                    
                    $processed++;
                    $progressBar->advance();

                } catch (\Throwable $e) {
                    Log::channel(config('n8n-eloquent.logging.channel'))
                        ->error('Failed to process subscription during cleanup', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                }
            }
        });

        $progressBar->finish();
        $this->newLine();

        return [
            'total_processed' => $processed,
            'total_archived' => $archived,
            'total_deleted' => $deleted,
        ];
    }

    /**
     * Archive a subscription.
     *
     * @param  WebhookSubscription  $subscription
     * @return void
     */
    protected function archiveSubscription(WebhookSubscription $subscription): void
    {
        // This is a placeholder for archiving functionality
        // In a real implementation, you might:
        // 1. Move to an archive table
        // 2. Export to a file
        // 3. Compress and store
        
        // For now, we'll just soft delete and mark as archived
        $subscription->update(['archived_at' => now()]);
        $subscription->delete();
    }

    /**
     * Display preview of subscriptions to be processed.
     *
     * @param  \Illuminate\Support\Collection  $subscriptions
     * @param  string  $type
     * @return void
     */
    protected function displayPreview($subscriptions, string $type): void
    {
        $this->line("\nPreview of {$type} subscriptions to be processed:");
        
        $headers = ['ID', 'Model', 'Active', 'Last Triggered', 'Created'];
        $rows = [];

        foreach ($subscriptions as $subscription) {
            $rows[] = [
                substr($subscription->id, 0, 8) . '...',
                class_basename($subscription->model_class),
                $subscription->active ? 'Yes' : 'No',
                $subscription->last_triggered_at?->diffForHumans() ?? 'Never',
                $subscription->created_at->diffForHumans(),
            ];
        }

        $this->table($headers, $rows);
        
        if ($subscriptions->count() < $subscriptions->count()) {
            $this->line('... and more');
        }
    }

    /**
     * Display cleanup results.
     *
     * @param  array  $results
     * @param  bool  $dryRun
     * @param  bool  $archive
     * @return void
     */
    protected function displayResults(array $results, bool $dryRun, bool $archive): void
    {
        $this->newLine();
        
        if ($dryRun) {
            $this->info('📊 Dry Run Results:');
            $action = $archive ? 'archived' : 'deleted';
            $this->line("  • Would be {$action}: {$results['total_processed']}");
        } else {
            $this->info('📊 Cleanup Results:');
            $this->line("  • Total processed: {$results['total_processed']}");
            
            if ($archive) {
                $this->line("  • Archived: {$results['total_archived']}");
            } else {
                $this->line("  • Deleted: {$results['total_deleted']}");
            }
        }

        // Show breakdown by type if available
        if (isset($results['by_type'])) {
            $this->line("\nBreakdown by type:");
            foreach ($results['by_type'] as $type => $typeResults) {
                $this->line("  • {$type}: {$typeResults['total_processed']}");
            }
        }
    }

    /**
     * Handle invalid cleanup type.
     *
     * @param  string  $type
     * @return false
     */
    protected function handleInvalidType(string $type): bool
    {
        $this->error("❌ Invalid cleanup type: {$type}");
        $this->line('Available types: inactive, errors, never-triggered, all');
        return false;
    }
} 