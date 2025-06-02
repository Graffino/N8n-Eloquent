<?php

namespace N8n\Eloquent\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use N8n\Eloquent\Models\WebhookSubscription;
use N8n\Eloquent\Services\WebhookService;
use Orchestra\Testbench\TestCase;
use Carbon\Carbon;

class SubscriptionHealthMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected WebhookService $webhookService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookService = app(WebhookService::class);
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            'N8n\Eloquent\Providers\N8nEloquentServiceProvider',
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Configure n8n-eloquent for testing
        $app['config']->set('n8n-eloquent.api.secret', 'test-secret');
        $app['config']->set('n8n-eloquent.logging.channel', 'single');
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /** @test */
    public function it_can_get_overall_health_status()
    {
        // Create test subscriptions with different health states
        $this->createTestSubscriptions();

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret',
        ])->getJson('/api/n8n/health');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'overall_health',
                         'statistics',
                         'recent_activity',
                         'recommendations',
                         'last_checked',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertContains($data['overall_health'], ['excellent', 'good', 'warning', 'critical', 'no_subscriptions']);
        $this->assertIsArray($data['statistics']);
        $this->assertIsArray($data['recent_activity']);
        $this->assertIsArray($data['recommendations']);
    }

    /** @test */
    public function it_can_get_detailed_health_information()
    {
        // Create test subscriptions
        $this->createTestSubscriptions();

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret',
        ])->getJson('/api/n8n/health/detailed');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         '*' => [
                             'id',
                             'model_class',
                             'events',
                             'webhook_url',
                             'active',
                             'health_status',
                             'trigger_count',
                             'last_triggered_at',
                             'last_error',
                             'created_at',
                             'issues',
                         ],
                     ],
                     'pagination',
                 ]);
    }

    /** @test */
    public function it_can_validate_specific_subscription()
    {
        $subscription = WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created', 'updated'],
            'webhook_url' => 'https://example.com/webhook',
            'active' => true,
        ]);

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret',
        ])->getJson("/api/n8n/health/validate/{$subscription->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'subscription_id',
                         'validation_results' => [
                             'is_valid',
                             'checks',
                         ],
                         'overall_valid',
                         'validated_at',
                     ],
                 ]);

        $validationResults = $response->json('data.validation_results');
        $this->assertIsBool($validationResults['is_valid']);
        $this->assertIsArray($validationResults['checks']);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_subscription_validation()
    {
        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret',
        ])->getJson('/api/n8n/health/validate/nonexistent-id');

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Subscription not found',
                 ]);
    }

    /** @test */
    public function it_can_get_subscription_analytics()
    {
        // Create test subscriptions with different creation dates
        $this->createTestSubscriptionsWithDates();

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret',
        ])->getJson('/api/n8n/health/analytics?days=7');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'period',
                         'creation_trends',
                         'trigger_activity',
                         'model_usage',
                         'event_usage',
                         'error_trends',
                         'generated_at',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertEquals(7, $data['period']['days']);
        $this->assertIsArray($data['creation_trends']);
        $this->assertIsArray($data['model_usage']);
        $this->assertIsArray($data['event_usage']);
    }

    /** @test */
    public function it_requires_authentication_for_health_endpoints()
    {
        $endpoints = [
            '/api/n8n/health',
            '/api/n8n/health/detailed',
            '/api/n8n/health/analytics',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function health_check_command_runs_successfully()
    {
        // Create test subscriptions
        $this->createTestSubscriptions();

        $exitCode = Artisan::call('n8n:health-check');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Starting webhook subscription health check', $output);
        $this->assertStringContainsString('Health check completed', $output);
    }

    /** @test */
    public function health_check_command_shows_detailed_information()
    {
        // Create problematic subscriptions
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook',
            'active' => false, // Inactive
        ]);

        WebhookSubscription::create([
            'model_class' => 'App\\Models\\Post',
            'events' => ['updated'],
            'webhook_url' => 'https://example.com/webhook2',
            'active' => true,
            'last_error' => ['message' => 'Connection failed'], // Has error
        ]);

        $exitCode = Artisan::call('n8n:health-check', ['--detailed' => true]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Detailed Health Information', $output);
    }

    /** @test */
    public function health_check_command_outputs_json_format()
    {
        $this->createTestSubscriptions();

        $exitCode = Artisan::call('n8n:health-check', ['--format' => 'json']);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        
        // Should contain JSON output
        $this->assertStringContainsString('{', $output);
        $this->assertStringContainsString('}', $output);
    }

    /** @test */
    public function health_status_calculation_works_correctly()
    {
        // Test excellent health (95%+ active, <5% errors)
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook1',
            'active' => true,
            'trigger_count' => 10,
        ]);

        $stats = $this->webhookService->getWebhookStats();
        $this->assertEquals(1, $stats['total_subscriptions']);
        $this->assertEquals(1, $stats['active_subscriptions']);
        $this->assertEquals(0, $stats['subscriptions_with_errors']);

        // Test critical health (>20% errors)
        for ($i = 0; $i < 4; $i++) {
            WebhookSubscription::create([
                'model_class' => 'App\\Models\\User',
                'events' => ['created'],
                'webhook_url' => "https://example.com/webhook{$i}",
                'active' => true,
                'last_error' => ['message' => 'Error'],
            ]);
        }

        $stats = $this->webhookService->getWebhookStats();
        $this->assertEquals(5, $stats['total_subscriptions']);
        $this->assertEquals(4, $stats['subscriptions_with_errors']); // 80% error rate
    }

    /** @test */
    public function subscription_validation_detects_issues()
    {
        // Create subscription with invalid model class
        $subscription = WebhookSubscription::create([
            'model_class' => 'NonExistent\\Model',
            'events' => ['created', 'invalid_event'],
            'webhook_url' => 'not-a-valid-url',
            'active' => true,
        ]);

        $response = $this->withHeaders([
            'X-N8n-Api-Key' => 'test-secret',
        ])->getJson("/api/n8n/health/validate/{$subscription->id}");

        $response->assertStatus(200);
        
        $validationResults = $response->json('data.validation_results');
        $this->assertFalse($validationResults['is_valid']);
        
        $checks = $validationResults['checks'];
        $this->assertFalse($checks['model_exists']['passed']);
        $this->assertFalse($checks['url_valid']['passed']);
        $this->assertFalse($checks['events_valid']['passed']);
    }

    /**
     * Create test subscriptions with different health states.
     *
     * @return void
     */
    protected function createTestSubscriptions(): void
    {
        // Healthy subscription
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook1',
            'active' => true,
            'trigger_count' => 5,
            'last_triggered_at' => now()->subHours(2),
        ]);

        // Subscription with error
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\Post',
            'events' => ['updated'],
            'webhook_url' => 'https://example.com/webhook2',
            'active' => true,
            'trigger_count' => 2,
            'last_error' => [
                'message' => 'Connection timeout',
                'occurred_at' => now()->subHours(1)->toIso8601String(),
            ],
        ]);

        // Inactive subscription
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\Comment',
            'events' => ['deleted'],
            'webhook_url' => 'https://example.com/webhook3',
            'active' => false,
        ]);

        // Stale subscription
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\Order',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook4',
            'active' => true,
            'trigger_count' => 1,
            'last_triggered_at' => now()->subDays(2),
        ]);
    }

    /**
     * Create test subscriptions with different creation dates for analytics.
     *
     * @return void
     */
    protected function createTestSubscriptionsWithDates(): void
    {
        // Subscription created today
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook1',
            'active' => true,
            'trigger_count' => 3,
            'last_triggered_at' => now(),
            'created_at' => now(),
        ]);

        // Subscription created yesterday
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\Post',
            'events' => ['updated'],
            'webhook_url' => 'https://example.com/webhook2',
            'active' => true,
            'trigger_count' => 7,
            'last_triggered_at' => now()->subHours(12),
            'created_at' => now()->subDay(),
        ]);

        // Subscription created 3 days ago
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\Comment',
            'events' => ['created', 'deleted'],
            'webhook_url' => 'https://example.com/webhook3',
            'active' => true,
            'trigger_count' => 15,
            'last_triggered_at' => now()->subDays(2),
            'created_at' => now()->subDays(3),
        ]);
    }
} 