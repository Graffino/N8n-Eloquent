<?php

namespace Shortinc\N8nEloquent\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionParameter;
use Illuminate\Contracts\Queue\ShouldQueue;

class JobDiscoveryService
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
     * Cache of discovered jobs.
     *
     * @var \Illuminate\Support\Collection|null
     */
    protected $discoveredJobs = null;

    /**
     * Create a new JobDiscoveryService instance.
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
     * Get all Laravel jobs in the application.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getJobs(): Collection
    {
        if ($this->discoveredJobs !== null) {
            return $this->discoveredJobs;
        }

        // Only return jobs that are explicitly configured as available
        $availableJobs = config('n8n-eloquent.jobs.available', []);
        
        $this->discoveredJobs = collect($availableJobs)->filter(function ($jobClass) {
            if (!class_exists($jobClass)) {
                Log::channel(config('n8n-eloquent.logging.channel'))
                    ->warning("Configured job class does not exist: {$jobClass}");
                return false;
            }

            try {
                $reflection = new ReflectionClass($jobClass);
                return !$reflection->isAbstract() && $this->isJobClass($reflection);
            } catch (\Throwable $e) {
                Log::channel(config('n8n-eloquent.logging.channel'))
                    ->warning("Error checking if {$jobClass} is a Laravel job", [
                        'error' => $e->getMessage(),
                    ]);
                return false;
            }
        });

        return $this->discoveredJobs;
    }

    /**
     * Get metadata for a specific job.
     *
     * @param  string  $jobClass
     * @return array|null
     */
    public function getJobMetadata(string $jobClass): ?array
    {
        // Check if the job is discoverable
        if (!$this->getJobs()->contains($jobClass)) {
            return null;
        }

        try {
            // Create reflection class
            $reflection = new ReflectionClass($jobClass);
            
            // Get job properties
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            $propertyNames = array_map(function ($property) {
                return $property->getName();
            }, $properties);

            // Get job configuration
            $jobConfig = config("n8n-eloquent.jobs.config.{$jobClass}", []);
            
            return [
                'class' => $jobClass,
                'name' => $reflection->getShortName(),
                'properties' => $propertyNames,
                'config' => $jobConfig,
            ];
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting metadata for job {$jobClass}", [
                    'error' => $e->getMessage(),
                ]);
            return null;
        }
    }

    /**
     * Check if a class is a job class.
     *
     * @param  \ReflectionClass  $reflection
     * @return bool
     */
    protected function isJobClass(ReflectionClass $reflection): bool
    {
        // Check if it extends Illuminate\Contracts\Queue\ShouldQueue
        if ($reflection->implementsInterface(ShouldQueue::class)) {
            return true;
        }
        
        // Check if it extends Illuminate\Console\Command
        if ($reflection->isSubclassOf(\Illuminate\Console\Command::class)) {
            return true;
        }
        
        // Check if it has a handle method (common for jobs)
        if ($reflection->hasMethod('handle')) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if a job is configured as available for n8n.
     *
     * @param  string  $jobClass
     * @return bool
     */
    public function isJobConfigured(string $jobClass): bool
    {
        // Check if the job is in the available jobs list
        $availableJobs = config('n8n-eloquent.jobs.available', []);
        return in_array($jobClass, $availableJobs);
    }

    /**
     * Get job parameters for dispatching.
     *
     * @param  string  $jobClass
     * @return array
     */
    public function getJobParameters(string $jobClass): array
    {
        if (!class_exists($jobClass)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($jobClass);
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
                    'type' => $paramType ? $this->getParameterType($paramType) : 'mixed',
                    'required' => $isRequired,
                    'default' => $defaultValue,
                    'label' => $this->getParameterLabel($parameter),
                ];
            }

            return $parameters;
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting parameters for job {$jobClass}", [
                    'error' => $e->getMessage(),
                ]);
            return [];
        }
    }

    /**
     * Get parameter type.
     *
     * @param  \ReflectionType  $type
     * @return string
     */
    protected function getParameterType($type): string
    {
        if (!$type) {
            return 'mixed';
        }
        
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(fn($t) => $t->getName(), $type->getTypes()));
        }
        
        if (method_exists($type, 'getName')) {
            return $type->getName();
        }
        
        return 'mixed';
    }

    /**
     * Get parameter label.
     *
     * @param  \ReflectionParameter  $parameter
     * @return string
     */
    protected function getParameterLabel(ReflectionParameter $parameter): string
    {
        $name = $parameter->getName();
        
        // Convert snake_case to Title Case
        $label = str_replace('_', ' ', $name);
        $label = ucwords($label);
        
        return $label;
    }

    /**
     * Create a job instance with parameters.
     *
     * @param  string  $jobClass
     * @param  array  $parameters
     * @param  array  $metadata
     * @return object|null
     */
    public function createJobInstance(string $jobClass, array $parameters = [], array $metadata = []): ?object
    {
        if (!class_exists($jobClass)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($jobClass);
            $constructor = $reflection->getConstructor();
            
            if (!$constructor) {
                // Job has no constructor, create instance without parameters
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
                    
                    // Handle JSON string parameters
                    if (is_string($paramValue) && (str_starts_with($paramValue, '{') || str_starts_with($paramValue, '['))) {
                        try {
                            $decoded = json_decode($paramValue, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $paramValue = $decoded;
                                Log::channel(config('n8n-eloquent.logging.channel'))
                                    ->info("Parsed JSON string for parameter {$paramName}");
                            }
                        } catch (\Throwable $e) {
                            Log::channel(config('n8n-eloquent.logging.channel'))
                                ->warning("Failed to parse JSON for parameter {$paramName}", [
                                    'error' => $e->getMessage(),
                                ]);
                        }
                    }
                    
                    // Handle PHP array syntax strings (like "['item1', 'item2']")
                    if (is_string($paramValue) && str_starts_with($paramValue, '[') && str_ends_with($paramValue, ']')) {
                        try {
                            $decoded = $this->parsePhpArraySyntax($paramValue);
                            if ($decoded !== null) {
                                $paramValue = $decoded;
                                Log::channel(config('n8n-eloquent.logging.channel'))
                                    ->info("Parsed PHP array syntax for parameter {$paramName}");
                            }
                        } catch (\Throwable $e) {
                            Log::channel(config('n8n-eloquent.logging.channel'))
                                ->warning("Failed to parse PHP array syntax for parameter {$paramName}", [
                                    'error' => $e->getMessage(),
                                ]);
                        }
                    }
                    
                    // If the parameter is a type-hinted model class, try to resolve it
                    if ($paramType && !$paramType->isBuiltin()) {
                        $typeName = $paramType->getName();
                        
                        // Check if it's a model class
                        if ($typeName && class_exists($typeName) && is_subclass_of($typeName, 'Illuminate\Database\Eloquent\Model')) {
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
                ->error("Error creating job instance for {$jobClass}", [
                    'parameters' => $parameters,
                    'error' => $e->getMessage(),
                ]);
            return null;
        }
    }

    /**
     * Parse PHP array syntax string to array.
     *
     * @param  string  $value
     * @return array|null
     */
    protected function parsePhpArraySyntax(string $value): ?array
    {
        // Remove outer brackets
        $value = trim($value, '[]');
        
        // If empty, return empty array
        if (empty($value)) {
            return [];
        }
        
        // Split by comma and parse each item
        $items = explode(',', $value);
        $result = [];
        
        foreach ($items as $item) {
            $item = trim($item);
            
            // Remove quotes
            $item = trim($item, '"\'');
            
            // Skip empty items
            if (empty($item)) {
                continue;
            }
            
            $result[] = $item;
        }
        
        return $result;
    }
}
