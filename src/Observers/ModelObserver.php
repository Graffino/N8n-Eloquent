<?php

namespace Shortinc\N8nEloquent\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Shortinc\N8nEloquent\Events\ModelLifecycleEvent;

class ModelObserver
{
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
     * Handle the Model "created" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function created(Model $model)
    {
        // If this operation has n8n metadata, don't dispatch events to prevent loops
        if ($this->hasN8nMetadata()) {
            \Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('ModelObserver created event skipped - n8n metadata detected', [
                    'model' => get_class($model),
                    'eventData' => $this->getN8nMetadata(),
                ]);
            return;
        }

        Event::dispatch(new ModelLifecycleEvent($model, 'created'));
    }

    /**
     * Handle the Model "updated" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function updated(Model $model)
    {
        // If this operation has n8n metadata, don't dispatch events to prevent loops
        if ($this->hasN8nMetadata()) {
            \Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('ModelObserver updated event skipped - n8n metadata detected', [
                    'model' => get_class($model),
                    'eventData' => $this->getN8nMetadata(),
                ]);
            return;
        }

        // Get metadata from current request context, attributes, or session
        $eventData = $this->getN8nMetadata();
        
        Event::dispatch(new ModelLifecycleEvent($model, 'updated', $eventData));
    }

    /**
     * Handle the Model "deleted" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function deleted(Model $model)
    {
        // If this operation has n8n metadata, don't dispatch events to prevent loops
        if ($this->hasN8nMetadata()) {
            \Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('ModelObserver deleted event skipped - n8n metadata detected', [
                    'model' => get_class($model),
                    'eventData' => $this->getN8nMetadata(),
                ]);
            return;
        }

        Event::dispatch(new ModelLifecycleEvent($model, 'deleted'));
    }

    /**
     * Handle the Model "restored" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function restored(Model $model)
    {
        // If this operation has n8n metadata, don't dispatch events to prevent loops
        if ($this->hasN8nMetadata()) {
            \Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('ModelObserver restored event skipped - n8n metadata detected', [
                    'model' => get_class($model),
                    'eventData' => $this->getN8nMetadata(),
                ]);
            return;
        }

        Event::dispatch(new ModelLifecycleEvent($model, 'restored'));
    }

    /**
     * Handle the Model "saving" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function saving(Model $model)
    {
        // If this operation has n8n metadata, don't dispatch events to prevent loops
        if ($this->hasN8nMetadata()) {
            \Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('ModelObserver saving event skipped - n8n metadata detected', [
                    'model' => get_class($model),
                    'eventData' => $this->getN8nMetadata(),
                ]);
            return;
        }

        Event::dispatch(new ModelLifecycleEvent($model, 'saving'));
    }

    /**
     * Handle the Model "saved" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function saved(Model $model)
    {
        // If this operation has n8n metadata, don't dispatch events to prevent loops
        if ($this->hasN8nMetadata()) {
            \Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('ModelObserver saved event skipped - n8n metadata detected', [
                    'model' => get_class($model),
                    'eventData' => $this->getN8nMetadata(),
                ]);
            return;
        }

        Event::dispatch(new ModelLifecycleEvent($model, 'saved'));
    }
} 