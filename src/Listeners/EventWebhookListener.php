<?php

namespace Shortinc\N8nEloquent\Listeners;

use Shortinc\N8nEloquent\Services\WebhookService;

class EventWebhookListener
{
    /**
     * The webhook service.
     *
     * @var \Shortinc\N8nEloquent\Services\WebhookService
     */
    protected $webhookService;

    /**
     * Create a new listener instance.
     *
     * @param  \Shortinc\N8nEloquent\Services\WebhookService  $webhookService
     * @return void
     */
    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
        public function handle($event)
    {
        $eventClass = get_class($event);

        // Check if this event should be processed
        if (!$this->shouldProcessEvent($eventClass)) {
            return;
        }

        // Check if the current operation has n8n metadata to prevent loops
        if ($this->hasN8nMetadata()) {
            return;
        }

        try {
            // Trigger the webhook
            $this->webhookService->triggerEventWebhook($eventClass, $event);
            
        } catch (\Throwable $e) {
        }
    }

    /**
     * Check if the event should be processed based on configuration.
     *
     * @param  string  $eventClass
     * @return bool
     */
    protected function shouldProcessEvent(string $eventClass): bool
    {
        // Check if event webhooks are enabled globally
        if (!config('n8n-eloquent.events.webhooks.enabled', true)) {
            return false;
        }

        // Get event-specific configuration
        $eventConfig = config("n8n-eloquent.events.config.{$eventClass}", []);
        
        // Check if this specific event is disabled
        if (isset($eventConfig['webhook_enabled']) && !$eventConfig['webhook_enabled']) {
            return false;
        }

        return true;
    }

    /**
     * Check if the current operation has n8n metadata, indicating it's from n8n
     *
     * @return bool
     */
    private function hasN8nMetadata(): bool
    {
        return request()->has('n8n_metadata') || 
               request()->attributes->has('n8n_metadata') || 
               session()->has('n8n_metadata');
    }

    /**
     * Get n8n metadata from current request context, attributes, or session
     *
     * @return array
     */
    private function getN8nMetadata(): array
    {
        $eventData = [];
        if (request()->has('n8n_metadata')) {
            $eventData = request()->get('n8n_metadata');
        } elseif (request()->attributes->has('n8n_metadata')) {
            $eventData = request()->attributes->get('n8n_metadata');
        } elseif (session()->has('n8n_metadata')) {
            $eventData = session()->get('n8n_metadata');
            // Clear the session data after reading it
            session()->forget('n8n_metadata');
        }
        
        return $eventData;
    }

    /**
     * Serialize an event for logging.
     *
     * @param  mixed  $event
     * @return array
     */
    protected function serializeEvent($event): array
    {
        if (is_object($event)) {
            // Get all public properties
            $reflection = new \ReflectionClass($event);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            
            $data = [];
            foreach ($properties as $property) {
                $propertyName = $property->getName();
                $propertyValue = $property->getValue($event);
                
                // Convert to array if it's an object that can be serialized
                if (is_object($propertyValue)) {
                    if (method_exists($propertyValue, 'toArray')) {
                        $data[$propertyName] = $propertyValue->toArray();
                    } elseif (method_exists($propertyValue, '__toString')) {
                        $data[$propertyName] = (string) $propertyValue;
                    } else {
                        $data[$propertyName] = get_class($propertyValue);
                    }
                } else {
                    $data[$propertyName] = $propertyValue;
                }
            }
            
            return $data;
        }
        
        return [];
    }
} 