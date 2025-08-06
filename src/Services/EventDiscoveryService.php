<?php

namespace Shortinc\N8nEloquent\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use Illuminate\Foundation\Events\Dispatchable;

class EventDiscoveryService
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Cache of discovered events.
     *
     * @var \Illuminate\Support\Collection|null
     */
    protected $discoveredEvents = null;

    /**
     * Create a new EventDiscoveryService instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->files = new Filesystem();
    }

    /**
     * Get all Laravel events in the application.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getEvents(): Collection
    {
        if ($this->discoveredEvents !== null) {
            return $this->discoveredEvents;
        }

        $eventNamespace = config('n8n-eloquent.events.namespace', 'App\\Events');
        $eventDirectory = config('n8n-eloquent.events.directory', app_path('Events'));
        $mode = config('n8n-eloquent.events.discovery.mode', 'all');
        $whitelist = config('n8n-eloquent.events.discovery.whitelist', []);
        $blacklist = config('n8n-eloquent.events.discovery.blacklist', []);

        // If directory doesn't exist, return empty collection
        if (!$this->files->exists($eventDirectory)) {
            return collect([]);
        }

        // Get all PHP files in the events directory
        $files = $this->files->glob("{$eventDirectory}/*.php");

        // Transform file paths to class names
        $events = collect($files)->map(function ($file) use ($eventNamespace, $eventDirectory) {
            $relativePath = Str::after($file, $eventDirectory . DIRECTORY_SEPARATOR);
            $className = $eventNamespace . '\\' . Str::beforeLast($relativePath, '.php');
            
            return $className;
        });

        // Filter to include only Laravel events
        $laravelEvents = $events->filter(function ($className) {
            if (!class_exists($className)) {
                return false;
            }

            try {
                $reflection = new ReflectionClass($className);
                return !$reflection->isAbstract() && in_array(Dispatchable::class, $reflection->getTraitNames());
            } catch (\Throwable $e) {
                Log::channel(config('n8n-eloquent.logging.channel'))
                    ->warning("Error checking if {$className} is a Laravel event", [
                        'error' => $e->getMessage(),
                    ]);
                return false;
            }
        });

        // Apply whitelist/blacklist filtering
        if ($mode === 'whitelist' && !empty($whitelist)) {
            $laravelEvents = $laravelEvents->filter(function ($className) use ($whitelist) {
                return in_array($className, $whitelist);
            });
        } elseif ($mode === 'blacklist' && !empty($blacklist)) {
            $laravelEvents = $laravelEvents->filter(function ($className) use ($blacklist) {
                return !in_array($className, $blacklist);
            });
        }

        $this->discoveredEvents = $laravelEvents;
        return $this->discoveredEvents;
    }

    /**
     * Get metadata for a specific event.
     *
     * @param  string  $eventClass
     * @return array|null
     */
    public function getEventMetadata(string $eventClass): ?array
    {
        // Check if the event is discoverable
        if (!$this->getEvents()->contains($eventClass)) {
            return null;
        }

        try {
            // Create reflection class
            $reflection = new ReflectionClass($eventClass);
            
            // Get event properties
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            $propertyNames = array_map(function ($property) {
                return $property->getName();
            }, $properties);

            // Get event configuration
            $eventConfig = config("n8n-eloquent.events.config.{$eventClass}", []);
            
            return [
                'class' => $eventClass,
                'name' => $reflection->getShortName(),
                'properties' => $propertyNames,
                'config' => $eventConfig,
            ];
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting metadata for event {$eventClass}", [
                    'error' => $e->getMessage(),
                ]);
            return null;
        }
    }

    /**
     * Search events by query.
     *
     * @param  string  $query
     * @return \Illuminate\Support\Collection
     */
    public function searchEvents(string $query): Collection
    {
        $events = $this->getEvents();
        
        return $events->filter(function ($eventClass) use ($query) {
            $query = strtolower($query);
            $eventName = class_basename($eventClass);
            $eventNamespace = strtolower($eventClass);
            
            return str_contains(strtolower($eventName), $query) ||
                   str_contains($eventNamespace, $query);
        });
    }
} 