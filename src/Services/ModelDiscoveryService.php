<?php

namespace N8n\Eloquent\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelDiscoveryService
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
     * Cache of discovered models.
     *
     * @var \Illuminate\Support\Collection|null
     */
    protected $discoveredModels = null;

    /**
     * Create a new ModelDiscoveryService instance.
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
     * Get all Eloquent models in the application.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getModels(): Collection
    {
        if ($this->discoveredModels !== null) {
            return $this->discoveredModels;
        }

        $modelNamespace = config('n8n-eloquent.models.namespace', 'App\\Models');
        $modelDirectory = config('n8n-eloquent.models.directory', app_path('Models'));
        $mode = config('n8n-eloquent.models.mode', 'whitelist');
        $whitelist = config('n8n-eloquent.models.whitelist', []);
        $blacklist = config('n8n-eloquent.models.blacklist', []);

        // Get all PHP files in the models directory
        $files = $this->files->glob("{$modelDirectory}/*.php");

        // Transform file paths to class names
        $models = collect($files)->map(function ($file) use ($modelNamespace, $modelDirectory) {
            $relativePath = Str::after($file, $modelDirectory . DIRECTORY_SEPARATOR);
            $className = $modelNamespace . '\\' . Str::beforeLast($relativePath, '.php');
            
            return $className;
        });

        // Filter to include only Eloquent models
        $eloquentModels = $models->filter(function ($className) {
            if (!class_exists($className)) {
                return false;
            }

            try {
                $reflection = new ReflectionClass($className);
                return !$reflection->isAbstract() && $reflection->isSubclassOf(Model::class);
            } catch (\Throwable $e) {
                Log::channel(config('n8n-eloquent.logging.channel'))
                    ->warning("Error checking if {$className} is an Eloquent model", [
                        'error' => $e->getMessage(),
                    ]);
                return false;
            }
        });

        // Apply whitelist/blacklist filtering
        switch ($mode) {
            case 'whitelist':
                $eloquentModels = $eloquentModels->filter(function ($className) use ($whitelist) {
                    return in_array($className, $whitelist);
                });
                break;
                
            case 'blacklist':
                $eloquentModels = $eloquentModels->filter(function ($className) use ($blacklist) {
                    return !in_array($className, $blacklist);
                });
                break;
                
            case 'all':
                // No filtering, include all models
                break;
                
            default:
                Log::channel(config('n8n-eloquent.logging.channel'))
                    ->warning("Unknown model discovery mode: {$mode}. Defaulting to 'all'.");
        }

        $this->discoveredModels = $eloquentModels->values();
        
        return $this->discoveredModels;
    }

    /**
     * Get metadata for a specific model.
     *
     * @param  string  $modelClass
     * @return array|null
     */
    public function getModelMetadata(string $modelClass): ?array
    {
        // Check if the model is discoverable
        if (!$this->getModels()->contains($modelClass)) {
            return null;
        }

        try {
            // Create reflection class
            $reflection = new ReflectionClass($modelClass);
            
            // Get an instance of the model
            $model = App::make($modelClass);
            
            // Get fillable attributes
            $fillable = $model->getFillable();
            
            // Get table name
            $table = $model->getTable();
            
            // Get primary key
            $primaryKey = $model->getKeyName();
            
            // Get model events configuration
            $modelConfig = config("n8n-eloquent.models.config.{$modelClass}", []);
            $defaultEvents = config('n8n-eloquent.events.default', ['created', 'updated', 'deleted']);
            $events = $modelConfig['events'] ?? $defaultEvents;
            
            // Get property getters/setters configuration
            $propertyEventsEnabled = config('n8n-eloquent.events.property_events.enabled', true);
            $defaultPropertyEvents = config('n8n-eloquent.events.property_events.default', []);
            $getters = $modelConfig['getters'] ?? $defaultPropertyEvents;
            $setters = $modelConfig['setters'] ?? $defaultPropertyEvents;
            
            return [
                'class' => $modelClass,
                'name' => $reflection->getShortName(),
                'table' => $table,
                'primaryKey' => $primaryKey,
                'fillable' => $fillable,
                'events' => $events,
                'property_events' => [
                    'enabled' => $propertyEventsEnabled,
                    'getters' => $getters,
                    'setters' => $setters,
                ],
            ];
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting metadata for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return null;
        }
    }

    /**
     * Get properties for a specific model.
     *
     * @param  string  $modelClass
     * @return array|null
     */
    public function getModelProperties(string $modelClass): ?array
    {
        // Check if the model is discoverable
        if (!$this->getModels()->contains($modelClass)) {
            return null;
        }

        try {
            // Get an instance of the model
            $model = App::make($modelClass);
            
            // Connect to the database to get column information
            $table = $model->getTable();
            $connection = $model->getConnection();
            $schema = $connection->getDoctrineSchemaManager();
            
            // Doctrine Schema does not support JSON columns in some DB drivers
            $schema->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
            $schema->getDatabasePlatform()->registerDoctrineTypeMapping('json', 'text');
            
            // Get column information
            $columns = $schema->listTableColumns($table);
            
            $properties = [];
            foreach ($columns as $column) {
                $name = $column->getName();
                $type = $column->getType()->getName();
                $nullable = !$column->getNotnull();
                $default = $column->getDefault();
                
                $properties[$name] = [
                    'name' => $name,
                    'type' => $type,
                    'nullable' => $nullable,
                    'default' => $default,
                    'fillable' => in_array($name, $model->getFillable()),
                    'primary' => $name === $model->getKeyName(),
                ];
            }
            
            return $properties;
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting properties for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return null;
        }
    }

    /**
     * Clear the model discovery cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->discoveredModels = null;
    }
} 