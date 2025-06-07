<?php

namespace N8n\Eloquent\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use N8n\Eloquent\Models\WebhookSubscription;

class WebhookService
{
    /**
     * Cache key for storing webhook subscriptions.
     *
     * @var string
     */
    protected string $cacheKey = 'n8n_webhook_subscriptions';

    /**
     * Cache TTL in seconds (1 hour).
     *
     * @var int
     */
    protected int $cacheTtl = 3600;

    /**
     * Subscribe to model events.
     *
     * @param  string  $modelClass
     * @param  array  $events
     * @param  string  $webhookUrl
     * @param  array  $properties
     * @return array
     */
    public function subscribe(
        string $modelClass,
        array $events,
        string $webhookUrl,
        array $properties = []
    ): array {
        // Create the subscription in database
        $subscription = WebhookSubscription::create([
            'model_class' => $modelClass,
            'events' => $events,
            'webhook_url' => $webhookUrl,
            'properties' => $properties,
            'active' => true,
        ]);

        // Clear cache to force refresh
        $this->clearSubscriptionsCache();

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info("Created webhook subscription for model {$modelClass}", [
                'subscription_id' => $subscription->id,
                'events' => $events,
                'webhook_url' => $webhookUrl,
            ]);

        return $subscription->toLegacyArray();
    }

    /**
     * Unsubscribe from model events.
     *
     * @param  string  $subscriptionId
     * @return bool
     */
    public function unsubscribe(string $subscriptionId): bool
    {
        $subscription = WebhookSubscription::find($subscriptionId);

        if (!$subscription) {
            return false;
        }

        // Soft delete the subscription
        $subscription->delete();

        // Clear cache to force refresh
        $this->clearSubscriptionsCache();

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info("Deleted webhook subscription", [
                'subscription_id' => $subscriptionId,
                'model' => $subscription->model_class,
            ]);

        return true;
    }

    /**
     * Get all webhook subscriptions.
     *
     * @return array
     */
    public function getSubscriptions(): array
    {
        return Cache::remember($this->cacheKey, $this->cacheTtl, function () {
            return WebhookSubscription::active()
                ->get()
                ->keyBy('id')
                ->map(function ($subscription) {
                    return $subscription->toLegacyArray();
                })
                ->toArray();
        });
    }

    /**
     * Get webhook subscriptions for a specific model and event.
     *
     * @param  string  $modelClass
     * @param  string  $event
     * @return array
     */
    public function getSubscriptionsForModelEvent(string $modelClass, string $event): array
    {
        // Use database query for better performance on specific lookups
        return WebhookSubscription::active()
            ->forModelEvent($modelClass, $event)
            ->get()
            ->map(function ($subscription) {
                return $subscription->toLegacyArray();
            })
            ->toArray();
    }

    /**
     * Save webhook subscriptions (legacy method for backward compatibility).
     *
     * @param  array  $subscriptions
     * @return void
     * @deprecated Use database methods instead
     */
    protected function saveSubscriptions(array $subscriptions): void
    {
        // This method is kept for backward compatibility but now clears cache
        $this->clearSubscriptionsCache();
    }

    /**
     * Trigger a webhook for a model event.
     *
     * @param  string  $modelClass
     * @param  string  $event
     * @param  mixed  $model
     * @param  array  $additionalPayload
     * @return void
     */
    public function triggerWebhook(string $modelClass, string $event, $model, array $additionalPayload = []): void
    {
        // Get subscriptions for this model and event directly from database
        $subscriptions = WebhookSubscription::active()
            ->forModelEvent($modelClass, $event)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        // Prepare the payload
        $payload = array_merge([
            'event' => $event,
            'model' => $modelClass,
            'timestamp' => now()->toIso8601String(),
            'data' => $model->toArray(),
            'metadata' => [
                'trigger_chain' => $additionalPayload['metadata']['trigger_chain'] ?? [],
                'source_event' => $event,
                'source_model' => $modelClass,
                'source_id' => $model->getKey(),
                'trigger_depth' => isset($additionalPayload['metadata']['trigger_depth']) 
                    ? $additionalPayload['metadata']['trigger_depth'] + 1 
                    : 1
            ]
        ], $additionalPayload);

        // Check for infinite loop
        $maxTriggerDepth = config('n8n-eloquent.events.max_trigger_depth', 5);
        if ($payload['metadata']['trigger_depth'] > $maxTriggerDepth) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->warning("Maximum trigger depth reached for {$modelClass} {$event} event", [
                    'trigger_chain' => $payload['metadata']['trigger_chain'],
                    'max_depth' => $maxTriggerDepth
                ]);
            return;
        }

        // Add current event to trigger chain
        $payload['metadata']['trigger_chain'][] = [
            'event' => $event,
            'model' => $modelClass,
            'id' => $model->getKey(),
            'depth' => $payload['metadata']['trigger_depth']
        ];

        // Send the webhook to each subscription
        foreach ($subscriptions as $subscription) {
            $this->sendWebhookRequest(
                $subscription->webhook_url,
                $payload,
                $subscription
            );
        }
    }

    /**
     * Send a webhook request.
     *
     * @param  string  $url
     * @param  array  $payload
     * @param  WebhookSubscription  $subscription
     * @return void
     */
    protected function sendWebhookRequest(string $url, array $payload, WebhookSubscription $subscription): void
    {
        try {
            $client = new \GuzzleHttp\Client();
            
            $apiSecret = config('n8n-eloquent.api.secret');
            
            // Calculate HMAC signature
            $signature = hash_hmac('sha256', json_encode($payload), $apiSecret);
            
            // Send the request
            $response = $client->post($url, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-N8n-Signature' => $signature,
                    'X-N8n-Subscription-Id' => $subscription->id,
                ],
                'timeout' => 5,
            ]);

            // Record successful trigger
            $subscription->recordTrigger();

            Log::channel(config('n8n-eloquent.logging.channel'))
                ->info("Sent webhook for subscription {$subscription->id}", [
                    'status_code' => $response->getStatusCode(),
                    'url' => $url,
                ]);
        } catch (\Throwable $e) {
            // Record error
            $subscription->recordError([
                'message' => $e->getMessage(),
                'url' => $url,
                'code' => $e->getCode(),
            ]);

            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error sending webhook for subscription {$subscription->id}", [
                    'error' => $e->getMessage(),
                    'url' => $url,
                ]);
        }
    }

    /**
     * Get all webhook subscriptions.
     *
     * @return array
     */
    public function getAllSubscriptions(): array
    {
        return WebhookSubscription::active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($subscription) {
                return $subscription->toLegacyArray();
            })
            ->toArray();
    }

    /**
     * Get a specific webhook subscription.
     *
     * @param  string  $subscriptionId
     * @return array|null
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        $subscription = WebhookSubscription::find($subscriptionId);
        
        return $subscription ? $subscription->toLegacyArray() : null;
    }

    /**
     * Update a webhook subscription.
     *
     * @param  string  $subscriptionId
     * @param  array  $updates
     * @return array|null
     */
    public function updateSubscription(string $subscriptionId, array $updates): ?array
    {
        $subscription = WebhookSubscription::find($subscriptionId);
        
        if (!$subscription) {
            return null;
        }

        // Filter allowed updates
        $allowedUpdates = array_intersect_key($updates, array_flip([
            'events', 'webhook_url', 'properties', 'active'
        ]));

        $subscription->update($allowedUpdates);

        // Clear cache to force refresh
        $this->clearSubscriptionsCache();

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info("Updated webhook subscription {$subscriptionId}", [
                'updates' => $allowedUpdates,
            ]);

        return $subscription->fresh()->toLegacyArray();
    }

    /**
     * Send a test webhook.
     *
     * @param  string  $subscriptionId
     * @param  array  $testPayload
     * @return bool
     */
    public function sendWebhook(string $subscriptionId, array $testPayload = []): bool
    {
        $subscription = WebhookSubscription::find($subscriptionId);
        
        if (!$subscription) {
            return false;
        }

        $payload = array_merge([
            'event' => 'test',
            'model' => $subscription->model_class,
            'timestamp' => now()->toIso8601String(),
            'data' => ['test' => true],
        ], $testPayload);

        try {
            $this->sendWebhookRequest(
                $subscription->webhook_url,
                $payload,
                $subscription
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get webhook statistics.
     *
     * @return array
     */
    public function getWebhookStats(): array
    {
        $totalSubscriptions = WebhookSubscription::count();
        $activeSubscriptions = WebhookSubscription::active()->count();
        $inactiveSubscriptions = WebhookSubscription::inactive()->count();
        $subscriptionsWithErrors = WebhookSubscription::withErrors()->count();
        $staleSubscriptions = WebhookSubscription::stale(24)->count();

        return [
            'total_subscriptions' => $totalSubscriptions,
            'active_subscriptions' => $activeSubscriptions,
            'inactive_subscriptions' => $inactiveSubscriptions,
            'subscriptions_with_errors' => $subscriptionsWithErrors,
            'stale_subscriptions' => $staleSubscriptions,
            'total_triggers' => WebhookSubscription::sum('trigger_count'),
        ];
    }

    /**
     * Clear the subscriptions cache.
     *
     * @return void
     */
    public function clearSubscriptionsCache(): void
    {
        Cache::forget($this->cacheKey);
    }

    /**
     * Migrate existing cache subscriptions to database.
     *
     * @return int Number of subscriptions migrated
     */
    public function migrateCacheToDatabase(): int
    {
        $cacheSubscriptions = Cache::get($this->cacheKey, []);
        
        if (empty($cacheSubscriptions)) {
            return 0;
        }

        $migrated = 0;

        foreach ($cacheSubscriptions as $subscriptionData) {
            // Check if subscription already exists in database
            if (WebhookSubscription::find($subscriptionData['id'])) {
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
        }

        // Clear cache after successful migration
        if ($migrated > 0) {
            Cache::forget($this->cacheKey);
        }

        return $migrated;
    }

    /**
     * Recover subscriptions from cache if database is empty.
     *
     * @return int Number of subscriptions recovered
     */
    public function recoverSubscriptions(): int
    {
        // Only recover if database is empty
        if (WebhookSubscription::count() > 0) {
            return 0;
        }

        return $this->migrateCacheToDatabase();
    }
} 