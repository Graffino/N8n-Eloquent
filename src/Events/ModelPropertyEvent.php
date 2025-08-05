<?php

namespace Shortinc\N8nEloquent\Events;

use Illuminate\Database\Eloquent\Model;

class ModelPropertyEvent extends BaseEvent
{
    /**
     * The property name that was accessed.
     *
     * @var string
     */
    public $propertyName;

    /**
     * The old value (for setter events).
     *
     * @var mixed
     */
    public $oldValue;

    /**
     * The new value (for setter events).
     *
     * @var mixed
     */
    public $newValue;

    /**
     * The current value (for getter events).
     *
     * @var mixed
     */
    public $currentValue;

    /**
     * Create a new model property event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $eventType  'get' or 'set'
     * @param  string  $propertyName
     * @param  array  $eventData
     * @return void
     */
    public function __construct(Model $model, string $eventType, string $propertyName, array $eventData = [])
    {
        parent::__construct($model, $eventType, $eventData);

        $this->propertyName = $propertyName;

        if ($eventType === 'get') {
            $this->currentValue = $model->getAttribute($propertyName);
        } elseif ($eventType === 'set') {
            $this->oldValue = $eventData['old_value'] ?? null;
            $this->newValue = $eventData['new_value'] ?? null;
        }
    }

    /**
     * Get the event payload for n8n with property-specific data.
     *
     * @return array
     */
    public function getPayload(): array
    {
        $payload = parent::getPayload();

        // Add property-specific data
        $payload['property_name'] = $this->propertyName;

        if ($this->eventType === 'get') {
            $payload['current_value'] = $this->currentValue;
        } elseif ($this->eventType === 'set') {
            $payload['old_value'] = $this->oldValue;
            $payload['new_value'] = $this->newValue;
        }

        return $payload;
    }

    /**
     * Check if this property event should be processed based on configuration.
     *
     * @return bool
     */
    public function shouldProcess(): bool
    {
        $modelClass = $this->getModelClass();
        
        // Check if property events are enabled globally
        if (!config('n8n-eloquent.events.property_events.enabled', true)) {
            return false;
        }

        // Get model-specific configuration
        $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
        $defaultPropertyEvents = config('n8n-eloquent.events.property_events.default', []);
        
        if ($this->eventType === 'get') {
            $allowedProperties = $modelConfig['getters'] ?? $defaultPropertyEvents;
        } else {
            $allowedProperties = $modelConfig['setters'] ?? $defaultPropertyEvents;
        }

        return in_array($this->propertyName, $allowedProperties);
    }

    /**
     * Check if the property value has actually changed.
     *
     * @return bool
     */
    public function hasValueChanged(): bool
    {
        if ($this->eventType !== 'set') {
            return false;
        }

        return $this->oldValue !== $this->newValue;
    }
} 