<?php

namespace Shortinc\N8nEloquent\Tests\Unit;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Shortinc\N8nEloquent\Services\ModelDiscoveryService;
use Shortinc\N8nEloquent\Tests\Fixtures\Models\TestUser;
use Shortinc\N8nEloquent\Tests\Fixtures\Models\TestUserCounter;
use Orchestra\Testbench\TestCase;

class ModelDiscoveryTest extends TestCase
{
    /**
     * The model discovery service.
     *
     * @var \N8n\Eloquent\Services\ModelDiscoveryService
     */
    protected $modelDiscovery;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the model discovery service
        $this->modelDiscovery = new ModelDiscoveryService($this->app);
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

    /**
     * Test model discovery in whitelist mode.
     *
     * @return void
     */
    public function testModelDiscoveryWhitelistMode()
    {
        // Configure whitelist mode with specific models
        Config::set('n8n-eloquent.models.mode', 'whitelist');
        Config::set('n8n-eloquent.models.whitelist', [
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser',
        ]);

        // Clear cache to ensure fresh discovery
        $this->modelDiscovery->clearCache();
        
        $models = $this->modelDiscovery->getModels();
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $models);
        $this->assertCount(1, $models);
        $this->assertContains('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser', $models->toArray());
    }

    /**
     * Test model discovery in blacklist mode.
     *
     * @return void
     */
    public function testModelDiscoveryBlacklistMode()
    {
        // Configure blacklist mode
        Config::set('n8n-eloquent.models.mode', 'blacklist');
        Config::set('n8n-eloquent.models.blacklist', [
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUserCounter',
        ]);

        // Clear cache to ensure fresh discovery
        $this->modelDiscovery->clearCache();
        
        $models = $this->modelDiscovery->getModels();
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $models);
        $this->assertContains('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser', $models->toArray());
        $this->assertNotContains('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUserCounter', $models->toArray());
    }

    /**
     * Test model discovery in all mode.
     *
     * @return void
     */
    public function testModelDiscoveryAllMode()
    {
        // Configure all mode
        Config::set('n8n-eloquent.models.mode', 'all');

        // Clear cache to ensure fresh discovery
        $this->modelDiscovery->clearCache();
        
        $models = $this->modelDiscovery->getModels();
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $models);
        $this->assertGreaterThanOrEqual(2, $models->count());
        $this->assertContains('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser', $models->toArray());
        $this->assertContains('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUserCounter', $models->toArray());
    }

    /**
     * Test getting model metadata for a valid model.
     *
     * @return void
     */
    public function testGetModelMetadataValidModel()
    {
        // Configure to include TestUser
        Config::set('n8n-eloquent.models.mode', 'whitelist');
        Config::set('n8n-eloquent.models.whitelist', [
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser',
        ]);

        // Clear cache to ensure fresh discovery
        $this->modelDiscovery->clearCache();
        
        $metadata = $this->modelDiscovery->getModelMetadata('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser');
        
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('class', $metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('table', $metadata);
        $this->assertArrayHasKey('primaryKey', $metadata);
        $this->assertArrayHasKey('fillable', $metadata);
        $this->assertArrayHasKey('events', $metadata);
        $this->assertArrayHasKey('property_events', $metadata);
        
        $this->assertEquals('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser', $metadata['class']);
        $this->assertEquals('TestUser', $metadata['name']);
        $this->assertEquals('test_users', $metadata['table']);
        $this->assertEquals('id', $metadata['primaryKey']);
        $this->assertEquals(['name', 'email', 'age'], $metadata['fillable']);
    }

    /**
     * Test getting model metadata for an invalid model.
     *
     * @return void
     */
    public function testGetModelMetadataInvalidModel()
    {
        $metadata = $this->modelDiscovery->getModelMetadata('NonExistentModel');
        
        $this->assertNull($metadata);
    }

    /**
     * Test getting model metadata for a model not in whitelist.
     *
     * @return void
     */
    public function testGetModelMetadataNotInWhitelist()
    {
        // Configure whitelist mode without TestUser
        Config::set('n8n-eloquent.models.mode', 'whitelist');
        Config::set('n8n-eloquent.models.whitelist', []);

        // Clear cache to ensure fresh discovery
        $this->modelDiscovery->clearCache();
        
        $metadata = $this->modelDiscovery->getModelMetadata('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser');
        
        $this->assertNull($metadata);
    }

    /**
     * Test cache functionality.
     *
     * @return void
     */
    public function testCacheFunctionality()
    {
        // Configure all mode
        Config::set('n8n-eloquent.models.mode', 'all');

        // Clear cache to ensure fresh discovery
        $this->modelDiscovery->clearCache();
        
        // First call should populate cache
        $models1 = $this->modelDiscovery->getModels();
        
        // Second call should use cache (same instance)
        $models2 = $this->modelDiscovery->getModels();
        
        $this->assertSame($models1, $models2);
        
        // Clear cache and get models again
        $this->modelDiscovery->clearCache();
        $models3 = $this->modelDiscovery->getModels();
        
        // Should be different instance but same content
        $this->assertNotSame($models1, $models3);
        $this->assertEquals($models1->toArray(), $models3->toArray());
    }

    /**
     * Test model configuration.
     *
     * @return void
     */
    public function testModelConfiguration()
    {
        // Configure specific model settings
        Config::set('n8n-eloquent.models.mode', 'whitelist');
        Config::set('n8n-eloquent.models.whitelist', [
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser',
        ]);
        Config::set('n8n-eloquent.models.config', [
            'N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser' => [
                'events' => ['created', 'updated'],
                'getters' => ['name'],
                'setters' => ['email'],
            ],
        ]);

        // Clear cache to ensure fresh discovery
        $this->modelDiscovery->clearCache();
        
        $metadata = $this->modelDiscovery->getModelMetadata('N8n\\Eloquent\\Tests\\Fixtures\\Models\\TestUser');
        
        $this->assertIsArray($metadata);
        $this->assertEquals(['created', 'updated'], $metadata['events']);
        $this->assertEquals(['name'], $metadata['property_events']['getters']);
        $this->assertEquals(['email'], $metadata['property_events']['setters']);
    }
} 