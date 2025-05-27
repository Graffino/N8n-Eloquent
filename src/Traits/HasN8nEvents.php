<?php

namespace N8n\Eloquent\Traits;

use Illuminate\Support\Facades\App;
use N8n\Eloquent\Services\WebhookService;

trait HasN8nEvents
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasN8nEvents()
    {
        // Register the observer
        static::observe(App::make(\N8n\Eloquent\Observers\ModelObserver::class));
    }

    /**
     * Get a property value.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $value = parent::__get($key);
        
        // Check if property events are enabled
        $propertyEventsEnabled = config('n8n-eloquent.events.property_events.enabled', true);
        
        if ($propertyEventsEnabled) {
            $modelClass = get_class($this);
            
            // Get model-specific configuration
            $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
            $defaultPropertyEvents = config('n8n-eloquent.events.property_events.default', []);
            $getters = $modelConfig['getters'] ?? $defaultPropertyEvents;
            
            // Check if this property should trigger an event
            if (in_array($key, $getters)) {
                $webhookService = App::make(WebhookService::class);
                $webhookService->triggerWebhook($modelClass, 'get', $this);
            }
        }
        
        return $value;
    }

    /**
     * Set a property value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        // Check if property events are enabled
        $propertyEventsEnabled = config('n8n-eloquent.events.property_events.enabled', true);
        
        if ($propertyEventsEnabled) {
            $modelClass = get_class($this);
            
            // Get model-specific configuration
            $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
            $defaultPropertyEvents = config('n8n-eloquent.events.property_events.default', []);
            $setters = $modelConfig['setters'] ?? $defaultPropertyEvents;
            
            // Check if this property should trigger an event
            if (in_array($key, $setters)) {
                // Store the old value before setting the new one
                $oldValue = $this->getAttribute($key);
                
                // Set the value
                parent::__set($key, $value);
                
                // Trigger the webhook with both old and new values
                $webhookService = App::make(WebhookService::class);
                $this->setAttribute('_n8n_old_value', $oldValue);
                $this->setAttribute('_n8n_property_name', $key);
                $webhookService->triggerWebhook($modelClass, 'set', $this);
                $this->offsetUnset('_n8n_old_value');
                $this->offsetUnset('_n8n_property_name');
                
                return;
            }
        }
        
        parent::__set($key, $value);
    }
} 