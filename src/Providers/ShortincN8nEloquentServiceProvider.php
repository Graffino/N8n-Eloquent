<?php

namespace Shortinc\N8nEloquent\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Shortinc\N8nEloquent\Console\Commands\RegisterModelsCommand;
use Shortinc\N8nEloquent\Console\Commands\SetupCommand;
use Shortinc\N8nEloquent\Console\Commands\StatusCommand;
use Shortinc\N8nEloquent\Console\Commands\MigrateWebhookSubscriptionsCommand;
use Shortinc\N8nEloquent\Console\Commands\HealthCheckCommand;
use Shortinc\N8nEloquent\Console\Commands\RecoveryCommand;
use Shortinc\N8nEloquent\Console\Commands\CleanupCommand;
use Shortinc\N8nEloquent\Events\ModelLifecycleEvent;
use Shortinc\N8nEloquent\Events\ModelPropertyEvent;
use Shortinc\N8nEloquent\Listeners\ModelLifecycleListener;
use Shortinc\N8nEloquent\Listeners\ModelPropertyListener;
use Shortinc\N8nEloquent\Listeners\EventWebhookListener;
use Shortinc\N8nEloquent\Observers\ModelObserver;
use Shortinc\N8nEloquent\Services\ModelDiscoveryService;
use Shortinc\N8nEloquent\Services\EventDiscoveryService;
use Shortinc\N8nEloquent\Services\WebhookService;
use Shortinc\N8nEloquent\Services\SubscriptionRecoveryService;

class ShortincN8nEloquentServiceProvider extends ServiceProvider
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

        $this->app->singleton(EventDiscoveryService::class, function ($app) {
            return new EventDiscoveryService($app);
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
            
            // Register the ModelObserver for this model
            $modelClass::observe(\Shortinc\N8nEloquent\Observers\ModelObserver::class);
            
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
        
        // Register event webhook listener for all discovered events
        $this->registerEventWebhookListeners();
    }
    
    /**
     * Register webhook listeners for Laravel events.
     *
     * @return void
     */
    protected function registerEventWebhookListeners()
    {
        // Get the event discovery service
        $eventDiscoveryService = $this->app->make(EventDiscoveryService::class);
        
        // Get all events based on config settings
        $events = $eventDiscoveryService->getEvents();
        
        // Register the EventWebhookListener for each event
        foreach ($events as $eventClass) {
            Event::listen($eventClass, EventWebhookListener::class);
        }
    }
} 