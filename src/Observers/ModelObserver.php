<?php

namespace N8n\Eloquent\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use N8n\Eloquent\Events\ModelLifecycleEvent;

class ModelObserver
{

    /**
     * Handle the Model "created" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function created(Model $model)
    {
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
        Event::dispatch(new ModelLifecycleEvent($model, 'updated'));
    }

    /**
     * Handle the Model "deleted" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function deleted(Model $model)
    {
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
        Event::dispatch(new ModelLifecycleEvent($model, 'saved'));
    }
} 