<?php

namespace Shortinc\N8nEloquent\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

class SetupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            \N8n\Eloquent\Providers\ShortincN8nEloquentServiceProvider::class,
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
    }

    /** @test */
    public function it_can_run_setup_command_with_api_secret()
    {
        $this->artisan('n8n:setup', ['--api-secret' => 'custom-secret'])
             ->expectsOutput('Setting up n8n Eloquent Integration...')
             ->expectsOutputToContain('API secret: custom-secret')
             ->expectsOutput('✅ n8n Eloquent Integration setup completed successfully!')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_can_run_setup_command_without_api_secret()
    {
        $this->artisan('n8n:setup')
             ->expectsOutput('Setting up n8n Eloquent Integration...')
             ->expectsOutputToContain('Generated API secret:')
             ->expectsOutput('✅ n8n Eloquent Integration setup completed successfully!')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_setup_summary()
    {
        $this->artisan('n8n:setup', ['--api-secret' => 'test-secret'])
             ->expectsOutput('📋 Setup Summary:')
             ->expectsOutputToContain('API Secret: test-secret')
             ->expectsOutput('📁 Configuration: config/n8n-eloquent.php')
             ->expectsOutput('🌐 API Endpoints: /api/n8n/*')
             ->expectsOutput('📖 Next Steps:')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_can_run_setup_with_force_option()
    {
        $this->artisan('n8n:setup', ['--force' => true, '--api-secret' => 'forced-secret'])
             ->expectsOutput('Setting up n8n Eloquent Integration...')
             ->expectsOutputToContain('API secret: forced-secret')
             ->assertExitCode(0);
    }
} 