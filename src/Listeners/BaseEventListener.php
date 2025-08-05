<?php

namespace Shortinc\N8nEloquent\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shortinc\N8nEloquent\Events\BaseEvent;
use Shortinc\N8nEloquent\Services\WebhookService;

abstract class BaseEventListener
{
    /**
     * The webhook service.
     *
     * @var \N8n\Eloquent\Services\WebhookService
     */
    protected $webhookService;

    /**
     * Create a new listener instance.
     *
     * @param  \N8n\Eloquent\Services\WebhookService  $webhookService
     * @return void
     */
    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle the event.
     *
     * @param  \N8n\Eloquent\Events\BaseEvent  $event
     * @return void
     */
    public function handle(BaseEvent $event)
    {
        // Check if the event should be processed
        if (!$event->shouldProcess()) {
            return;
        }

        // Check if transactions are enabled
        $transactionsEnabled = config('n8n-eloquent.events.transactions.enabled', true);
        $rollbackOnError = config('n8n-eloquent.events.transactions.rollback_on_error', true);
        
        try {
            if ($transactionsEnabled) {
                DB::beginTransaction();
            }
            
            // Process the event
            $this->processEvent($event);
            
            if ($transactionsEnabled) {
                DB::commit();
            }
            
            $this->logSuccess($event);
            
        } catch (\Throwable $e) {
            if ($transactionsEnabled && $rollbackOnError) {
                DB::rollBack();
            }
            
            $this->logError($event, $e);
            
            // Re-throw if configured to do so
            if (config('n8n-eloquent.events.throw_on_error', false)) {
                throw $e;
            }
        }
    }

    /**
     * Process the specific event.
     *
     * @param  \N8n\Eloquent\Events\BaseEvent  $event
     * @return void
     */
    abstract protected function processEvent(BaseEvent $event);

    /**
     * Log successful event processing.
     *
     * @param  \N8n\Eloquent\Events\BaseEvent  $event
     * @return void
     */
    protected function logSuccess(BaseEvent $event)
    {
        if (!config('n8n-eloquent.logging.enabled', true)) {
            return;
        }

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->info("Successfully processed {$event->eventType} event for model {$event->getModelClass()}", [
                'model_id' => $event->getModelKey(),
                'event_type' => $event->eventType,
                'timestamp' => $event->timestamp->toISOString(),
            ]);
    }

    /**
     * Log event processing error.
     *
     * @param  \N8n\Eloquent\Events\BaseEvent  $event
     * @param  \Throwable  $exception
     * @return void
     */
    protected function logError(BaseEvent $event, \Throwable $exception)
    {
        if (!config('n8n-eloquent.logging.enabled', true)) {
            return;
        }

        Log::channel(config('n8n-eloquent.logging.channel'))
            ->error("Error processing {$event->eventType} event for model {$event->getModelClass()}", [
                'model_id' => $event->getModelKey(),
                'event_type' => $event->eventType,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'timestamp' => $event->timestamp->toISOString(),
            ]);
    }

    /**
     * Check if the event should be queued.
     *
     * @param  \N8n\Eloquent\Events\BaseEvent  $event
     * @return bool
     */
    protected function shouldQueue(BaseEvent $event): bool
    {
        $modelClass = $event->getModelClass();
        $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
        
        return $modelConfig['queue_events'] ?? config('n8n-eloquent.events.queue.enabled', false);
    }

    /**
     * Get the queue name for the event.
     *
     * @param  \N8n\Eloquent\Events\BaseEvent  $event
     * @return string
     */
    protected function getQueueName(BaseEvent $event): string
    {
        $modelClass = $event->getModelClass();
        $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
        
        return $modelConfig['queue_name'] ?? config('n8n-eloquent.events.queue.name', 'default');
    }
} 