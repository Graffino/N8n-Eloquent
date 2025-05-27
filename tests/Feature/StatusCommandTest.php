<?php

namespace N8n\Eloquent\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use N8n\Eloquent\Services\WebhookService;
use N8n\Eloquent\Tests\Fixtures\Models\TestUser;
use Orchestra\Testbench\TestCase;

class StatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            \N8n\Eloquent\Providers\N8nEloquentServiceProvider::class,
        ];
    }

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
        $app['config']->set('n8n-eloquent.models.namespace', 'N8n\\Eloquent\\Tests\\Fixtures\\Models');
        $app['config']->set('n8n-eloquent.models.directory', __DIR__ . '/../Fixtures/Models');
        $app['config']->set('n8n-eloquent.api.secret', 'test-secret');
        $app['config']->set('n8n-eloquent.logging.channel', 'single');
        $app['config']->set('n8n-eloquent.models.mode', 'all');
        $app['config']->set('n8n-eloquent.n8n.url', 'http://localhost:5678');
    }

    /** @test */
    public function it_can_show_basic_status()
    {
        $this->artisan('n8n:status')
             ->expectsOutput('🔍 n8n Eloquent Integration Status')
             ->expectsOutput('⚙️  Configuration Status:')
             ->expectsOutput('API Secret: ✅ Configured')
             ->expectsOutput('n8n URL: ✅ http://localhost:5678')
             ->expectsOutput('📊 Model Discovery Status:')
             ->expectsOutput('Discovery Mode: all')
             ->expectsOutput('🔗 Webhook Status:')
             ->expectsOutput('🎯 Event Configuration:')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_can_show_detailed_status()
    {
        // Create a webhook subscription for testing
        $webhookService = app(WebhookService::class);
        $webhookService->subscribe(
            TestUser::class,
            ['created', 'updated'],
            'https://example.com/webhook'
        );

                 $this->artisan('n8n:status', ['--detailed' => true])
              ->expectsOutput('🔍 n8n Eloquent Integration Status')
              ->expectsOutput('🔍 Detailed Information:')
              ->expectsOutputToContain('Cache Status:')
              ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_model_discovery_information()
    {
        $this->artisan('n8n:status')
             ->expectsOutput('📊 Model Discovery Status:')
             ->expectsOutput('Discovery Mode: all')
             ->expectsOutputToContain('Models Found:')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_webhook_statistics()
    {
        // Create some webhook subscriptions
        $webhookService = app(WebhookService::class);
        $webhookService->subscribe(TestUser::class, ['created'], 'https://example.com/webhook1');
        $webhookService->subscribe(TestUser::class, ['updated'], 'https://example.com/webhook2');

        $this->artisan('n8n:status')
             ->expectsOutput('🔗 Webhook Status:')
             ->expectsOutput('Total Subscriptions: 2')
             ->expectsOutput('Active Subscriptions: 2')
             ->expectsOutput('Inactive Subscriptions: 0')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_event_configuration()
    {
        $this->artisan('n8n:status')
             ->expectsOutput('🎯 Event Configuration:')
             ->expectsOutputToContain('Default Events:')
             ->expectsOutputToContain('Property Events:')
             ->expectsOutputToContain('Queue Processing:')
             ->expectsOutputToContain('Transactions:')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_missing_configuration()
    {
        // Clear API secret to test missing configuration
        Config::set('n8n-eloquent.api.secret', null);
        Config::set('n8n-eloquent.n8n.url', null);

        $this->artisan('n8n:status')
             ->expectsOutput('API Secret: ❌ Not configured')
             ->expectsOutput('n8n URL: ❌ Not configured')
             ->assertExitCode(0);
    }
} 