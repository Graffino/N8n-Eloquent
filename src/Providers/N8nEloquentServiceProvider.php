<?php

namespace N8n\Eloquent\Providers;

use Illuminate\Support\ServiceProvider;
use N8n\Eloquent\Console\Commands\RegisterModelsCommand;
use N8n\Eloquent\Services\ModelDiscoveryService;
use N8n\Eloquent\Services\WebhookService;

class N8nEloquentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/n8n-eloquent.php', 'n8n-eloquent'
        );

        $this->app->singleton(ModelDiscoveryService::class, function ($app) {
            return new ModelDiscoveryService($app);
        });

        $this->app->singleton(WebhookService::class, function ($app) {
            return new WebhookService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/n8n-eloquent.php' => config_path('n8n-eloquent.php'),
        ], 'n8n-eloquent-config');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterModelsCommand::class,
            ]);
        }
    }
} 