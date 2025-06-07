<?php

namespace Shortinc\N8nEloquent\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Shortinc\N8nEloquent\Services\WebhookService;
use Shortinc\N8nEloquent\Tests\Fixtures\Models\TestUser;
use Orchestra\Testbench\TestCase;

class WebhookServiceTest extends TestCase
{
    /**
     * The webhook service.
     *
     * @var \N8n\Eloquent\Services\WebhookService
     */
    protected $webhookService;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the webhook service
        $this->webhookService = new WebhookService();
        
        // Clear cache before each test
        Cache::flush();
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
            'N8n\Eloquent\Providers\ShortincN8nEloquentServiceProvider',
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
        // Configure n8n-eloquent for testing
        $app['config']->set('n8n-eloquent.api.secret', 'test-secret');
        $app['config']->set('n8n-eloquent.logging.channel', 'single');
    }

    /**
     * Test webhook subscription.
     *
     * @return void
     */
    public function testWebhookSubscription()
    {
        $modelClass = 'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser';
        $events = ['created', 'updated'];
        $webhookUrl = 'https://example.com/webhook';
        $properties = ['name', 'email'];

        $subscription = $this->webhookService->subscribe(
            $modelClass,
            $events,
            $webhookUrl,
            $properties
        );

        $this->assertIsArray($subscription);
        $this->assertArrayHasKey('id', $subscription);
        $this->assertArrayHasKey('model', $subscription);
        $this->assertArrayHasKey('events', $subscription);
        $this->assertArrayHasKey('webhook_url', $subscription);
        $this->assertArrayHasKey('properties', $subscription);
        $this->assertArrayHasKey('created_at', $subscription);

        $this->assertEquals($modelClass, $subscription['model']);
        $this->assertEquals($events, $subscription['events']);
        $this->assertEquals($webhookUrl, $subscription['webhook_url']);
        $this->assertEquals($properties, $subscription['properties']);
    }

    /**
     * Test webhook unsubscription.
     *
     * @return void
     */
    public function testWebhookUnsubscription()
    {
        // First, create a subscription
        $subscription = $this->webhookService->subscribe(
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser',
            ['created'],
            'https://example.com/webhook'
        );

        $subscriptionId = $subscription['id'];

        // Verify subscription exists
        $subscriptions = $this->webhookService->getSubscriptions();
        $this->assertArrayHasKey($subscriptionId, $subscriptions);

        // Unsubscribe
        $result = $this->webhookService->unsubscribe($subscriptionId);
        $this->assertTrue($result);

        // Verify subscription is removed
        $subscriptions = $this->webhookService->getSubscriptions();
        $this->assertArrayNotHasKey($subscriptionId, $subscriptions);
    }

    /**
     * Test unsubscribing from non-existent subscription.
     *
     * @return void
     */
    public function testUnsubscribeNonExistentSubscription()
    {
        $result = $this->webhookService->unsubscribe('non-existent-id');
        $this->assertFalse($result);
    }

    /**
     * Test getting subscriptions for model event.
     *
     * @return void
     */
    public function testGetSubscriptionsForModelEvent()
    {
        $modelClass = 'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser';
        
        // Create multiple subscriptions
        $subscription1 = $this->webhookService->subscribe(
            $modelClass,
            ['created', 'updated'],
            'https://example.com/webhook1'
        );

        $subscription2 = $this->webhookService->subscribe(
            $modelClass,
            ['deleted'],
            'https://example.com/webhook2'
        );

        $subscription3 = $this->webhookService->subscribe(
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUserCounter',
            ['created'],
            'https://example.com/webhook3'
        );

        // Test getting subscriptions for specific model and event
        $createdSubscriptions = $this->webhookService->getSubscriptionsForModelEvent($modelClass, 'created');
        $this->assertCount(1, $createdSubscriptions);
        $this->assertEquals($subscription1['id'], $createdSubscriptions[0]['id']);

        $updatedSubscriptions = $this->webhookService->getSubscriptionsForModelEvent($modelClass, 'updated');
        $this->assertCount(1, $updatedSubscriptions);
        $this->assertEquals($subscription1['id'], $updatedSubscriptions[0]['id']);

        $deletedSubscriptions = $this->webhookService->getSubscriptionsForModelEvent($modelClass, 'deleted');
        $this->assertCount(1, $deletedSubscriptions);
        $this->assertEquals($subscription2['id'], $deletedSubscriptions[0]['id']);

        // Test with different model
        $counterCreatedSubscriptions = $this->webhookService->getSubscriptionsForModelEvent(
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUserCounter',
            'created'
        );
        $this->assertCount(1, $counterCreatedSubscriptions);
        $this->assertEquals($subscription3['id'], $counterCreatedSubscriptions[0]['id']);
    }

    /**
     * Test getting all subscriptions.
     *
     * @return void
     */
    public function testGetAllSubscriptions()
    {
        // Initially should be empty
        $subscriptions = $this->webhookService->getSubscriptions();
        $this->assertEmpty($subscriptions);

        // Create a subscription
        $subscription = $this->webhookService->subscribe(
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser',
            ['created'],
            'https://example.com/webhook'
        );

        // Should now have one subscription
        $subscriptions = $this->webhookService->getSubscriptions();
        $this->assertCount(1, $subscriptions);
        $this->assertArrayHasKey($subscription['id'], $subscriptions);
    }

    /**
     * Test triggering webhook with mock model.
     *
     * @return void
     */
    public function testTriggerWebhook()
    {
        // Create a subscription
        $this->webhookService->subscribe(
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser',
            ['created'],
            'https://httpbin.org/post' // Using httpbin for testing
        );

        // Create a mock model
        $model = new TestUser();
        $model->name = 'Test User';
        $model->email = 'test@example.com';
        $model->age = 25;

        // This should not throw an exception
        // In a real test, we might mock the HTTP client
        $this->webhookService->triggerWebhook(
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser',
            'created',
            $model
        );

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }
} 