<?php

namespace N8n\Eloquent\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use N8n\Eloquent\Models\WebhookSubscription;
use N8n\Eloquent\Services\WebhookService;
use Orchestra\Testbench\TestCase;

class WebhookSubscriptionMigrationTest extends TestCase
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
    public function it_creates_webhook_subscriptions_table()
    {
        $this->assertTrue(Schema::hasTable('n8n_webhook_subscriptions'));
        
        $columns = [
            'id', 'model_class', 'events', 'webhook_url', 'properties',
            'active', 'last_triggered_at', 'trigger_count', 'last_error',
            'created_at', 'updated_at', 'deleted_at'
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('n8n_webhook_subscriptions', $column),
                "Column {$column} should exist in n8n_webhook_subscriptions table"
            );
        }
    }

    /** @test */
    public function it_can_create_webhook_subscription_in_database()
    {
        $subscription = WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created', 'updated'],
            'webhook_url' => 'https://example.com/webhook',
            'properties' => ['name', 'email'],
            'active' => true,
        ]);

        $this->assertDatabaseHas('n8n_webhook_subscriptions', [
            'id' => $subscription->id,
            'model_class' => 'App\\Models\\User',
            'webhook_url' => 'https://example.com/webhook',
            'active' => true,
        ]);

        $this->assertEquals(['created', 'updated'], $subscription->events);
        $this->assertEquals(['name', 'email'], $subscription->properties);
    }

    /** @test */
    public function it_can_use_webhook_service_with_database_storage()
    {
        $subscription = $this->webhookService->subscribe(
            'App\\Models\\User',
            ['created', 'updated'],
            'https://example.com/webhook',
            ['name', 'email']
        );

        $this->assertIsArray($subscription);
        $this->assertArrayHasKey('id', $subscription);
        $this->assertEquals('App\\Models\\User', $subscription['model']);
        $this->assertEquals(['created', 'updated'], $subscription['events']);

        // Verify it's stored in database
        $this->assertDatabaseHas('n8n_webhook_subscriptions', [
            'id' => $subscription['id'],
            'model_class' => 'App\\Models\\User',
        ]);
    }

    /** @test */
    public function it_can_query_subscriptions_by_model_and_event()
    {
        // Create test subscriptions
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created', 'updated'],
            'webhook_url' => 'https://example.com/user-webhook',
            'active' => true,
        ]);

        WebhookSubscription::create([
            'model_class' => 'App\\Models\\Post',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/post-webhook',
            'active' => true,
        ]);

        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['deleted'],
            'webhook_url' => 'https://example.com/user-delete-webhook',
            'active' => false, // Inactive
        ]);

        // Test model and event filtering
        $userCreatedSubscriptions = $this->webhookService->getSubscriptionsForModelEvent(
            'App\\Models\\User',
            'created'
        );

        $this->assertCount(1, $userCreatedSubscriptions);
        $this->assertEquals('https://example.com/user-webhook', $userCreatedSubscriptions[0]['webhook_url']);

        // Test that inactive subscriptions are excluded
        $userDeletedSubscriptions = $this->webhookService->getSubscriptionsForModelEvent(
            'App\\Models\\User',
            'deleted'
        );

        $this->assertCount(0, $userDeletedSubscriptions);
    }

    /** @test */
    public function it_can_migrate_cache_subscriptions_to_database()
    {
        // Set up cache subscriptions (legacy format)
        $cacheSubscriptions = [
            'sub-1' => [
                'id' => 'sub-1',
                'model' => 'App\\Models\\User',
                'events' => ['created'],
                'webhook_url' => 'https://example.com/webhook1',
                'properties' => [],
                'active' => true,
                'created_at' => now()->toIso8601String(),
            ],
            'sub-2' => [
                'id' => 'sub-2',
                'model' => 'App\\Models\\Post',
                'events' => ['updated', 'deleted'],
                'webhook_url' => 'https://example.com/webhook2',
                'properties' => ['title'],
                'active' => false,
                'created_at' => now()->subHour()->toIso8601String(),
            ],
        ];

        Cache::put('n8n_webhook_subscriptions', $cacheSubscriptions);

        // Migrate to database
        $migrated = $this->webhookService->migrateCacheToDatabase();

        $this->assertEquals(2, $migrated);

        // Verify subscriptions are in database
        $this->assertDatabaseHas('n8n_webhook_subscriptions', [
            'id' => 'sub-1',
            'model_class' => 'App\\Models\\User',
            'webhook_url' => 'https://example.com/webhook1',
            'active' => true,
        ]);

        $this->assertDatabaseHas('n8n_webhook_subscriptions', [
            'id' => 'sub-2',
            'model_class' => 'App\\Models\\Post',
            'webhook_url' => 'https://example.com/webhook2',
            'active' => false,
        ]);

        // Verify cache is cleared
        $this->assertNull(Cache::get('n8n_webhook_subscriptions'));
    }

    /** @test */
    public function it_records_webhook_triggers_and_errors()
    {
        $subscription = WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook',
            'active' => true,
        ]);

        $this->assertEquals(0, $subscription->trigger_count);
        $this->assertNull($subscription->last_triggered_at);

        // Record successful trigger
        $subscription->recordTrigger();
        $subscription->refresh();

        $this->assertEquals(1, $subscription->trigger_count);
        $this->assertNotNull($subscription->last_triggered_at);
        $this->assertNull($subscription->last_error);

        // Record error
        $subscription->recordError([
            'message' => 'Connection timeout',
            'code' => 500,
        ]);
        $subscription->refresh();

        $this->assertNotNull($subscription->last_error);
        $this->assertEquals('Connection timeout', $subscription->last_error['message']);
        $this->assertEquals(500, $subscription->last_error['code']);
        $this->assertArrayHasKey('occurred_at', $subscription->last_error);
    }

    /** @test */
    public function it_can_filter_subscriptions_by_health_status()
    {
        // Healthy subscription
        $healthy = WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook1',
            'active' => true,
        ]);

        // Subscription with error
        $withError = WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook2',
            'active' => true,
            'last_error' => ['message' => 'Error'],
        ]);

        // Inactive subscription
        $inactive = WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook3',
            'active' => false,
        ]);

        // Stale subscription
        $stale = WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook4',
            'active' => true,
            'last_triggered_at' => now()->subDays(2),
        ]);

        // Test scopes
        $this->assertEquals(3, WebhookSubscription::active()->count()); // healthy, withError, stale
        $this->assertEquals(1, WebhookSubscription::inactive()->count());
        $this->assertEquals(1, WebhookSubscription::withErrors()->count());
        $this->assertEquals(4, WebhookSubscription::stale(24)->count()); // All 4 subscriptions are stale (never triggered or old)

        // Test health status
        $this->assertTrue($healthy->isHealthy());
        $this->assertFalse($withError->isHealthy());
        $this->assertFalse($inactive->isHealthy());
        $this->assertTrue($stale->isHealthy()); // Active with no errors
    }

    /** @test */
    public function it_provides_webhook_statistics()
    {
        // Create test data
        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook1',
            'active' => true,
            'trigger_count' => 10,
        ]);

        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook2',
            'active' => false,
            'trigger_count' => 5,
            'last_error' => ['message' => 'Error'],
        ]);

        WebhookSubscription::create([
            'model_class' => 'App\\Models\\User',
            'events' => ['created'],
            'webhook_url' => 'https://example.com/webhook3',
            'active' => true,
            'trigger_count' => 3,
            'last_triggered_at' => now()->subDays(2),
        ]);

        $stats = $this->webhookService->getWebhookStats();

        $this->assertEquals(3, $stats['total_subscriptions']);
        $this->assertEquals(2, $stats['active_subscriptions']);
        $this->assertEquals(1, $stats['inactive_subscriptions']);
        $this->assertEquals(1, $stats['subscriptions_with_errors']);
        $this->assertEquals(3, $stats['stale_subscriptions']); // All 3 subscriptions are stale (never triggered or old)
        $this->assertEquals(18, $stats['total_triggers']); // 10 + 5 + 3
    }
} 