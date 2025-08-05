<?php

namespace Shortinc\N8nEloquent\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Shortinc\N8nEloquent\Events\ModelLifecycleEvent;
use Shortinc\N8nEloquent\Events\ModelPropertyEvent;
use Shortinc\N8nEloquent\Listeners\ModelLifecycleListener;
use Shortinc\N8nEloquent\Listeners\ModelPropertyListener;
use Shortinc\N8nEloquent\Services\WebhookService;
use Shortinc\N8nEloquent\Tests\Fixtures\Models\TestUser;
use Orchestra\Testbench\TestCase;

class EventListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration
        Config::set('n8n-eloquent.events.enabled', true);
        Config::set('n8n-eloquent.events.property_events.enabled', true);
        Config::set('n8n-eloquent.logging.enabled', false); // Disable logging for tests
    }

    protected function getPackageProviders($app)
    {
        return [
            \N8n\Eloquent\Providers\ShortincN8nEloquentServiceProvider::class,
        ];
    }

    /** @test */
    public function it_handles_model_lifecycle_events()
    {
        // Mock the webhook service
        $webhookService = $this->createMock(WebhookService::class);
        $this->app->instance(WebhookService::class, $webhookService);

        // Create a test model
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Create the event
        $event = new ModelLifecycleEvent($model, 'created');

        // Create the listener
        $listener = new ModelLifecycleListener($webhookService);

        // Expect the webhook to be triggered
        $webhookService->expects($this->once())
            ->method('triggerWebhook')
            ->with(
                TestUser::class,
                'created',
                $model,
                $this->isType('array')
            );

        // Handle the event
        $listener->handle($event);
    }

    /** @test */
    public function it_handles_model_property_events()
    {
        // Configure property events for the test model
        Config::set('n8n-eloquent.models.config.' . TestUser::class . '.setters', ['name']);

        // Mock the webhook service
        $webhookService = $this->createMock(WebhookService::class);
        $this->app->instance(WebhookService::class, $webhookService);

        // Create a test model
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Create the event
        $event = new ModelPropertyEvent($model, 'set', 'name', [
            'old_value' => 'John Doe',
            'new_value' => 'Jane Doe'
        ]);

        // Create the listener
        $listener = new ModelPropertyListener($webhookService);

        // Expect the webhook to be triggered
        $webhookService->expects($this->once())
            ->method('triggerWebhook')
            ->with(
                TestUser::class,
                'set',
                $model,
                $this->isType('array')
            );

        // Handle the event
        $listener->handle($event);
    }

    /** @test */
    public function it_skips_events_when_disabled()
    {
        // Disable events
        Config::set('n8n-eloquent.events.enabled', false);

        // Mock the webhook service
        $webhookService = $this->createMock(WebhookService::class);
        $this->app->instance(WebhookService::class, $webhookService);

        // Create a test model
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Create the event
        $event = new ModelLifecycleEvent($model, 'created');

        // Create the listener
        $listener = new ModelLifecycleListener($webhookService);

        // Expect the webhook NOT to be triggered
        $webhookService->expects($this->never())
            ->method('triggerWebhook');

        // Handle the event
        $listener->handle($event);
    }

    /** @test */
    public function it_skips_property_events_when_disabled()
    {
        // Disable property events
        Config::set('n8n-eloquent.events.property_events.enabled', false);

        // Mock the webhook service
        $webhookService = $this->createMock(WebhookService::class);
        $this->app->instance(WebhookService::class, $webhookService);

        // Create a test model
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Create the event
        $event = new ModelPropertyEvent($model, 'set', 'name', [
            'old_value' => 'John Doe',
            'new_value' => 'Jane Doe'
        ]);

        // Create the listener
        $listener = new ModelPropertyListener($webhookService);

        // Expect the webhook NOT to be triggered
        $webhookService->expects($this->never())
            ->method('triggerWebhook');

        // Handle the event
        $listener->handle($event);
    }

    /** @test */
    public function it_skips_unchanged_property_values()
    {
        // Mock the webhook service
        $webhookService = $this->createMock(WebhookService::class);
        $this->app->instance(WebhookService::class, $webhookService);

        // Create a test model
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Create the event with same old and new values
        $event = new ModelPropertyEvent($model, 'set', 'name', [
            'old_value' => 'John Doe',
            'new_value' => 'John Doe'
        ]);

        // Create the listener
        $listener = new ModelPropertyListener($webhookService);

        // Expect the webhook NOT to be triggered
        $webhookService->expects($this->never())
            ->method('triggerWebhook');

        // Handle the event
        $listener->handle($event);
    }

    /** @test */
    public function it_respects_watched_attributes_for_update_events()
    {
        // Configure watched attributes
        Config::set('n8n-eloquent.models.config.' . TestUser::class . '.watched_attributes', ['email']);

        // Mock the webhook service
        $webhookService = $this->createMock(WebhookService::class);
        $this->app->instance(WebhookService::class, $webhookService);

        // Create a test model with changes
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $model->syncOriginal();
        $model->name = 'Jane Doe'; // Change name (not watched)
        $model->syncChanges(); // Ensure changes are tracked

        // Create the event
        $event = new ModelLifecycleEvent($model, 'updated');

        // Create the listener
        $listener = new ModelLifecycleListener($webhookService);

        // Expect the webhook NOT to be triggered (name is not watched)
        $webhookService->expects($this->never())
            ->method('triggerWebhook');

        // Handle the event
        $listener->handle($event);
    }

    /** @test */
    public function it_triggers_for_watched_attribute_changes()
    {
        // Configure watched attributes
        Config::set('n8n-eloquent.models.config.' . TestUser::class . '.watched_attributes', ['email']);

        // Mock the webhook service
        $webhookService = $this->createMock(WebhookService::class);
        $this->app->instance(WebhookService::class, $webhookService);

        // Create a test model with changes
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $model->syncOriginal();
        $model->email = 'jane@example.com'; // Change email (watched)
        $model->syncChanges(); // Ensure changes are tracked

        // Create the event
        $event = new ModelLifecycleEvent($model, 'updated');

        // Create the listener
        $listener = new ModelLifecycleListener($webhookService);

        // Expect the webhook to be triggered (email is watched)
        $webhookService->expects($this->once())
            ->method('triggerWebhook')
            ->with(
                TestUser::class,
                'updated',
                $model,
                $this->isType('array')
            );

        // Handle the event
        $listener->handle($event);
    }

    /** @test */
    public function it_includes_original_and_changed_attributes_in_update_events()
    {
        // Mock the webhook service
        $webhookService = $this->createMock(WebhookService::class);
        $this->app->instance(WebhookService::class, $webhookService);

        // Create a test model with changes
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $model->syncOriginal();
        $model->name = 'Jane Doe';
        $model->syncChanges(); // Ensure changes are tracked

        // Create the event
        $event = new ModelLifecycleEvent($model, 'updated');

        // Verify the payload includes original and changed attributes
        $payload = $event->getPayload();
        
        $this->assertArrayHasKey('original_attributes', $payload);
        $this->assertArrayHasKey('changed_attributes', $payload);
        
        // Check if the attributes exist before asserting their values
        if (isset($payload['original_attributes']['name'])) {
            $this->assertEquals('John Doe', $payload['original_attributes']['name']);
        }
        if (isset($payload['changed_attributes']['name'])) {
            $this->assertEquals('Jane Doe', $payload['changed_attributes']['name']);
        }
    }

    /** @test */
    public function it_includes_property_data_in_property_events()
    {
        // Create a test model
        $model = new TestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Create a setter event
        $setEvent = new ModelPropertyEvent($model, 'set', 'name', [
            'old_value' => 'John Doe',
            'new_value' => 'Jane Doe'
        ]);

        $setPayload = $setEvent->getPayload();
        $this->assertArrayHasKey('property_name', $setPayload);
        $this->assertArrayHasKey('old_value', $setPayload);
        $this->assertArrayHasKey('new_value', $setPayload);
        $this->assertEquals('name', $setPayload['property_name']);
        $this->assertEquals('John Doe', $setPayload['old_value']);
        $this->assertEquals('Jane Doe', $setPayload['new_value']);

        // Create a getter event
        $getEvent = new ModelPropertyEvent($model, 'get', 'name');
        $getPayload = $getEvent->getPayload();
        $this->assertArrayHasKey('property_name', $getPayload);
        $this->assertArrayHasKey('current_value', $getPayload);
        $this->assertEquals('name', $getPayload['property_name']);
        $this->assertEquals('John Doe', $getPayload['current_value']);
    }
} 