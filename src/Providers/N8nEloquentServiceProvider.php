<?php

namespace N8n\Eloquent\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use N8n\Eloquent\Console\Commands\RegisterModelsCommand;
use N8n\Eloquent\Console\Commands\SetupCommand;
use N8n\Eloquent\Console\Commands\StatusCommand;
use N8n\Eloquent\Console\Commands\MigrateWebhookSubscriptionsCommand;
use N8n\Eloquent\Console\Commands\HealthCheckCommand;
use N8n\Eloquent\Console\Commands\RecoveryCommand;
use N8n\Eloquent\Console\Commands\CleanupCommand;
use N8n\Eloquent\Events\ModelLifecycleEvent;
use N8n\Eloquent\Events\ModelPropertyEvent;
use N8n\Eloquent\Listeners\ModelLifecycleListener;
use N8n\Eloquent\Listeners\ModelPropertyListener;
use N8n\Eloquent\Services\ModelDiscoveryService;
use N8n\Eloquent\Services\WebhookService;
use N8n\Eloquent\Services\SubscriptionRecoveryService;

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

        $this->app->singleton(SubscriptionRecoveryService::class, function ($app) {
            return new SubscriptionRecoveryService($app->make(WebhookService::class));
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

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'n8n-eloquent-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Register event listeners
        $this->registerEventListeners();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterModelsCommand::class,
                SetupCommand::class,
                StatusCommand::class,
                MigrateWebhookSubscriptionsCommand::class,
                HealthCheckCommand::class,
                RecoveryCommand::class,
                CleanupCommand::class,
            ]);
        }
    }

    /**
     * Register event listeners.
     *
     * @return void
     */
    protected function registerEventListeners()
    {
        // Get the model discovery service
        $discoveryService = $this->app->make(ModelDiscoveryService::class);
        
        // Get all models based on config settings (whitelist/blacklist/all)
        $models = $discoveryService->getModels();
        
        // Get default events from config
        $defaultEvents = config('n8n-eloquent.events.default', ['created', 'updated', 'deleted']);
        
        foreach ($models as $modelClass) {
            // Get model-specific config
            $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
            
            // Get events for this specific model, fallback to default events
            $events = $modelConfig['events'] ?? $defaultEvents;
            
            // Register the events for this model
            foreach ($events as $event) {
                $modelClass::$event(function ($model) use ($event) {
                    event(new ModelLifecycleEvent($model, $event));
                });
            }
            
            // If property events are enabled, register those too
            if (config('n8n-eloquent.events.property_events.enabled', true)) {
                $getters = $modelConfig['getters'] ?? [];
                $setters = $modelConfig['setters'] ?? [];
                
                // Register property events
                foreach ($getters as $property) {
                    $modelClass::retrieved(function ($model) use ($property) {
                        event(new ModelPropertyEvent($model, 'get', $property));
                    });
                }
                
                foreach ($setters as $property) {
                    $modelClass::saving(function ($model) use ($property) {
                        if ($model->isDirty($property)) {
                            event(new ModelPropertyEvent($model, 'set', $property));
                        }
                    });
                }
            }
        }

        // Register the event listeners
        Event::listen(ModelLifecycleEvent::class, ModelLifecycleListener::class);
        Event::listen(ModelPropertyEvent::class, ModelPropertyListener::class);
    }
} 