<?php

namespace Shortinc\N8nEloquent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Shortinc\N8nEloquent\Services\ModelDiscoveryService;
use Shortinc\N8nEloquent\Services\WebhookService;

class WebhookManagementController extends Controller
{
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
     * Create a new controller instance.
     *
     * @param  \N8n\Eloquent\Services\ModelDiscoveryService  $modelDiscovery
     * @param  \N8n\Eloquent\Services\WebhookService  $webhookService
     * @return void
     */
    public function __construct(
        ModelDiscoveryService $modelDiscovery,
        WebhookService $webhookService
    ) {
        $this->modelDiscovery = $modelDiscovery;
        $this->webhookService = $webhookService;
    }

    /**
     * List all webhook subscriptions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $subscriptions = $this->webhookService->getAllSubscriptions();
            
            if ($request->has('model')) {
                $modelFilter = urldecode($request->input('model'));
                $subscriptions = collect($subscriptions)->filter(function ($subscription) use ($modelFilter) {
                    return $subscription['model'] === $modelFilter;
                })->values();
            }
            
            if ($request->has('event')) {
                $eventFilter = $request->input('event');
                $subscriptions = collect($subscriptions)->filter(function ($subscription) use ($eventFilter) {
                    return in_array($eventFilter, $subscription['events']);
                })->values();
            }
            
            return response()->json([
                'subscriptions' => $subscriptions,
                'total' => count($subscriptions),
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error fetching webhook subscriptions", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error fetching subscriptions: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Get a specific webhook subscription.
     *
     * @param  string  $subscriptionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $subscriptionId)
    {
        try {
            $subscription = $this->webhookService->getSubscription($subscriptionId);
            
            if (!$subscription) {
                return response()->json([
                    'error' => "Subscription with ID {$subscriptionId} not found",
                ], 404);
            }
            
            return response()->json([
                'subscription' => $subscription,
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error fetching webhook subscription {$subscriptionId}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error fetching subscription: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Update a webhook subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $subscriptionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $subscriptionId)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'events' => 'array',
            'events.*' => 'string|in:created,updated,deleted,restored,saving,saved,get,set',
            'webhook_url' => 'url',
            'properties' => 'array',
            'properties.*' => 'string',
            'active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $subscription = $this->webhookService->getSubscription($subscriptionId);
            
            if (!$subscription) {
                return response()->json([
                    'error' => "Subscription with ID {$subscriptionId} not found",
                ], 404);
            }

            // Update the subscription
            $updatedSubscription = $this->webhookService->updateSubscription(
                $subscriptionId,
                $request->only(['events', 'webhook_url', 'properties', 'active'])
            );
            
            return response()->json([
                'message' => 'Webhook subscription updated successfully',
                'subscription' => $updatedSubscription,
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error updating webhook subscription {$subscriptionId}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error updating subscription: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Test a webhook subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $subscriptionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function test(Request $request, string $subscriptionId)
    {
        try {
            $subscription = $this->webhookService->getSubscription($subscriptionId);
            
            if (!$subscription) {
                return response()->json([
                    'error' => "Subscription with ID {$subscriptionId} not found",
                ], 404);
            }

            // Create a test payload
            $testPayload = [
                'event_type' => 'test',
                'model_class' => $subscription['model'],
                'model_key' => 'test-id',
                'model_data' => ['test' => true],
                'event_data' => [],
                'timestamp' => now()->toISOString(),
                'subscription_id' => $subscriptionId,
            ];

            // Send test webhook
            $result = $this->webhookService->sendWebhook(
                $subscription['webhook_url'],
                $testPayload
            );
            
            return response()->json([
                'message' => 'Test webhook sent successfully',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error testing webhook subscription {$subscriptionId}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error testing webhook: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Get webhook statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        try {
            $stats = $this->webhookService->getWebhookStats();
            
            return response()->json([
                'stats' => $stats,
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error fetching webhook statistics", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error fetching statistics: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Bulk operations on webhook subscriptions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulk(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:activate,deactivate,delete',
            'subscription_ids' => 'required|array',
            'subscription_ids.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $action = $request->input('action');
        $subscriptionIds = $request->input('subscription_ids');
        $results = [];

        try {
            foreach ($subscriptionIds as $subscriptionId) {
                try {
                    switch ($action) {
                        case 'activate':
                            $this->webhookService->updateSubscription($subscriptionId, ['active' => true]);
                            $results[$subscriptionId] = 'activated';
                            break;
                        case 'deactivate':
                            $this->webhookService->updateSubscription($subscriptionId, ['active' => false]);
                            $results[$subscriptionId] = 'deactivated';
                            break;
                        case 'delete':
                            $this->webhookService->unsubscribe($subscriptionId);
                            $results[$subscriptionId] = 'deleted';
                            break;
                    }
                } catch (\Throwable $e) {
                    $results[$subscriptionId] = 'error: ' . $e->getMessage();
                }
            }
            
            return response()->json([
                'message' => "Bulk {$action} operation completed",
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error performing bulk webhook operation", [
                    'action' => $action,
                    'subscription_ids' => $subscriptionIds,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error performing bulk operation: {$e->getMessage()}",
            ], 500);
        }
    }
} 