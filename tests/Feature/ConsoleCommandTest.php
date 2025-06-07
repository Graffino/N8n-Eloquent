<?php

namespace Shortinc\N8nEloquent\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class ConsoleCommandTest extends TestCase
{
    use RefreshDatabase;

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
        $app['config']->set('n8n-eloquent.models.mode', 'whitelist');
        $app['config']->set('n8n-eloquent.models.whitelist', [
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser',
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUserCounter',
        ]);
    }

    /**
     * Test the register models command with whitelist mode.
     *
     * @return void
     */
    public function testRegisterModelsCommandWhitelist()
    {
        $this->artisan('n8n:register-models', ['--whitelist' => true])
             ->expectsOutput('Discovered 2 models for registration in whitelist mode.')
             ->expectsOutput('Registering model: N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser')
             ->expectsOutput('Registering model: N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUserCounter')
             ->expectsOutput('Successfully registered 2 models with n8n.')
             ->assertExitCode(0);
    }

    /**
     * Test the register models command with specific model.
     *
     * @return void
     */
    public function testRegisterModelsCommandSpecificModel()
    {
        $this->artisan('n8n:register-models', [
                '--model' => 'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser'
             ])
             ->expectsOutput('Registering model: N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser')
             ->assertExitCode(0);
    }

    /**
     * Test the register models command with invalid model.
     *
     * @return void
     */
    public function testRegisterModelsCommandInvalidModel()
    {
        $this->artisan('n8n:register-models', [
                '--model' => 'NonExistentModel'
             ])
             ->expectsOutput('Model NonExistentModel not found or not accessible.')
             ->assertExitCode(0);
    }

    /**
     * Test the register models command with all mode.
     *
     * @return void
     */
    public function testRegisterModelsCommandAllMode()
    {
        $this->artisan('n8n:register-models', ['--all' => true])
             ->expectsOutput('Discovered 2 models for registration in all mode.')
             ->expectsOutput('Successfully registered 2 models with n8n.')
             ->assertExitCode(0);
    }
} 