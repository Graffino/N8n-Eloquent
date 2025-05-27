<?php

namespace N8n\Eloquent\Events;

use Illuminate\Database\Eloquent\Model;

class ModelLifecycleEvent extends BaseEvent
{
    /**
     * The original model attributes before the change.
     *
     * @var array|null
     */
    public $originalAttributes;

    /**
     * The changed attributes.
     *
     * @var array
     */
    public $changedAttributes;

    /**
     * Create a new model lifecycle event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $eventType
     * @param  array  $eventData
     * @return void
     */
    public function __construct(Model $model, string $eventType, array $eventData = [])
    {
        parent::__construct($model, $eventType, $eventData);

        // Capture original and changed attributes for update events
        if ($eventType === 'updated') {
            $this->originalAttributes = $model->getOriginal();
            $this->changedAttributes = $model->getChanges();
        }
    }

    /**
     * Get the event payload for n8n with lifecycle-specific data.
     *
     * @return array
     */
    public function getPayload(): array
    {
        $payload = parent::getPayload();

        // Add lifecycle-specific data
        if ($this->eventType === 'updated') {
            $payload['original_attributes'] = $this->originalAttributes;
            $payload['changed_attributes'] = $this->changedAttributes;
        }

        return $payload;
    }

    /**
     * Check if the event has specific attribute changes.
     *
     * @param  array  $attributes
     * @return bool
     */
    public function hasChanges(array $attributes = []): bool
    {
        if ($this->eventType !== 'updated') {
            return false;
        }

        if (empty($attributes)) {
            return !empty($this->changedAttributes);
        }

        return !empty(array_intersect($attributes, array_keys($this->changedAttributes)));
    }

    /**
     * Get the old value of a specific attribute.
     *
     * @param  string  $attribute
     * @return mixed
     */
    public function getOriginal(string $attribute)
    {
        return $this->originalAttributes[$attribute] ?? null;
    }

    /**
     * Get the new value of a specific attribute.
     *
     * @param  string  $attribute
     * @return mixed
     */
    public function getChanged(string $attribute)
    {
        return $this->changedAttributes[$attribute] ?? null;
    }
} 