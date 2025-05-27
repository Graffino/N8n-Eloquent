<?php

namespace N8n\Eloquent\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookService
{
    /**
     * The cache key for webhook subscriptions.
     *
     * @var string
     */
    protected $cacheKey = 'n8n_eloquent_webhook_subscriptions';

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
        // Generate a unique subscription ID
        $subscriptionId = (string) Str::uuid();
        
        // Create the subscription
        $subscription = [
            'id' => $subscriptionId,
            'model' => $modelClass,
            'events' => $events,
            'webhook_url' => $webhookUrl,
            'properties' => $properties,
            'created_at' => now()->toIso8601String(),
        ];
        
        // Get existing subscriptions
        $subscriptions = $this->getSubscriptions();
        
        // Add the new subscription
        $subscriptions[$subscriptionId] = $subscription;
        
        // Save the subscriptions
        $this->saveSubscriptions($subscriptions);
        
        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info("Created webhook subscription for model {$modelClass}", [
                'subscription_id' => $subscriptionId,
                'events' => $events,
                'webhook_url' => $webhookUrl,
            ]);
        
        return $subscription;
    }

    /**
     * Unsubscribe from model events.
     *
     * @param  string  $subscriptionId
     * @return bool
     */
    public function unsubscribe(string $subscriptionId): bool
    {
        // Get existing subscriptions
        $subscriptions = $this->getSubscriptions();
        
        // Check if the subscription exists
        if (!isset($subscriptions[$subscriptionId])) {
            return false;
        }
        
        // Get the subscription for logging
        $subscription = $subscriptions[$subscriptionId];
        
        // Remove the subscription
        unset($subscriptions[$subscriptionId]);
        
        // Save the subscriptions
        $this->saveSubscriptions($subscriptions);
        
        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info("Deleted webhook subscription", [
                'subscription_id' => $subscriptionId,
                'model' => $subscription['model'] ?? 'unknown',
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
        return Cache::get($this->cacheKey, []);
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
        $subscriptions = $this->getSubscriptions();
        
        return collect($subscriptions)
            ->filter(function ($subscription) use ($modelClass, $event) {
                return $subscription['model'] === $modelClass &&
                       in_array($event, $subscription['events']);
            })
            ->values()
            ->all();
    }

    /**
     * Save webhook subscriptions.
     *
     * @param  array  $subscriptions
     * @return void
     */
    protected function saveSubscriptions(array $subscriptions): void
    {
        Cache::forever($this->cacheKey, $subscriptions);
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
        // Get subscriptions for this model and event
        $subscriptions = $this->getSubscriptionsForModelEvent($modelClass, $event);
        
        if (empty($subscriptions)) {
            return;
        }
        
        // Prepare the payload
        $payload = array_merge([
            'event' => $event,
            'model' => $modelClass,
            'timestamp' => now()->toIso8601String(),
            'data' => $model->toArray(),
        ], $additionalPayload);
        
        // Send the webhook to each subscription
        foreach ($subscriptions as $subscription) {
            // Skip inactive subscriptions
            if (isset($subscription['active']) && !$subscription['active']) {
                continue;
            }
            
            $this->sendWebhookRequest(
                $subscription['webhook_url'],
                $payload,
                $subscription['id']
            );
        }
    }

    /**
     * Send a webhook request.
     *
     * @param  string  $url
     * @param  array  $payload
     * @param  string  $subscriptionId
     * @return void
     */
    protected function sendWebhookRequest(string $url, array $payload, string $subscriptionId): void
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
                    'X-N8n-Subscription-Id' => $subscriptionId,
                ],
                'timeout' => 5,
            ]);
            
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->info("Sent webhook for subscription {$subscriptionId}", [
                    'status_code' => $response->getStatusCode(),
                    'url' => $url,
                ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error sending webhook for subscription {$subscriptionId}", [
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
        return array_values($this->getSubscriptions());
    }

    /**
     * Get a specific webhook subscription.
     *
     * @param  string  $subscriptionId
     * @return array|null
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        $subscriptions = $this->getSubscriptions();
        return $subscriptions[$subscriptionId] ?? null;
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
        $subscriptions = $this->getSubscriptions();
        
        if (!isset($subscriptions[$subscriptionId])) {
            return null;
        }
        
        // Update the subscription
        $subscriptions[$subscriptionId] = array_merge(
            $subscriptions[$subscriptionId],
            $updates,
            ['updated_at' => now()->toIso8601String()]
        );
        
        // Save the subscriptions
        $this->saveSubscriptions($subscriptions);
        
        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info("Updated webhook subscription {$subscriptionId}", [
                'updates' => $updates,
            ]);
        
        return $subscriptions[$subscriptionId];
    }

    /**
     * Send a webhook to a specific URL.
     *
     * @param  string  $url
     * @param  array  $payload
     * @return array
     */
    public function sendWebhook(string $url, array $payload): array
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
                ],
                'timeout' => 10,
            ]);
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getBody()->getContents(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Get webhook statistics.
     *
     * @return array
     */
    public function getWebhookStats(): array
    {
        $subscriptions = $this->getSubscriptions();
        
        $stats = [
            'total_subscriptions' => count($subscriptions),
            'active_subscriptions' => 0,
            'inactive_subscriptions' => 0,
            'models' => [],
            'events' => [],
        ];
        
        foreach ($subscriptions as $subscription) {
            // Count active/inactive
            if (isset($subscription['active']) && !$subscription['active']) {
                $stats['inactive_subscriptions']++;
            } else {
                $stats['active_subscriptions']++;
            }
            
            // Count by model
            $model = $subscription['model'];
            if (!isset($stats['models'][$model])) {
                $stats['models'][$model] = 0;
            }
            $stats['models'][$model]++;
            
            // Count by event
            foreach ($subscription['events'] as $event) {
                if (!isset($stats['events'][$event])) {
                    $stats['events'][$event] = 0;
                }
                $stats['events'][$event]++;
            }
        }
        
        return $stats;
    }
} 