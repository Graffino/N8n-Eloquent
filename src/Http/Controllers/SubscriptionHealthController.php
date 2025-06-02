<?php

namespace N8n\Eloquent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use N8n\Eloquent\Models\WebhookSubscription;
use N8n\Eloquent\Services\WebhookService;
use Carbon\Carbon;

class SubscriptionHealthController extends Controller
{
    /**
     * The webhook service instance.
     *
     * @var WebhookService
     */
    protected WebhookService $webhookService;

    /**
     * Create a new controller instance.
     *
     * @param  WebhookService  $webhookService
     */
    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Get overall subscription health status.
     *
     * @return JsonResponse
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $stats = $this->webhookService->getWebhookStats();
            
            // Calculate health metrics
            $totalSubscriptions = $stats['total_subscriptions'];
            $activeSubscriptions = $stats['active_subscriptions'];
            $subscriptionsWithErrors = $stats['subscriptions_with_errors'];
            $staleSubscriptions = $stats['stale_subscriptions'];
            
            // Determine overall health status
            $healthStatus = $this->calculateHealthStatus(
                $totalSubscriptions,
                $activeSubscriptions,
                $subscriptionsWithErrors,
                $staleSubscriptions
            );
            
            // Get recent activity
            $recentActivity = $this->getRecentActivity();
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'overall_health' => $healthStatus,
                    'statistics' => $stats,
                    'recent_activity' => $recentActivity,
                    'recommendations' => $this->getHealthRecommendations($stats),
                    'last_checked' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Error performing subscription health check', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to perform health check',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed health information for all subscriptions.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function detailedHealth(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->get('per_page', 50), 100);
            $includeHealthy = $request->boolean('include_healthy', false);
            
            $query = WebhookSubscription::query();
            
            // Filter by health status if requested
            if (!$includeHealthy) {
                $query->where(function ($q) {
                    $q->where('active', false)
                      ->orWhereNotNull('last_error')
                      ->orWhere(function ($subQ) {
                          $subQ->whereNull('last_triggered_at')
                               ->where('created_at', '<', Carbon::now()->subHours(24));
                      })
                      ->orWhere('last_triggered_at', '<', Carbon::now()->subHours(24));
                });
            }
            
            $subscriptions = $query->orderBy('created_at', 'desc')
                                  ->paginate($perPage);
            
            $healthData = $subscriptions->getCollection()->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'model_class' => $subscription->model_class,
                    'events' => $subscription->events,
                    'webhook_url' => $subscription->webhook_url,
                    'active' => $subscription->active,
                    'health_status' => $this->getSubscriptionHealthStatus($subscription),
                    'trigger_count' => $subscription->trigger_count,
                    'last_triggered_at' => $subscription->last_triggered_at?->toIso8601String(),
                    'last_error' => $subscription->last_error,
                    'created_at' => $subscription->created_at->toIso8601String(),
                    'issues' => $this->getSubscriptionIssues($subscription),
                ];
            });
            
            return response()->json([
                'status' => 'success',
                'data' => $healthData,
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                    'last_page' => $subscriptions->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Error getting detailed subscription health', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get detailed health information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate a specific subscription.
     *
     * @param  string  $subscriptionId
     * @return JsonResponse
     */
    public function validateSubscription(string $subscriptionId): JsonResponse
    {
        try {
            $subscription = WebhookSubscription::find($subscriptionId);
            
            if (!$subscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscription not found',
                ], 404);
            }
            
            $validationResults = $this->performSubscriptionValidation($subscription);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'subscription_id' => $subscriptionId,
                    'validation_results' => $validationResults,
                    'overall_valid' => $validationResults['is_valid'],
                    'validated_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Error validating subscription', [
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to validate subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription usage analytics.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $days = min($request->get('days', 7), 30);
            $startDate = Carbon::now()->subDays($days);
            
            // Get subscription creation trends
            $creationTrends = WebhookSubscription::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            // Get trigger activity
            $triggerActivity = WebhookSubscription::selectRaw('DATE(last_triggered_at) as date, SUM(trigger_count) as total_triggers')
                ->whereNotNull('last_triggered_at')
                ->where('last_triggered_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            // Get model usage statistics
            $modelUsage = WebhookSubscription::selectRaw('model_class, COUNT(*) as subscription_count, SUM(trigger_count) as total_triggers')
                ->groupBy('model_class')
                ->orderBy('subscription_count', 'desc')
                ->get();
            
            // Get event usage statistics
            $eventUsage = WebhookSubscription::get()
                ->flatMap(function ($subscription) {
                    return collect($subscription->events)->map(function ($event) use ($subscription) {
                        return [
                            'event' => $event,
                            'trigger_count' => $subscription->trigger_count,
                        ];
                    });
                })
                ->groupBy('event')
                ->map(function ($events, $eventName) {
                    return [
                        'event' => $eventName,
                        'subscription_count' => $events->count(),
                        'total_triggers' => $events->sum('trigger_count'),
                    ];
                })
                ->values();
            
            // Get error trends
            $errorTrends = WebhookSubscription::whereNotNull('last_error')
                ->selectRaw('DATE(updated_at) as date, COUNT(*) as error_count')
                ->where('updated_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'period' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => Carbon::now()->toDateString(),
                        'days' => $days,
                    ],
                    'creation_trends' => $creationTrends,
                    'trigger_activity' => $triggerActivity,
                    'model_usage' => $modelUsage,
                    'event_usage' => $eventUsage,
                    'error_trends' => $errorTrends,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('Error generating subscription analytics', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate overall health status.
     *
     * @param  int  $total
     * @param  int  $active
     * @param  int  $withErrors
     * @param  int  $stale
     * @return string
     */
    protected function calculateHealthStatus(int $total, int $active, int $withErrors, int $stale): string
    {
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
     * Get recent activity summary.
     *
     * @return array
     */
    protected function getRecentActivity(): array
    {
        $recentlyCreated = WebhookSubscription::where('created_at', '>=', Carbon::now()->subHours(24))->count();
        $recentlyTriggered = WebhookSubscription::where('last_triggered_at', '>=', Carbon::now()->subHours(24))->count();
        $recentErrors = WebhookSubscription::whereNotNull('last_error')
            ->where('updated_at', '>=', Carbon::now()->subHours(24))
            ->count();
        
        return [
            'subscriptions_created_24h' => $recentlyCreated,
            'subscriptions_triggered_24h' => $recentlyTriggered,
            'errors_24h' => $recentErrors,
        ];
    }

    /**
     * Get health recommendations based on statistics.
     *
     * @param  array  $stats
     * @return array
     */
    protected function getHealthRecommendations(array $stats): array
    {
        $recommendations = [];
        
        if ($stats['subscriptions_with_errors'] > 0) {
            $recommendations[] = [
                'type' => 'error',
                'message' => "You have {$stats['subscriptions_with_errors']} subscription(s) with errors. Review and fix these subscriptions.",
                'action' => 'review_errors',
            ];
        }
        
        if ($stats['stale_subscriptions'] > $stats['active_subscriptions'] * 0.3) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => "Many subscriptions haven't been triggered recently. Consider reviewing your model events.",
                'action' => 'review_stale',
            ];
        }
        
        if ($stats['inactive_subscriptions'] > 0) {
            $recommendations[] = [
                'type' => 'info',
                'message' => "You have {$stats['inactive_subscriptions']} inactive subscription(s). Consider cleaning up unused subscriptions.",
                'action' => 'cleanup_inactive',
            ];
        }
        
        if ($stats['total_subscriptions'] === 0) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'No webhook subscriptions found. Create subscriptions to start receiving model events.',
                'action' => 'create_subscriptions',
            ];
        }
        
        return $recommendations;
    }

    /**
     * Get health status for a specific subscription.
     *
     * @param  WebhookSubscription  $subscription
     * @return string
     */
    protected function getSubscriptionHealthStatus(WebhookSubscription $subscription): string
    {
        if (!$subscription->active) {
            return 'inactive';
        }
        
        if ($subscription->last_error) {
            return 'error';
        }
        
        if (!$subscription->last_triggered_at) {
            $hoursSinceCreation = $subscription->created_at->diffInHours(now());
            if ($hoursSinceCreation > 24) {
                return 'stale';
            }
            return 'pending';
        }
        
        $hoursSinceLastTrigger = $subscription->last_triggered_at->diffInHours(now());
        if ($hoursSinceLastTrigger > 24) {
            return 'stale';
        }
        
        return 'healthy';
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
            $issues[] = [
                'type' => 'inactive',
                'message' => 'Subscription is inactive',
                'severity' => 'warning',
            ];
        }
        
        if ($subscription->last_error) {
            $issues[] = [
                'type' => 'error',
                'message' => $subscription->last_error['message'] ?? 'Unknown error',
                'severity' => 'error',
                'occurred_at' => $subscription->last_error['occurred_at'] ?? null,
            ];
        }
        
        if (!$subscription->last_triggered_at) {
            $hoursSinceCreation = $subscription->created_at->diffInHours(now());
            if ($hoursSinceCreation > 24) {
                $issues[] = [
                    'type' => 'never_triggered',
                    'message' => 'Subscription has never been triggered',
                    'severity' => 'warning',
                ];
            }
        } elseif ($subscription->last_triggered_at->diffInHours(now()) > 24) {
            $issues[] = [
                'type' => 'stale',
                'message' => 'Subscription has not been triggered recently',
                'severity' => 'info',
                'last_triggered' => $subscription->last_triggered_at->toIso8601String(),
            ];
        }
        
        return $issues;
    }

    /**
     * Perform comprehensive validation of a subscription.
     *
     * @param  WebhookSubscription  $subscription
     * @return array
     */
    protected function performSubscriptionValidation(WebhookSubscription $subscription): array
    {
        $results = [
            'is_valid' => true,
            'checks' => [],
        ];
        
        // Check if model class exists
        $modelExists = class_exists($subscription->model_class);
        $results['checks']['model_exists'] = [
            'passed' => $modelExists,
            'message' => $modelExists ? 'Model class exists' : 'Model class not found',
        ];
        
        if (!$modelExists) {
            $results['is_valid'] = false;
        }
        
        // Check if webhook URL is reachable (basic validation)
        $urlValid = filter_var($subscription->webhook_url, FILTER_VALIDATE_URL) !== false;
        $results['checks']['url_valid'] = [
            'passed' => $urlValid,
            'message' => $urlValid ? 'Webhook URL is valid' : 'Webhook URL is invalid',
        ];
        
        if (!$urlValid) {
            $results['is_valid'] = false;
        }
        
        // Check if events are valid
        $validEvents = ['created', 'updated', 'deleted', 'saved', 'saving'];
        $eventsValid = collect($subscription->events)->every(function ($event) use ($validEvents) {
            return in_array($event, $validEvents);
        });
        
        $results['checks']['events_valid'] = [
            'passed' => $eventsValid,
            'message' => $eventsValid ? 'All events are valid' : 'Some events are invalid',
            'invalid_events' => $eventsValid ? [] : collect($subscription->events)->reject(function ($event) use ($validEvents) {
                return in_array($event, $validEvents);
            })->values()->toArray(),
        ];
        
        if (!$eventsValid) {
            $results['is_valid'] = false;
        }
        
        // Check subscription activity
        $hasActivity = $subscription->trigger_count > 0;
        $results['checks']['has_activity'] = [
            'passed' => $hasActivity,
            'message' => $hasActivity ? 'Subscription has activity' : 'Subscription has no activity',
            'trigger_count' => $subscription->trigger_count,
        ];
        
        // Check for recent errors
        $hasRecentErrors = $subscription->last_error !== null;
        $results['checks']['no_recent_errors'] = [
            'passed' => !$hasRecentErrors,
            'message' => $hasRecentErrors ? 'Subscription has recent errors' : 'No recent errors',
            'last_error' => $subscription->last_error,
        ];
        
        return $results;
    }
} 