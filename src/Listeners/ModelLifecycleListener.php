<?php

namespace Shortinc\N8nEloquent\Listeners;

use Shortinc\N8nEloquent\Events\BaseEvent;
use Shortinc\N8nEloquent\Events\ModelLifecycleEvent;

class ModelLifecycleListener extends BaseEventListener
{
    /**
     * Process the lifecycle event.
     *
     * @param  \N8n\Eloquent\Events\BaseEvent  $event
     * @return void
     */
    protected function processEvent(BaseEvent $event)
    {
        if (!$event instanceof ModelLifecycleEvent) {
            return;
        }

        // Check if we should only trigger on specific attribute changes for update events
        if ($event->eventType === 'updated' && $this->hasWatchedAttributes($event)) {
            $watchedAttributes = $this->getWatchedAttributes($event);
            if (!$event->hasChanges($watchedAttributes)) {
                return; // Skip if no watched attributes changed
            }
        }

        // Trigger the webhook
        $this->webhookService->triggerWebhook(
            $event->getModelClass(),
            $event->eventType,
            $event->model,
            ['metadata' => $event->eventData]
        );
    }

    /**
     * Check if the model has watched attributes configured.
     *
     * @param  \N8n\Eloquent\Events\ModelLifecycleEvent  $event
     * @return bool
     */
    protected function hasWatchedAttributes(ModelLifecycleEvent $event): bool
    {
        $modelClass = $event->getModelClass();
        $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
        
        return !empty($modelConfig['watched_attributes']);
    }

    /**
     * Get the watched attributes for the model.
     *
     * @param  \N8n\Eloquent\Events\ModelLifecycleEvent  $event
     * @return array
     */
    protected function getWatchedAttributes(ModelLifecycleEvent $event): array
    {
        $modelClass = $event->getModelClass();
        $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
        
        return $modelConfig['watched_attributes'] ?? [];
    }
} 