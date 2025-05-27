<?php

namespace N8n\Eloquent\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use N8n\Eloquent\Services\WebhookService;

class ModelObserver
{
    /**
     * The webhook service.
     *
     * @var \N8n\Eloquent\Services\WebhookService
     */
    protected $webhookService;

    /**
     * Create a new observer instance.
     *
     * @param  \N8n\Eloquent\Services\WebhookService  $webhookService
     * @return void
     */
    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle the Model "created" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function created(Model $model)
    {
        $this->handleModelEvent($model, 'created');
    }

    /**
     * Handle the Model "updated" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function updated(Model $model)
    {
        $this->handleModelEvent($model, 'updated');
    }

    /**
     * Handle the Model "deleted" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function deleted(Model $model)
    {
        $this->handleModelEvent($model, 'deleted');
    }

    /**
     * Handle the Model "restored" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function restored(Model $model)
    {
        $this->handleModelEvent($model, 'restored');
    }

    /**
     * Handle model events with transaction support.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $event
     * @return void
     */
    protected function handleModelEvent(Model $model, string $event)
    {
        $modelClass = get_class($model);
        
        // Check if transactions are enabled
        $transactionsEnabled = config('n8n-eloquent.events.transactions.enabled', true);
        $rollbackOnError = config('n8n-eloquent.events.transactions.rollback_on_error', true);
        
        try {
            if ($transactionsEnabled) {
                DB::beginTransaction();
            }
            
            // Trigger the webhook
            $this->webhookService->triggerWebhook($modelClass, $event, $model);
            
            if ($transactionsEnabled) {
                DB::commit();
            }
            
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->info("Processed {$event} event for model {$modelClass}", [
                    'model_id' => $model->getKey(),
                ]);
        } catch (\Throwable $e) {
            if ($transactionsEnabled && $rollbackOnError) {
                DB::rollBack();
            }
            
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error processing {$event} event for model {$modelClass}", [
                    'model_id' => $model->getKey(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
        }
    }
} 