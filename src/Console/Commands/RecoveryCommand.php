<?php

namespace N8n\Eloquent\Console\Commands;

use Illuminate\Console\Command;
use N8n\Eloquent\Services\SubscriptionRecoveryService;

class RecoveryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:recovery 
                            {action : Action to perform (auto-recover, backup, restore, export, import, list-backups, cleanup)}
                            {--file= : File path for restore/import operations}
                            {--name= : Name for backup operation}
                            {--format=json : Format for export (json, csv)}
                            {--replace : Replace existing subscriptions during restore}
                            {--days=30 : Number of days to keep backups during cleanup}
                            {--filter=* : Filters for export (active=true, model_class=App\\Models\\User, etc.)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage subscription recovery, backup, and import/export operations';

    /**
     * The recovery service instance.
     *
     * @var SubscriptionRecoveryService
     */
    protected SubscriptionRecoveryService $recoveryService;

    /**
     * Create a new command instance.
     *
     * @param  SubscriptionRecoveryService  $recoveryService
     */
    public function __construct(SubscriptionRecoveryService $recoveryService)
    {
        parent::__construct();
        $this->recoveryService = $recoveryService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        try {
            return match ($action) {
                'auto-recover' => $this->handleAutoRecover(),
                'backup' => $this->handleBackup(),
                'restore' => $this->handleRestore(),
                'export' => $this->handleExport(),
                'import' => $this->handleImport(),
                'list-backups' => $this->handleListBackups(),
                'cleanup' => $this->handleCleanup(),
                default => $this->handleInvalidAction($action),
            };
        } catch (\Throwable $e) {
            $this->error("❌ Operation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Handle auto-recovery operation.
     *
     * @return int
     */
    protected function handleAutoRecover(): int
    {
        $this->info('🔄 Starting automatic subscription recovery...');

        $results = $this->recoveryService->autoRecover();

        if (!empty($results['errors'])) {
            $this->error('❌ Recovery completed with errors:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        $this->info("✅ Recovery completed:");
        $this->line("  • Recovered from cache: {$results['recovered_from_cache']}");
        $this->line("  • Recovered from backup: {$results['recovered_from_backup']}");
        $this->line("  • Total recovered: {$results['total_recovered']}");

        return $results['total_recovered'] > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Handle backup operation.
     *
     * @return int
     */
    protected function handleBackup(): int
    {
        $name = $this->option('name');
        
        $this->info('💾 Creating subscription backup...');

        $backupPath = $this->recoveryService->createBackup($name);

        $this->info("✅ Backup created successfully:");
        $this->line("  • File: {$backupPath}");

        return Command::SUCCESS;
    }

    /**
     * Handle restore operation.
     *
     * @return int
     */
    protected function handleRestore(): int
    {
        $filePath = $this->option('file');
        $replace = $this->option('replace');

        if (!$filePath) {
            $this->error('❌ File path is required for restore operation. Use --file option.');
            return Command::FAILURE;
        }

        $this->info("🔄 Restoring subscriptions from {$filePath}...");

        if ($replace) {
            $this->warn('⚠️  This will replace all existing subscriptions!');
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $results = $this->recoveryService->restoreFromBackup($filePath, $replace);

        if (!empty($results['errors'])) {
            $this->error('❌ Restore completed with errors:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        $this->info("✅ Restore completed:");
        $this->line("  • Restored: {$results['restored']}");
        $this->line("  • Skipped: {$results['skipped']}");

        return empty($results['errors']) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Handle export operation.
     *
     * @return int
     */
    protected function handleExport(): int
    {
        $format = $this->option('format');
        $filters = $this->parseFilters($this->option('filter'));

        $this->info("📤 Exporting subscriptions to {$format} format...");

        if (!empty($filters)) {
            $this->line('Applied filters:');
            foreach ($filters as $key => $value) {
                $this->line("  • {$key}: {$value}");
            }
        }

        $exportPath = $this->recoveryService->exportSubscriptions($filters, $format);

        $this->info("✅ Export completed:");
        $this->line("  • File: {$exportPath}");

        return Command::SUCCESS;
    }

    /**
     * Handle import operation.
     *
     * @return int
     */
    protected function handleImport(): int
    {
        $filePath = $this->option('file');

        if (!$filePath) {
            $this->error('❌ File path is required for import operation. Use --file option.');
            return Command::FAILURE;
        }

        $this->info("📥 Importing subscriptions from {$filePath}...");

        $options = [
            'skip_existing' => true,
            'validate' => true,
        ];

        $results = $this->recoveryService->importSubscriptions($filePath, $options);

        if (!empty($results['errors'])) {
            $this->error('❌ Import completed with errors:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        $this->info("✅ Import completed:");
        $this->line("  • Imported: {$results['imported']}");
        $this->line("  • Skipped: {$results['skipped']}");

        return empty($results['errors']) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Handle list backups operation.
     *
     * @return int
     */
    protected function handleListBackups(): int
    {
        $this->info('📋 Available backups:');

        $backups = $this->recoveryService->getAvailableBackups();

        if (empty($backups)) {
            $this->line('No backups found.');
            return Command::SUCCESS;
        }

        $headers = ['Name', 'Created', 'Size', 'Subscriptions', 'Path'];
        $rows = [];

        foreach ($backups as $backup) {
            $rows[] = [
                $backup['backup_name'],
                $backup['created_at'] ? \Carbon\Carbon::parse($backup['created_at'])->format('Y-m-d H:i:s') : 'Unknown',
                $this->formatBytes($backup['size']),
                $backup['subscription_count'],
                $backup['path'],
            ];
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }

    /**
     * Handle cleanup operation.
     *
     * @return int
     */
    protected function handleCleanup(): int
    {
        $days = (int) $this->option('days');

        $this->info("🧹 Cleaning up backups older than {$days} days...");

        $deleted = $this->recoveryService->cleanupOldBackups($days);

        $this->info("✅ Cleanup completed:");
        $this->line("  • Deleted: {$deleted} backup(s)");

        return Command::SUCCESS;
    }

    /**
     * Handle invalid action.
     *
     * @param  string  $action
     * @return int
     */
    protected function handleInvalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->line('Available actions: auto-recover, backup, restore, export, import, list-backups, cleanup');
        return Command::FAILURE;
    }

    /**
     * Parse filter options.
     *
     * @param  array  $filterOptions
     * @return array
     */
    protected function parseFilters(array $filterOptions): array
    {
        $filters = [];

        foreach ($filterOptions as $filter) {
            if (strpos($filter, '=') !== false) {
                [$key, $value] = explode('=', $filter, 2);
                
                // Convert string values to appropriate types
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    $value = is_float($value) ? (float) $value : (int) $value;
                }
                
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * Format bytes to human readable format.
     *
     * @param  int  $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
} 