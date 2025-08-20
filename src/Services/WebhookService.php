<?php

namespace Shortinc\N8nEloquent\Services;

use Illuminate\Support\Facades\Cache;
use Shortinc\N8nEloquent\Models\WebhookSubscription;

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
     * @param  array  $metadata
     * @return array
     */
    public function subscribe(
        string $modelClass,
        array $events,
        string $webhookUrl,
        array $properties = [],
        array $metadata = []
    ): array {
        // Check if a subscription already exists for this exact webhook URL
        // We only match by webhook_url because that's what uniquely identifies an n8n node
        $existingSubscription = WebhookSubscription::where('webhook_url', $webhookUrl)
            ->first();

        if ($existingSubscription) {
            // Update the existing subscription
            $existingSubscription->update([
                'model_class' => $modelClass,
                'events' => $events,
                'properties' => $properties,
                'node_id' => $metadata['node_id'] ?? null,
                'workflow_id' => $metadata['workflow_id'] ?? null,
                'verify_hmac' => $metadata['verify_hmac'] ?? true,
                'require_timestamp' => $metadata['require_timestamp'] ?? true,
                'expected_source_ip' => $metadata['expected_source_ip'] ?? null,
                'active' => true,
                'last_error' => null, // Clear any previous errors
            ]);

            // Clear cache to force refresh
            $this->clearSubscriptionsCache();

            return [
                'message' => 'Webhook subscription updated successfully',
                'subscription' => $existingSubscription->fresh()->toLegacyArray(),
            ];
        }

        // Create a new subscription if none exists
        $subscription = WebhookSubscription::create([
            'model_class' => $modelClass,
            'events' => $events,
            'webhook_url' => $webhookUrl,
            'properties' => $properties,
            'node_id' => $metadata['node_id'] ?? null,
            'workflow_id' => $metadata['workflow_id'] ?? null,
            'verify_hmac' => $metadata['verify_hmac'] ?? true,
            'require_timestamp' => $metadata['require_timestamp'] ?? true,
            'expected_source_ip' => $metadata['expected_source_ip'] ?? null,
            'active' => true,
        ]);

        // Clear cache to force refresh
        $this->clearSubscriptionsCache();

        return [
            'message' => 'Webhook subscription created successfully',
            'subscription' => $subscription->toLegacyArray(),
        ];
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

        // Initialize metadata if not present
        $metadata = $additionalPayload['metadata'] ?? [];
        
        // Ensure source_trigger is properly structured
        if (!empty($metadata['source_trigger'])) {
            $sourceTrigger = $metadata['source_trigger'];
            if (!isset($sourceTrigger['timestamp'])) {
                $sourceTrigger['timestamp'] = now()->toIso8601String();
            }
            $metadata['source_trigger'] = $sourceTrigger;
        }

        // Prepare the payload
        $payload = array_merge([
            'event' => $event,
            'model' => $modelClass,
            'timestamp' => now()->toIso8601String(),
            'data' => $model->toArray(),
            'metadata' => $metadata
        ], $additionalPayload);

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
            
            $hmacSecret = config('n8n-eloquent.webhooks.hmac_secret');
            
            // Calculate HMAC signature using the JSON-encoded payload
            $jsonPayload = json_encode($payload);
            $signature = hash_hmac('sha256', $jsonPayload, $hmacSecret);
            
            // Send the request with the raw JSON string to match the HMAC calculation
            $response = $client->post($url, [
                'body' => $jsonPayload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-N8n-Signature' => $signature,
                    'X-N8n-Subscription-Id' => $subscription->id,
                ],
                'timeout' => 5,
            ]);

            // Record successful trigger
            $subscription->recordTrigger();
        } catch (\Throwable $e) {
            // Record error
            $subscription->recordError([
                'message' => $e->getMessage(),
                'url' => $url,
                'code' => $e->getCode(),
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

    /**
     * Subscribe to Laravel events.
     *
     * @param  string  $eventClass
     * @param  string  $webhookUrl
     * @param  array  $metadata
     * @return array
     */
    public function subscribeToEvent(
        string $eventClass,
        string $webhookUrl,
        array $metadata = []
    ): array {
        // Check if a subscription already exists for this exact webhook URL
        // We only match by webhook_url because that's what uniquely identifies an n8n node
        $existingSubscription = WebhookSubscription::where('webhook_url', $webhookUrl)
            ->where('is_event_subscription', true)
            ->first();

        if ($existingSubscription) {
            // Update the existing subscription
            $existingSubscription->update([
                'model_class' => $eventClass,
                'node_id' => $metadata['node_id'] ?? null,
                'workflow_id' => $metadata['workflow_id'] ?? null,
                'verify_hmac' => $metadata['verify_hmac'] ?? true,
                'require_timestamp' => $metadata['require_timestamp'] ?? true,
                'expected_source_ip' => $metadata['expected_source_ip'] ?? null,
                'active' => true,
                'last_error' => null, // Clear any previous errors
            ]);

            // Clear cache to force refresh
            $this->clearSubscriptionsCache();

            return [
                'message' => 'Event webhook subscription updated successfully',
                'subscription' => $existingSubscription->fresh()->toLegacyArray(),
            ];
        }

        // Create a new subscription if none exists
        $subscription = WebhookSubscription::create([
            'model_class' => $eventClass,
            'events' => ['dispatched'], // Event subscriptions only listen for 'dispatched' event
            'webhook_url' => $webhookUrl,
            'properties' => [],
            'node_id' => $metadata['node_id'] ?? null,
            'workflow_id' => $metadata['workflow_id'] ?? null,
            'verify_hmac' => $metadata['verify_hmac'] ?? true,
            'require_timestamp' => $metadata['require_timestamp'] ?? true,
            'expected_source_ip' => $metadata['expected_source_ip'] ?? null,
            'active' => true,
            'is_event_subscription' => true, // Mark as event subscription
        ]);

        // Clear cache to force refresh
        $this->clearSubscriptionsCache();

        return [
            'message' => 'Event webhook subscription created successfully',
            'subscription' => $subscription->toLegacyArray(),
        ];
    }

    /**
     * Unsubscribe from Laravel events.
     *
     * @param  string  $subscriptionId
     * @return bool
     */
    public function unsubscribeFromEvent(string $subscriptionId): bool
    {
        return $this->unsubscribe($subscriptionId);
    }

    /**
     * Trigger webhook for a Laravel event.
     *
     * @param  string  $eventClass
     * @param  mixed  $eventInstance
     * @param  array  $additionalPayload
     * @return void
     */
    public function triggerEventWebhook(string $eventClass, $eventInstance, array $additionalPayload = []): void
    {
        $subscriptions = $this->getSubscriptionsForEvent($eventClass);

        if (empty($subscriptions)) {
            return;
        }

        // Prepare the webhook payload
        $payload = [
            'event' => 'dispatched',
            'event_class' => $eventClass,
            'data' => $this->serializeEvent($eventInstance),
            'timestamp' => now()->toISOString(),
        ];

        // Merge additional payload
        $payload = array_merge($payload, $additionalPayload);

        // Send webhook to all subscriptions
        foreach ($subscriptions as $subscription) {
            try {
                $this->sendWebhookRequest($subscription['webhook_url'], $payload, WebhookSubscription::find($subscription['id']));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Get subscriptions for a specific event.
     *
     * @param  string  $eventClass
     * @return array
     */
    public function getSubscriptionsForEvent(string $eventClass): array
    {
        return WebhookSubscription::where('model_class', $eventClass)
            ->where('is_event_subscription', true)
            ->where('active', true)
            ->get()
            ->map(function ($subscription) {
                return $subscription->toLegacyArray();
            })
            ->toArray();
    }

    /**
     * Serialize an event instance for webhook payload.
     *
     * @param  mixed  $eventInstance
     * @return array
     */
    protected function serializeEvent($eventInstance): array
    {
        if (is_object($eventInstance)) {
            // Get all public properties
            $reflection = new \ReflectionClass($eventInstance);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            
            $data = [];
            foreach ($properties as $property) {
                $propertyName = $property->getName();
                $propertyValue = $property->getValue($eventInstance);
                
                // Convert to array if it's an object that can be serialized
                if (is_object($propertyValue)) {
                    if (method_exists($propertyValue, 'toArray')) {
                        $data[$propertyName] = $propertyValue->toArray();
                    } elseif (method_exists($propertyValue, '__toString')) {
                        $data[$propertyName] = (string) $propertyValue;
                    } else {
                        $data[$propertyName] = get_class($propertyValue);
                    }
                } else {
                    $data[$propertyName] = $propertyValue;
                }
            }
            
            return $data;
        }
        
        return [];
    }
} 