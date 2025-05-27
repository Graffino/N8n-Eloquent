<?php

namespace N8n\Eloquent\Listeners;

use N8n\Eloquent\Events\BaseEvent;
use N8n\Eloquent\Events\ModelPropertyEvent;

class ModelPropertyListener extends BaseEventListener
{


    /**
     * Check if the property event is rate limited.
     *
     * @param  \N8n\Eloquent\Events\ModelPropertyEvent  $event
     * @return bool
     */
    protected function isRateLimited(ModelPropertyEvent $event): bool
    {
        $rateLimitEnabled = config('n8n-eloquent.events.property_events.rate_limit.enabled', true);
        
        if (!$rateLimitEnabled) {
            return false;
        }

        $cacheKey = sprintf(
            'n8n_property_event:%s:%s:%s:%s',
            $event->getModelClass(),
            $event->getModelKey(),
            $event->propertyName,
            $event->eventType
        );

        $maxAttempts = config('n8n-eloquent.events.property_events.rate_limit.max_attempts', 10);
        $decayMinutes = config('n8n-eloquent.events.property_events.rate_limit.decay_minutes', 1);

        return app('cache')->store()->has($cacheKey) && 
               app('cache')->store()->get($cacheKey, 0) >= $maxAttempts;
    }

    /**
     * Record the property event for rate limiting.
     *
     * @param  \N8n\Eloquent\Events\ModelPropertyEvent  $event
     * @return void
     */
    protected function recordEventForRateLimit(ModelPropertyEvent $event)
    {
        $rateLimitEnabled = config('n8n-eloquent.events.property_events.rate_limit.enabled', true);
        
        if (!$rateLimitEnabled) {
            return;
        }

        $cacheKey = sprintf(
            'n8n_property_event:%s:%s:%s:%s',
            $event->getModelClass(),
            $event->getModelKey(),
            $event->propertyName,
            $event->eventType
        );

        $decayMinutes = config('n8n-eloquent.events.property_events.rate_limit.decay_minutes', 1);
        $currentCount = app('cache')->store()->get($cacheKey, 0);
        
        app('cache')->store()->put($cacheKey, $currentCount + 1, now()->addMinutes($decayMinutes));
    }

    /**
     * Process the property event with rate limiting.
     *
     * @param  \N8n\Eloquent\Events\BaseEvent  $event
     * @return void
     */
    protected function processEvent(BaseEvent $event)
    {
        if (!$event instanceof ModelPropertyEvent) {
            return;
        }

        // For setter events, check if the value actually changed
        if ($event->eventType === 'set' && !$event->hasValueChanged()) {
            $skipUnchanged = config('n8n-eloquent.events.property_events.skip_unchanged', true);
            if ($skipUnchanged) {
                return; // Skip if value didn't change
            }
        }

        // Check rate limiting for property events
        if ($this->isRateLimited($event)) {
            return;
        }

        // Trigger the webhook
        $this->webhookService->triggerWebhook(
            $event->getModelClass(),
            $event->eventType,
            $event->model,
            $event->getPayload()
        );

        // Record for rate limiting
        $this->recordEventForRateLimit($event);
    }
} 