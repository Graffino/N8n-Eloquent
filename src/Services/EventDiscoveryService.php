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

        $eventNamespace = env('N8N_ELOQUENT_EVENTS_NAMESPACE', 'App\\Events');
        $eventDirectory = env('N8N_ELOQUENT_EVENTS_DIRECTORY', app_path('Events'));

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

        $this->discoveredEvents = $laravelEvents;
        return $this->discoveredEvents;
    }

    /**
     * Get events available for listening (similar to trigger node).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getListenableEvents(): Collection
    {
        $allEvents = $this->getEvents();
        $availableEvents = config('n8n-eloquent.event_listeners.available', []);
        $mode = config('n8n-eloquent.event_listeners.discovery.mode', 'all');
        $whitelist = config('n8n-eloquent.event_listeners.discovery.whitelist', []);
        $blacklist = config('n8n-eloquent.event_listeners.discovery.blacklist', []);

        // If available list is specified, use only those events
        if (!empty($availableEvents)) {
            return $allEvents->filter(function ($eventClass) use ($availableEvents) {
                return in_array($eventClass, $availableEvents);
            });
        }

        // Apply whitelist/blacklist filtering
        if ($mode === 'whitelist' && !empty($whitelist)) {
            return $allEvents->filter(function ($eventClass) use ($whitelist) {
                return in_array($eventClass, $whitelist);
            });
        } elseif ($mode === 'blacklist' && !empty($blacklist)) {
            return $allEvents->filter(function ($eventClass) use ($blacklist) {
                return !in_array($eventClass, $blacklist);
            });
        }

        // Default to all events
        return $allEvents;
    }

    /**
     * Get events available for dispatching (similar to job dispatcher).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDispatchableEvents(): Collection
    {
        $allEvents = $this->getEvents();
        $availableEvents = config('n8n-eloquent.event_dispatchers.available', []);

        // Only return events that are explicitly listed as available
        return $allEvents->filter(function ($eventClass) use ($availableEvents) {
            return in_array($eventClass, $availableEvents);
        });
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

            // Get event configuration (check both listener and dispatcher configs)
            $listenerConfig = config("n8n-eloquent.event_listeners.config.{$eventClass}", []);
            $dispatcherConfig = config("n8n-eloquent.event_dispatchers.config.{$eventClass}", []);
            $eventConfig = array_merge($listenerConfig, $dispatcherConfig);
            
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

    /**
     * Check if an event is configured as available for listening.
     *
     * @param  string  $eventClass
     * @return bool
     */
    public function isEventListenable(string $eventClass): bool
    {
        return $this->getListenableEvents()->contains($eventClass);
    }

    /**
     * Check if an event is configured as available for dispatching.
     *
     * @param  string  $eventClass
     * @return bool
     */
    public function isEventDispatchable(string $eventClass): bool
    {
        return $this->getDispatchableEvents()->contains($eventClass);
    }

    /**
     * Get event parameters for dispatching.
     *
     * @param  string  $eventClass
     * @return array
     */
    public function getEventParameters(string $eventClass): array
    {
        if (!class_exists($eventClass)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($eventClass);
            $constructor = $reflection->getConstructor();
            
            if (!$constructor) {
                return [];
            }

            $parameters = [];
            foreach ($constructor->getParameters() as $parameter) {
                $paramName = $parameter->getName();
                $paramType = $parameter->getType();
                $isRequired = !$parameter->isOptional();
                $defaultValue = $parameter->isOptional() ? $parameter->getDefaultValue() : null;

                $parameters[] = [
                    'name' => $paramName,
                    'type' => $paramType ? $paramType->getName() : 'mixed',
                    'required' => $isRequired,
                    'default' => $defaultValue,
                    'label' => ucfirst(str_replace('_', ' ', $paramName)),
                ];
            }

            return $parameters;
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting parameters for event {$eventClass}", [
                    'error' => $e->getMessage(),
                ]);
            return [];
        }
    }

    /**
     * Create an event instance with parameters.
     *
     * @param  string  $eventClass
     * @param  array  $parameters
     * @param  array  $metadata
     * @return object|null
     */
    public function createEventInstance(string $eventClass, array $parameters = [], array $metadata = []): ?object
    {
        if (!class_exists($eventClass)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($eventClass);
            $constructor = $reflection->getConstructor();
            
            if (!$constructor) {
                // Event has no constructor, create instance without parameters
                $instance = $reflection->newInstance();
                
                // Set metadata as public properties if they exist
                foreach ($metadata as $key => $value) {
                    if ($reflection->hasProperty($key)) {
                        $property = $reflection->getProperty($key);
                        if ($property->isPublic()) {
                            $property->setValue($instance, $value);
                        }
                    }
                }
                
                return $instance;
            }

            // Get constructor parameters
            $constructorParams = $constructor->getParameters();
            $args = [];

            // Build arguments array based on constructor parameters
            foreach ($constructorParams as $param) {
                $paramName = $param->getName();
                $paramType = $param->getType();
                
                if (isset($parameters[$paramName])) {
                    $paramValue = $parameters[$paramName];
                    
                    // If the parameter is a type-hinted model class, try to resolve it
                    if ($paramType && !$paramType->isBuiltin()) {
                        $typeName = $paramType->getName();
                        
                        // Check if it's a model class
                        if (class_exists($typeName) && is_subclass_of($typeName, 'Illuminate\Database\Eloquent\Model')) {
                            // If we have an array/object with an 'id' field, try to find the model
                            if (is_array($paramValue) && isset($paramValue['id'])) {
                                try {
                                    $model = $typeName::find($paramValue['id']);
                                    if ($model) {
                                        $args[] = $model;
                                    } else {
                                        // If model not found, pass null or create a new instance with the data
                                        $args[] = null;
                                    }
                                } catch (\Throwable $e) {
                                    Log::channel(config('n8n-eloquent.logging.channel'))
                                        ->warning("Could not resolve model instance for {$typeName}", [
                                            'id' => $paramValue['id'] ?? 'unknown',
                                            'error' => $e->getMessage(),
                                        ]);
                                    $args[] = null;
                                }
                            } else {
                                // If no ID or not an array, pass null
                                $args[] = null;
                            }
                        } else {
                            // Not a model class, pass the value as-is
                            $args[] = $paramValue;
                        }
                    } else {
                        // Built-in type or no type hint, pass the value as-is
                        $args[] = $paramValue;
                    }
                } elseif ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    // Required parameter missing, use null as fallback
                    $args[] = null;
                }
            }

            // Create instance with constructor arguments
            $instance = $reflection->newInstanceArgs($args);
            

            
            return $instance;
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error creating event instance for {$eventClass}", [
                    'parameters' => $parameters,
                    'error' => $e->getMessage(),
                ]);
            return null;
        }
    }
} 