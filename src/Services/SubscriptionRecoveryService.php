<?php

namespace Shortinc\N8nEloquent\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Shortinc\N8nEloquent\Models\WebhookSubscription;
use Carbon\Carbon;

class SubscriptionRecoveryService
{
    /**
     * The webhook service instance.
     *
     * @var WebhookService
     */
    protected WebhookService $webhookService;

    /**
     * Create a new service instance.
     *
     * @param  WebhookService  $webhookService
     */
    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Automatically recover subscriptions after cache clear.
     *
     * @return array Recovery results
     */
    public function autoRecover(): array
    {
        $results = [
            'recovered_from_cache' => 0,
            'recovered_from_backup' => 0,
            'total_recovered' => 0,
            'errors' => [],
        ];

        try {
            // First, try to recover from cache
            $cacheRecovered = $this->webhookService->migrateCacheToDatabase();
            $results['recovered_from_cache'] = $cacheRecovered;

            // If no cache recovery, try backup recovery
            if ($cacheRecovered === 0) {
                $backupRecovered = $this->recoverFromLatestBackup();
                $results['recovered_from_backup'] = $backupRecovered;
            }

            $results['total_recovered'] = $results['recovered_from_cache'] + $results['recovered_from_backup'];

            Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('Automatic subscription recovery completed', $results);

        } catch (\Throwable $e) {
            $results['errors'][] = $e->getMessage();
            
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Automatic subscription recovery failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
        }

        return $results;
    }

    /**
     * Manually sync subscriptions from various sources.
     *
     * @param  array  $sources Sources to sync from ['cache', 'backup', 'file']
     * @return array Sync results
     */
    public function manualSync(array $sources = ['cache', 'backup']): array
    {
        $results = [
            'synced_sources' => [],
            'total_synced' => 0,
            'errors' => [],
        ];

        foreach ($sources as $source) {
            try {
                $synced = match ($source) {
                    'cache' => $this->syncFromCache(),
                    'backup' => $this->syncFromLatestBackup(),
                    'file' => $this->syncFromFile(),
                    default => 0,
                };

                $results['synced_sources'][$source] = $synced;
                $results['total_synced'] += $synced;

            } catch (\Throwable $e) {
                $results['errors'][$source] = $e->getMessage();
                
                Log::channel(config('n8n-eloquent.logging.channel'))
                    ->error("Manual sync from {$source} failed", [
                        'source' => $source,
                        'error' => $e->getMessage(),
                    ]);
            }
        }

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info('Manual subscription sync completed', $results);

        return $results;
    }

    /**
     * Create a backup of current subscriptions.
     *
     * @param  string|null  $name Optional backup name
     * @return string Backup file path
     */
    public function createBackup(?string $name = null): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupName = $name ? "{$name}_{$timestamp}" : "subscriptions_backup_{$timestamp}";
        $filename = "{$backupName}.json";

        // Get all subscriptions
        $subscriptions = WebhookSubscription::all()->map(function ($subscription) {
            return $subscription->toLegacyArray();
        })->toArray();

        // Add metadata
        $backupData = [
            'metadata' => [
                'created_at' => now()->toIso8601String(),
                'version' => '1.0',
                'total_subscriptions' => count($subscriptions),
                'backup_name' => $backupName,
            ],
            'subscriptions' => $subscriptions,
        ];

        // Store backup
        Storage::disk('local')->put("n8n-backups/{$filename}", json_encode($backupData, JSON_PRETTY_PRINT));

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info('Subscription backup created', [
                'filename' => $filename,
                'count' => count($subscriptions),
            ]);

        return "n8n-backups/{$filename}";
    }

    /**
     * Restore subscriptions from a backup file.
     *
     * @param  string  $backupPath Path to backup file
     * @param  bool  $replaceExisting Whether to replace existing subscriptions
     * @return array Restore results
     */
    public function restoreFromBackup(string $backupPath, bool $replaceExisting = false): array
    {
        $results = [
            'restored' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            if (!Storage::disk('local')->exists($backupPath)) {
                throw new \Exception("Backup file not found: {$backupPath}");
            }

            $backupContent = Storage::disk('local')->get($backupPath);
            $backupData = json_decode($backupContent, true);

            if (!$backupData || !isset($backupData['subscriptions'])) {
                throw new \Exception('Invalid backup file format');
            }

            // Clear existing subscriptions if requested
            if ($replaceExisting) {
                WebhookSubscription::query()->delete();
                Log::channel(config('n8n-eloquent.logging.channel'))
                    ->info('Existing subscriptions cleared for restore');
            }

            // Restore subscriptions
            foreach ($backupData['subscriptions'] as $subscriptionData) {
                try {
                    // Check if subscription already exists
                    if (!$replaceExisting && WebhookSubscription::find($subscriptionData['id'])) {
                        $results['skipped']++;
                        continue;
                    }

                    // Create subscription
                    WebhookSubscription::create([
                        'id' => $subscriptionData['id'],
                        'model_class' => $subscriptionData['model'],
                        'events' => $subscriptionData['events'],
                        'webhook_url' => $subscriptionData['webhook_url'],
                        'properties' => $subscriptionData['properties'] ?? [],
                        'active' => $subscriptionData['active'] ?? true,
                        'created_at' => isset($subscriptionData['created_at']) 
                            ? Carbon::parse($subscriptionData['created_at'])
                            : now(),
                    ]);

                    $results['restored']++;

                } catch (\Throwable $e) {
                    $results['errors'][] = "Failed to restore subscription {$subscriptionData['id']}: " . $e->getMessage();
                }
            }

            // Clear cache after restore
            $this->webhookService->clearSubscriptionsCache();

            Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('Subscription restore completed', [
                    'backup_path' => $backupPath,
                    'restored' => $results['restored'],
                    'skipped' => $results['skipped'],
                    'errors' => count($results['errors']),
                ]);

        } catch (\Throwable $e) {
            $results['errors'][] = $e->getMessage();
            
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Subscription restore failed', [
                    'backup_path' => $backupPath,
                    'error' => $e->getMessage(),
                ]);
        }

        return $results;
    }

    /**
     * Export subscriptions to a file.
     *
     * @param  array  $filters Optional filters for export
     * @param  string  $format Export format ('json', 'csv')
     * @return string Export file path
     */
    public function exportSubscriptions(array $filters = [], string $format = 'json'): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "subscriptions_export_{$timestamp}.{$format}";

        // Build query with filters
        $query = WebhookSubscription::query();

        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        if (isset($filters['model_class'])) {
            $query->where('model_class', $filters['model_class']);
        }

        if (isset($filters['has_errors'])) {
            if ($filters['has_errors']) {
                $query->whereNotNull('last_error');
            } else {
                $query->whereNull('last_error');
            }
        }

        if (isset($filters['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['created_after']));
        }

        $subscriptions = $query->get();

        // Export based on format
        $content = match ($format) {
            'json' => $this->exportToJson($subscriptions),
            'csv' => $this->exportToCsv($subscriptions),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };

        // Store export
        Storage::disk('local')->put("n8n-exports/{$filename}", $content);

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info('Subscription export completed', [
                'filename' => $filename,
                'format' => $format,
                'count' => $subscriptions->count(),
                'filters' => $filters,
            ]);

        return "n8n-exports/{$filename}";
    }

    /**
     * Import subscriptions from a file.
     *
     * @param  string  $filePath Path to import file
     * @param  array  $options Import options
     * @return array Import results
     */
    public function importSubscriptions(string $filePath, array $options = []): array
    {
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            if (!Storage::disk('local')->exists($filePath)) {
                throw new \Exception("Import file not found: {$filePath}");
            }

            $content = Storage::disk('local')->get($filePath);
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);

            $subscriptions = match ($extension) {
                'json' => $this->parseJsonImport($content),
                'csv' => $this->parseCsvImport($content),
                default => throw new \InvalidArgumentException("Unsupported import format: {$extension}"),
            };

            $skipExisting = $options['skip_existing'] ?? true;
            $validateData = $options['validate'] ?? true;

            foreach ($subscriptions as $subscriptionData) {
                try {
                    // Validate data if requested
                    if ($validateData && !$this->validateSubscriptionData($subscriptionData)) {
                        $results['errors'][] = "Invalid subscription data: " . json_encode($subscriptionData);
                        continue;
                    }

                    // Check if subscription already exists
                    if ($skipExisting && isset($subscriptionData['id']) && WebhookSubscription::find($subscriptionData['id'])) {
                        $results['skipped']++;
                        continue;
                    }

                    // Create subscription
                    WebhookSubscription::create([
                        'id' => $subscriptionData['id'] ?? null,
                        'model_class' => $subscriptionData['model_class'] ?? $subscriptionData['model'],
                        'events' => $subscriptionData['events'],
                        'webhook_url' => $subscriptionData['webhook_url'],
                        'properties' => $subscriptionData['properties'] ?? [],
                        'active' => $subscriptionData['active'] ?? true,
                    ]);

                    $results['imported']++;

                } catch (\Throwable $e) {
                    $results['errors'][] = "Failed to import subscription: " . $e->getMessage();
                }
            }

            // Clear cache after import
            $this->webhookService->clearSubscriptionsCache();

            Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('Subscription import completed', [
                    'file_path' => $filePath,
                    'imported' => $results['imported'],
                    'skipped' => $results['skipped'],
                    'errors' => count($results['errors']),
                ]);

        } catch (\Throwable $e) {
            $results['errors'][] = $e->getMessage();
            
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Subscription import failed', [
                    'file_path' => $filePath,
                    'error' => $e->getMessage(),
                ]);
        }

        return $results;
    }

    /**
     * Get list of available backups.
     *
     * @return array List of backup files with metadata
     */
    public function getAvailableBackups(): array
    {
        $backups = [];
        $files = Storage::disk('local')->files('n8n-backups');

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                try {
                    $content = Storage::disk('local')->get($file);
                    $data = json_decode($content, true);
                    
                    $backups[] = [
                        'path' => $file,
                        'filename' => basename($file),
                        'size' => Storage::disk('local')->size($file),
                        'created_at' => $data['metadata']['created_at'] ?? null,
                        'subscription_count' => $data['metadata']['total_subscriptions'] ?? 0,
                        'backup_name' => $data['metadata']['backup_name'] ?? basename($file, '.json'),
                    ];
                } catch (\Throwable $e) {
                    // Skip invalid backup files
                    continue;
                }
            }
        }

        // Sort by creation date (newest first)
        usort($backups, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        return $backups;
    }

    /**
     * Clean up old backups.
     *
     * @param  int  $keepDays Number of days to keep backups
     * @return int Number of backups deleted
     */
    public function cleanupOldBackups(int $keepDays = 30): int
    {
        $deleted = 0;
        $cutoffDate = Carbon::now()->subDays($keepDays);
        $backups = $this->getAvailableBackups();

        foreach ($backups as $backup) {
            if ($backup['created_at'] && Carbon::parse($backup['created_at'])->lt($cutoffDate)) {
                Storage::disk('local')->delete($backup['path']);
                $deleted++;
            }
        }

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info('Old backups cleaned up', [
                'deleted' => $deleted,
                'keep_days' => $keepDays,
            ]);

        return $deleted;
    }

    /**
     * Sync subscriptions from cache.
     *
     * @return int Number of subscriptions synced
     */
    protected function syncFromCache(): int
    {
        return $this->webhookService->migrateCacheToDatabase();
    }

    /**
     * Sync subscriptions from latest backup.
     *
     * @return int Number of subscriptions synced
     */
    protected function syncFromLatestBackup(): int
    {
        $backups = $this->getAvailableBackups();
        
        if (empty($backups)) {
            return 0;
        }

        $latestBackup = $backups[0];
        $results = $this->restoreFromBackup($latestBackup['path'], false);
        
        return $results['restored'];
    }

    /**
     * Sync subscriptions from file.
     *
     * @return int Number of subscriptions synced
     */
    protected function syncFromFile(): int
    {
        // This would be implemented based on specific file requirements
        // For now, return 0 as it requires user input for file path
        return 0;
    }

    /**
     * Recover from latest backup.
     *
     * @return int Number of subscriptions recovered
     */
    protected function recoverFromLatestBackup(): int
    {
        return $this->syncFromLatestBackup();
    }

    /**
     * Export subscriptions to JSON format.
     *
     * @param  \Illuminate\Support\Collection  $subscriptions
     * @return string JSON content
     */
    protected function exportToJson($subscriptions): string
    {
        $data = [
            'metadata' => [
                'exported_at' => now()->toIso8601String(),
                'version' => '1.0',
                'total_subscriptions' => $subscriptions->count(),
            ],
            'subscriptions' => $subscriptions->map(function ($subscription) {
                return $subscription->toLegacyArray();
            })->toArray(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Export subscriptions to CSV format.
     *
     * @param  \Illuminate\Support\Collection  $subscriptions
     * @return string CSV content
     */
    protected function exportToCsv($subscriptions): string
    {
        $csv = "id,model_class,events,webhook_url,properties,active,created_at,updated_at\n";
        
        foreach ($subscriptions as $subscription) {
            $csv .= sprintf(
                "%s,%s,\"%s\",%s,\"%s\",%s,%s,%s\n",
                $subscription->id,
                $subscription->model_class,
                implode(';', $subscription->events),
                $subscription->webhook_url,
                json_encode($subscription->properties ?? []),
                $subscription->active ? 'true' : 'false',
                $subscription->created_at->toIso8601String(),
                $subscription->updated_at->toIso8601String()
            );
        }

        return $csv;
    }

    /**
     * Parse JSON import content.
     *
     * @param  string  $content
     * @return array
     */
    protected function parseJsonImport(string $content): array
    {
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new \Exception('Invalid JSON format');
        }

        // Handle both direct array and metadata format
        if (isset($data['subscriptions'])) {
            return $data['subscriptions'];
        }

        return $data;
    }

    /**
     * Parse CSV import content.
     *
     * @param  string  $content
     * @return array
     */
    protected function parseCsvImport(string $content): array
    {
        $lines = explode("\n", trim($content));
        $headers = str_getcsv(array_shift($lines));
        $subscriptions = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $values = str_getcsv($line);
            $subscription = array_combine($headers, $values);
            
            // Convert CSV data to proper format
            $subscription['events'] = explode(';', $subscription['events']);
            $subscription['properties'] = json_decode($subscription['properties'], true) ?? [];
            $subscription['active'] = $subscription['active'] === 'true';
            
            $subscriptions[] = $subscription;
        }

        return $subscriptions;
    }

    /**
     * Validate subscription data.
     *
     * @param  array  $data
     * @return bool
     */
    protected function validateSubscriptionData(array $data): bool
    {
        $required = ['model_class', 'events', 'webhook_url'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }

        // Validate events array
        if (!is_array($data['events']) || empty($data['events'])) {
            return false;
        }

        // Validate webhook URL
        if (!filter_var($data['webhook_url'], FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }
} 