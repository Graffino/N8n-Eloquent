<?php

namespace N8n\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class WebhookSubscription extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'n8n_webhook_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'model_class',
        'events',
        'webhook_url',
        'properties',
        'active',
        'last_triggered_at',
        'trigger_count',
        'last_error',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'events' => 'array',
        'properties' => 'array',
        'active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
        'last_error' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'last_error',
    ];

    /**
     * Validation rules for the model.
     *
     * @return array<string, string>
     */
    public static function validationRules(): array
    {
        return [
            'model_class' => 'required|string|max:255',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|in:created,updated,deleted,saved,saving',
            'webhook_url' => 'required|url|max:2048',
            'properties' => 'nullable|array',
            'active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active subscriptions.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include inactive subscriptions.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('active', false);
    }

    /**
     * Scope a query to filter by model class.
     *
     * @param  Builder  $query
     * @param  string  $modelClass
     * @return Builder
     */
    public function scopeForModel(Builder $query, string $modelClass): Builder
    {
        return $query->where('model_class', $modelClass);
    }

    /**
     * Scope a query to filter by event.
     *
     * @param  Builder  $query
     * @param  string  $event
     * @return Builder
     */
    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->whereJsonContains('events', $event);
    }

    /**
     * Scope a query to filter by model and event.
     *
     * @param  Builder  $query
     * @param  string  $modelClass
     * @param  string  $event
     * @return Builder
     */
    public function scopeForModelEvent(Builder $query, string $modelClass, string $event): Builder
    {
        return $query->forModel($modelClass)->forEvent($event);
    }

    /**
     * Scope a query to filter subscriptions that haven't been triggered recently.
     *
     * @param  Builder  $query
     * @param  int  $hours
     * @return Builder
     */
    public function scopeStale(Builder $query, int $hours = 24): Builder
    {
        return $query->where(function ($query) use ($hours) {
            $query->whereNull('last_triggered_at')
                  ->orWhere('last_triggered_at', '<', Carbon::now()->subHours($hours));
        });
    }

    /**
     * Scope a query to filter subscriptions with errors.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->whereNotNull('last_error');
    }

    /**
     * Check if the subscription has a specific event.
     *
     * @param  string  $event
     * @return bool
     */
    public function hasEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Record a successful webhook trigger.
     *
     * @return void
     */
    public function recordTrigger(): void
    {
        $this->increment('trigger_count');
        $this->update([
            'last_triggered_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Record a webhook error.
     *
     * @param  array  $error
     * @return void
     */
    public function recordError(array $error): void
    {
        $this->update([
            'last_error' => array_merge($error, [
                'occurred_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Activate the subscription.
     *
     * @return void
     */
    public function activate(): void
    {
        $this->update(['active' => true]);
    }

    /**
     * Deactivate the subscription.
     *
     * @return void
     */
    public function deactivate(): void
    {
        $this->update(['active' => false]);
    }

    /**
     * Check if the subscription is healthy (active and no recent errors).
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        return $this->active && empty($this->last_error);
    }

    /**
     * Get the subscription as an array in the legacy format.
     *
     * @return array
     */
    public function toLegacyArray(): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model_class,
            'events' => $this->events,
            'webhook_url' => $this->webhook_url,
            'properties' => $this->properties ?? [],
            'active' => $this->active,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
} 