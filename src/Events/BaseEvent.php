<?php

namespace Shortinc\N8nEloquent\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class BaseEvent
{
    use Dispatchable, SerializesModels;

    /**
     * The model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * The event type.
     *
     * @var string
     */
    public $eventType;

    /**
     * Additional event data.
     *
     * @var array
     */
    public $eventData;

    /**
     * The timestamp when the event occurred.
     *
     * @var \Carbon\Carbon
     */
    public $timestamp;

    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $eventType
     * @param  array  $eventData
     * @return void
     */
    public function __construct(Model $model, string $eventType, array $eventData = [])
    {
        $this->model = $model;
        $this->eventType = $eventType;
        $this->eventData = $eventData;
        $this->timestamp = now();
    }

    /**
     * Get the model class name.
     *
     * @return string
     */
    public function getModelClass(): string
    {
        return get_class($this->model);
    }

    /**
     * Get the model primary key value.
     *
     * @return mixed
     */
    public function getModelKey()
    {
        return $this->model->getKey();
    }

    /**
     * Get the event payload for n8n.
     *
     * @return array
     */
    public function getPayload(): array
    {
        return [
            'event_type' => $this->eventType,
            'model_class' => $this->getModelClass(),
            'model_key' => $this->getModelKey(),
            'model_data' => $this->model->toArray(),
            'event_data' => $this->eventData,
            'timestamp' => $this->timestamp->toISOString(),
        ];
    }

    /**
     * Check if this event should be processed based on configuration.
     *
     * @return bool
     */
    public function shouldProcess(): bool
    {
        $modelClass = $this->getModelClass();
        
        // Check if events are enabled globally
        if (!config('n8n-eloquent.events.enabled', true)) {
            return false;
        }

        // Get model-specific configuration
        $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
        $defaultEvents = config('n8n-eloquent.events.default', ['created', 'updated', 'deleted']);
        $allowedEvents = $modelConfig['events'] ?? $defaultEvents;

        return in_array($this->eventType, $allowedEvents);
    }
} 