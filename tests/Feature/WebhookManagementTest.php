<?php

namespace N8n\Eloquent\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use N8n\Eloquent\Services\WebhookService;
use N8n\Eloquent\Tests\Fixtures\Models\TestUser;
use Orchestra\Testbench\TestCase;

class WebhookManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration
        Config::set('n8n-eloquent.api.secret', 'test-secret-key');
        Config::set('n8n-eloquent.logging.enabled', false);
    }

    protected function getPackageProviders($app)
    {
        return [
            \N8n\Eloquent\Providers\N8nEloquentServiceProvider::class,
        ];
    }

    /** @test */
    public function it_can_list_webhook_subscriptions()
    {
        // Create some test subscriptions
        $webhookService = app(WebhookService::class);
        $subscription1 = $webhookService->subscribe(
            TestUser::class,
            ['created', 'updated'],
            'https://example.com/webhook1'
        );
        $subscription2 = $webhookService->subscribe(
            TestUser::class,
            ['deleted'],
            'https://example.com/webhook2'
        );

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->getJson('/api/n8n/webhooks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscriptions' => [
                    '*' => [
                        'id',
                        'model',
                        'events',
                        'webhook_url',
                        'properties',
                        'created_at',
                    ]
                ],
                'total'
            ])
            ->assertJsonPath('total', 2);
    }

    /** @test */
    public function it_can_filter_subscriptions_by_model()
    {
        // Create subscriptions for different models
        $webhookService = app(WebhookService::class);
        $webhookService->subscribe(
            TestUser::class,
            ['created'],
            'https://example.com/webhook1'
        );
        $webhookService->subscribe(
            'App\\Models\\Post',
            ['created'],
            'https://example.com/webhook2'
        );

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->getJson('/api/n8n/webhooks?model=' . urlencode(TestUser::class));

        $response->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('subscriptions.0.model', TestUser::class);
    }

    /** @test */
    public function it_can_filter_subscriptions_by_event()
    {
        // Create subscriptions with different events
        $webhookService = app(WebhookService::class);
        $webhookService->subscribe(
            TestUser::class,
            ['created', 'updated'],
            'https://example.com/webhook1'
        );
        $webhookService->subscribe(
            TestUser::class,
            ['deleted'],
            'https://example.com/webhook2'
        );

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->getJson('/api/n8n/webhooks?event=created');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    /** @test */
    public function it_can_show_a_specific_subscription()
    {
        $webhookService = app(WebhookService::class);
        $subscription = $webhookService->subscribe(
            TestUser::class,
            ['created'],
            'https://example.com/webhook'
        );

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->getJson('/api/n8n/webhooks/' . $subscription['id']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscription' => [
                    'id',
                    'model',
                    'events',
                    'webhook_url',
                    'properties',
                    'created_at',
                ]
            ])
            ->assertJsonPath('subscription.id', $subscription['id']);
    }

    /** @test */
    public function it_returns_404_for_non_existent_subscription()
    {
        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->getJson('/api/n8n/webhooks/non-existent-id');

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Subscription with ID non-existent-id not found');
    }

    /** @test */
    public function it_can_update_a_subscription()
    {
        $webhookService = app(WebhookService::class);
        $subscription = $webhookService->subscribe(
            TestUser::class,
            ['created'],
            'https://example.com/webhook'
        );

        $updateData = [
            'events' => ['created', 'updated', 'deleted'],
            'webhook_url' => 'https://example.com/new-webhook',
            'active' => false,
        ];

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->putJson('/api/n8n/webhooks/' . $subscription['id'], $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Webhook subscription updated successfully')
            ->assertJsonPath('subscription.events', ['created', 'updated', 'deleted'])
            ->assertJsonPath('subscription.webhook_url', 'https://example.com/new-webhook')
            ->assertJsonPath('subscription.active', false);
    }

    /** @test */
    public function it_validates_update_data()
    {
        $webhookService = app(WebhookService::class);
        $subscription = $webhookService->subscribe(
            TestUser::class,
            ['created'],
            'https://example.com/webhook'
        );

        $invalidData = [
            'events' => ['invalid-event'],
            'webhook_url' => 'not-a-url',
            'active' => 'not-boolean',
        ];

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->putJson('/api/n8n/webhooks/' . $subscription['id'], $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => [
                    'events.0',
                    'webhook_url',
                    'active',
                ]
            ]);
    }

    /** @test */
    public function it_can_test_a_webhook_subscription()
    {
        $webhookService = app(WebhookService::class);
        $subscription = $webhookService->subscribe(
            TestUser::class,
            ['created'],
            'https://httpbin.org/post' // Using httpbin for testing
        );

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->postJson('/api/n8n/webhooks/' . $subscription['id'] . '/test');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Test webhook sent successfully')
            ->assertJsonStructure([
                'message',
                'result' => [
                    'success',
                    'status_code',
                ]
            ]);
    }

    /** @test */
    public function it_can_get_webhook_statistics()
    {
        $webhookService = app(WebhookService::class);
        
        // Create some test subscriptions
        $webhookService->subscribe(TestUser::class, ['created'], 'https://example.com/webhook1');
        $webhookService->subscribe(TestUser::class, ['updated'], 'https://example.com/webhook2');
        $subscription3 = $webhookService->subscribe(TestUser::class, ['deleted'], 'https://example.com/webhook3');
        
        // Deactivate one subscription
        $webhookService->updateSubscription($subscription3['id'], ['active' => false]);

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->getJson('/api/n8n/webhooks/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'total_subscriptions',
                    'active_subscriptions',
                    'inactive_subscriptions',
                    'models',
                    'events',
                ]
            ])
            ->assertJsonPath('stats.total_subscriptions', 3)
            ->assertJsonPath('stats.active_subscriptions', 2)
            ->assertJsonPath('stats.inactive_subscriptions', 1);
    }

    /** @test */
    public function it_can_perform_bulk_operations()
    {
        $webhookService = app(WebhookService::class);
        
        // Create test subscriptions
        $subscription1 = $webhookService->subscribe(TestUser::class, ['created'], 'https://example.com/webhook1');
        $subscription2 = $webhookService->subscribe(TestUser::class, ['updated'], 'https://example.com/webhook2');
        $subscription3 = $webhookService->subscribe(TestUser::class, ['deleted'], 'https://example.com/webhook3');

        // Test bulk deactivation
        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->postJson('/api/n8n/webhooks/bulk', [
            'action' => 'deactivate',
            'subscription_ids' => [$subscription1['id'], $subscription2['id']],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Bulk deactivate operation completed')
            ->assertJsonStructure([
                'message',
                'results'
            ]);

        // Verify subscriptions were deactivated
        $updatedSubscription1 = $webhookService->getSubscription($subscription1['id']);
        $this->assertFalse($updatedSubscription1['active']);
    }

    /** @test */
    public function it_validates_bulk_operation_data()
    {
        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret-key',
        ])->postJson('/api/n8n/webhooks/bulk', [
            'action' => 'invalid-action',
            'subscription_ids' => 'not-an-array',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => [
                    'action',
                    'subscription_ids',
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_all_endpoints()
    {
        $endpoints = [
            ['GET', '/api/n8n/webhooks'],
            ['GET', '/api/n8n/webhooks/test-id'],
            ['PUT', '/api/n8n/webhooks/test-id'],
            ['POST', '/api/n8n/webhooks/test-id/test'],
            ['GET', '/api/n8n/webhooks/stats'],
            ['POST', '/api/n8n/webhooks/bulk'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function it_applies_rate_limiting()
    {
        // Enable rate limiting with very low limits for testing
        Config::set('n8n-eloquent.api.rate_limiting.enabled', true);
        Config::set('n8n-eloquent.api.rate_limiting.max_attempts', 2);
        Config::set('n8n-eloquent.api.rate_limiting.decay_minutes', 1);

        $headers = ['X-N8n-Api-Key' => 'test-secret-key'];

        // First two requests should succeed
        $this->getJson('/api/n8n/webhooks', $headers)->assertStatus(200);
        $this->getJson('/api/n8n/webhooks', $headers)->assertStatus(200);

        // Third request should be rate limited
        $response = $this->getJson('/api/n8n/webhooks', $headers);
        $response->assertStatus(429)
            ->assertJsonStructure([
                'error',
                'retry_after'
            ]);
    }
} 