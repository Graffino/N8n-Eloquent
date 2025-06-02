<?php

namespace N8n\Eloquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use N8n\Eloquent\Models\WebhookSubscription;
use N8n\Eloquent\Services\WebhookService;
use Carbon\Carbon;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:health-check 
                            {--alert : Send alerts for critical issues}
                            {--fix : Attempt to fix common issues automatically}
                            {--detailed : Show detailed health information}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of webhook subscriptions and report issues';

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
        $this->info('🔍 Starting webhook subscription health check...');

        try {
            // Get overall statistics
            $stats = $this->webhookService->getWebhookStats();
            $healthStatus = $this->calculateOverallHealth($stats);

            // Display overall health
            $this->displayOverallHealth($healthStatus, $stats);

            // Get detailed health information if requested
            if ($this->option('detailed')) {
                $this->displayDetailedHealth();
            }

            // Check for critical issues
            $criticalIssues = $this->identifyCriticalIssues($stats);
            
            if (!empty($criticalIssues)) {
                $this->displayCriticalIssues($criticalIssues);
                
                // Send alerts if requested
                if ($this->option('alert')) {
                    $this->sendAlerts($criticalIssues);
                }
                
                // Attempt fixes if requested
                if ($this->option('fix')) {
                    $this->attemptFixes($criticalIssues);
                }
            }

            // Display recommendations
            $recommendations = $this->getRecommendations($stats);
            if (!empty($recommendations)) {
                $this->displayRecommendations($recommendations);
            }

            $this->info('✅ Health check completed');
            
            return $healthStatus === 'critical' ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Health check failed: ' . $e->getMessage());
            
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Health check command failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Calculate overall health status.
     *
     * @param  array  $stats
     * @return string
     */
    protected function calculateOverallHealth(array $stats): string
    {
        $total = $stats['total_subscriptions'];
        $active = $stats['active_subscriptions'];
        $withErrors = $stats['subscriptions_with_errors'];
        $stale = $stats['stale_subscriptions'];

        if ($total === 0) {
            return 'no_subscriptions';
        }

        $activePercentage = ($active / $total) * 100;
        $errorPercentage = ($withErrors / $total) * 100;
        $stalePercentage = ($stale / $total) * 100;

        if ($errorPercentage > 20 || $stalePercentage > 50) {
            return 'critical';
        }

        if ($errorPercentage > 10 || $stalePercentage > 30 || $activePercentage < 80) {
            return 'warning';
        }

        if ($activePercentage >= 95 && $errorPercentage < 5) {
            return 'excellent';
        }

        return 'good';
    }

    /**
     * Display overall health status.
     *
     * @param  string  $healthStatus
     * @param  array  $stats
     * @return void
     */
    protected function displayOverallHealth(string $healthStatus, array $stats): void
    {
        $statusEmoji = match ($healthStatus) {
            'excellent' => '🟢',
            'good' => '🟡',
            'warning' => '🟠',
            'critical' => '🔴',
            'no_subscriptions' => '⚪',
            default => '❓',
        };

        $this->info("\n📊 Overall Health Status: {$statusEmoji} " . strtoupper($healthStatus));

        if ($this->option('format') === 'json') {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Subscriptions', $stats['total_subscriptions']],
                ['Active Subscriptions', $stats['active_subscriptions']],
                ['Inactive Subscriptions', $stats['inactive_subscriptions']],
                ['Subscriptions with Errors', $stats['subscriptions_with_errors']],
                ['Stale Subscriptions', $stats['stale_subscriptions']],
                ['Total Triggers', number_format($stats['total_triggers'])],
            ]
        );
    }

    /**
     * Display detailed health information.
     *
     * @return void
     */
    protected function displayDetailedHealth(): void
    {
        $this->info("\n🔍 Detailed Health Information:");

        // Get subscriptions with issues
        $problematicSubscriptions = WebhookSubscription::where(function ($query) {
            $query->where('active', false)
                  ->orWhereNotNull('last_error')
                  ->orWhere(function ($subQuery) {
                      $subQuery->whereNull('last_triggered_at')
                               ->where('created_at', '<', Carbon::now()->subHours(24));
                  })
                  ->orWhere('last_triggered_at', '<', Carbon::now()->subHours(24));
        })->get();

        if ($problematicSubscriptions->isEmpty()) {
            $this->info('✅ No problematic subscriptions found');
            return;
        }

        $rows = [];
        foreach ($problematicSubscriptions as $subscription) {
            $issues = $this->getSubscriptionIssues($subscription);
            $issueTypes = collect($issues)->pluck('type')->implode(', ');
            
            $rows[] = [
                substr($subscription->id, 0, 8) . '...',
                class_basename($subscription->model_class),
                $subscription->active ? 'Yes' : 'No',
                $subscription->trigger_count,
                $subscription->last_triggered_at?->diffForHumans() ?? 'Never',
                $issueTypes ?: 'None',
            ];
        }

        $this->table(
            ['ID', 'Model', 'Active', 'Triggers', 'Last Triggered', 'Issues'],
            $rows
        );
    }

    /**
     * Identify critical issues that need immediate attention.
     *
     * @param  array  $stats
     * @return array
     */
    protected function identifyCriticalIssues(array $stats): array
    {
        $issues = [];

        // High error rate
        if ($stats['total_subscriptions'] > 0) {
            $errorRate = ($stats['subscriptions_with_errors'] / $stats['total_subscriptions']) * 100;
            if ($errorRate > 20) {
                $issues[] = [
                    'type' => 'high_error_rate',
                    'severity' => 'critical',
                    'message' => "High error rate: {$errorRate}% of subscriptions have errors",
                    'count' => $stats['subscriptions_with_errors'],
                ];
            }
        }

        // Many stale subscriptions
        if ($stats['total_subscriptions'] > 0) {
            $staleRate = ($stats['stale_subscriptions'] / $stats['total_subscriptions']) * 100;
            if ($staleRate > 50) {
                $issues[] = [
                    'type' => 'many_stale_subscriptions',
                    'severity' => 'warning',
                    'message' => "Many stale subscriptions: {$staleRate}% haven't been triggered recently",
                    'count' => $stats['stale_subscriptions'],
                ];
            }
        }

        // Low activity
        if ($stats['total_subscriptions'] > 0 && $stats['total_triggers'] === 0) {
            $issues[] = [
                'type' => 'no_activity',
                'severity' => 'warning',
                'message' => 'No webhook triggers recorded - subscriptions may not be working',
                'count' => $stats['total_subscriptions'],
            ];
        }

        // Many inactive subscriptions
        if ($stats['inactive_subscriptions'] > $stats['active_subscriptions']) {
            $issues[] = [
                'type' => 'many_inactive',
                'severity' => 'info',
                'message' => 'More inactive than active subscriptions - consider cleanup',
                'count' => $stats['inactive_subscriptions'],
            ];
        }

        return $issues;
    }

    /**
     * Display critical issues.
     *
     * @param  array  $issues
     * @return void
     */
    protected function displayCriticalIssues(array $issues): void
    {
        $this->warn("\n⚠️  Critical Issues Found:");

        foreach ($issues as $issue) {
            $emoji = match ($issue['severity']) {
                'critical' => '🔴',
                'warning' => '🟠',
                'info' => '🔵',
                default => '❓',
            };

            $this->line("{$emoji} {$issue['message']}");
        }
    }

    /**
     * Send alerts for critical issues.
     *
     * @param  array  $issues
     * @return void
     */
    protected function sendAlerts(array $issues): void
    {
        $this->info("\n📧 Sending alerts for critical issues...");

        foreach ($issues as $issue) {
            if ($issue['severity'] === 'critical') {
                Log::channel(config('n8n-eloquent.logging.channel'))
                    ->critical('Webhook subscription critical issue detected', [
                        'type' => $issue['type'],
                        'message' => $issue['message'],
                        'count' => $issue['count'],
                        'timestamp' => now()->toIso8601String(),
                    ]);

                $this->warn("🚨 CRITICAL ALERT: {$issue['message']}");
            }
        }
    }

    /**
     * Attempt to fix common issues automatically.
     *
     * @param  array  $issues
     * @return void
     */
    protected function attemptFixes(array $issues): void
    {
        $this->info("\n🔧 Attempting to fix issues automatically...");

        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'many_inactive':
                    if ($this->confirm('Remove inactive subscriptions older than 30 days?')) {
                        $deleted = WebhookSubscription::where('active', false)
                            ->where('updated_at', '<', Carbon::now()->subDays(30))
                            ->forceDelete();
                        
                        $this->info("✅ Removed {$deleted} old inactive subscriptions");
                    }
                    break;

                case 'high_error_rate':
                    if ($this->confirm('Deactivate subscriptions with persistent errors?')) {
                        $deactivated = WebhookSubscription::whereNotNull('last_error')
                            ->where('updated_at', '<', Carbon::now()->subHours(24))
                            ->update(['active' => false]);
                        
                        $this->info("✅ Deactivated {$deactivated} subscriptions with persistent errors");
                    }
                    break;
            }
        }
    }

    /**
     * Get recommendations for improving health.
     *
     * @param  array  $stats
     * @return array
     */
    protected function getRecommendations(array $stats): array
    {
        $recommendations = [];

        if ($stats['subscriptions_with_errors'] > 0) {
            $recommendations[] = "Review and fix {$stats['subscriptions_with_errors']} subscription(s) with errors";
        }

        if ($stats['stale_subscriptions'] > $stats['active_subscriptions'] * 0.3) {
            $recommendations[] = "Investigate why many subscriptions haven't been triggered recently";
        }

        if ($stats['inactive_subscriptions'] > 0) {
            $recommendations[] = "Consider cleaning up {$stats['inactive_subscriptions']} inactive subscription(s)";
        }

        if ($stats['total_subscriptions'] === 0) {
            $recommendations[] = "Create webhook subscriptions to start monitoring model events";
        }

        return $recommendations;
    }

    /**
     * Display recommendations.
     *
     * @param  array  $recommendations
     * @return void
     */
    protected function displayRecommendations(array $recommendations): void
    {
        $this->info("\n💡 Recommendations:");

        foreach ($recommendations as $recommendation) {
            $this->line("• {$recommendation}");
        }
    }

    /**
     * Get issues for a specific subscription.
     *
     * @param  WebhookSubscription  $subscription
     * @return array
     */
    protected function getSubscriptionIssues(WebhookSubscription $subscription): array
    {
        $issues = [];

        if (!$subscription->active) {
            $issues[] = ['type' => 'inactive'];
        }

        if ($subscription->last_error) {
            $issues[] = ['type' => 'error'];
        }

        if (!$subscription->last_triggered_at) {
            $hoursSinceCreation = $subscription->created_at->diffInHours(now());
            if ($hoursSinceCreation > 24) {
                $issues[] = ['type' => 'never_triggered'];
            }
        } elseif ($subscription->last_triggered_at->diffInHours(now()) > 24) {
            $issues[] = ['type' => 'stale'];
        }

        return $issues;
    }
} 