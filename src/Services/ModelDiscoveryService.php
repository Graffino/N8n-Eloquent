<?php

namespace Shortinc\N8nEloquent\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use Illuminate\Support\Facades\Schema;

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
            
            // Get table information using Laravel's Schema facade
            $table = $model->getTable();
            $connection = $model->getConnection();
            
            // Get column information using Laravel's Schema
            $columns = Schema::connection($connection->getName())->getColumnListing($table);
            $columnTypes = Schema::connection($connection->getName())->getColumns($table);
            
            $properties = [];
            foreach ($columns as $columnName) {
                // Find the column details
                $columnDetails = collect($columnTypes)->firstWhere('name', $columnName);
                
                if (!$columnDetails) {
                    continue;
                }
                
                $type = $columnDetails['type_name'] ?? 'string';
                $nullable = $columnDetails['nullable'] ?? false;
                $default = $columnDetails['default'] ?? null;
                
                $properties[$columnName] = [
                    'name' => $columnName,
                    'type' => $type,
                    'nullable' => $nullable,
                    'default' => $default,
                    'fillable' => in_array($columnName, $model->getFillable()),
                    'primary' => $columnName === $model->getKeyName(),
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

    /**
     * Get enhanced field metadata for a specific model.
     *
     * @param  string  $modelClass
     * @return array
     */
    public function getEnhancedFieldMetadata(string $modelClass): array
    {
        $properties = $this->getModelProperties($modelClass);
        
        if ($properties === null) {
            return [];
        }

        try {
            $model = App::make($modelClass);
            $reflection = new ReflectionClass($modelClass);
            
            $enhancedFields = [];
            
            foreach ($properties as $property) {
                $fieldName = $property['name'];
                
                // Determine field categories
                $categories = $this->getFieldCategories($model, $fieldName, $property);
                
                // Get field validation rules if available
                $validationRules = $this->getFieldValidationRules($modelClass, $fieldName);
                
                // Get field relationships
                $relationship = $this->getFieldRelationship($model, $fieldName);
                
                // Generate human-readable label
                $label = $this->generateFieldLabel($fieldName);
                
                // Determine input type for UI
                $inputType = $this->determineInputType($property, $relationship);
                
                $enhancedFields[] = [
                    'name' => $fieldName,
                    'label' => $label,
                    'type' => $property['type'],
                    'input_type' => $inputType,
                    'nullable' => $property['nullable'],
                    'default' => $property['default'],
                    'primary' => $property['primary'],
                    'fillable' => $property['fillable'],
                    'categories' => $categories,
                    'validation_rules' => $validationRules,
                    'relationship' => $relationship,
                    'description' => $this->getFieldDescription($reflection, $fieldName),
                ];
            }
            
            return $enhancedFields;
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting enhanced field metadata for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return [];
        }
    }

    /**
     * Get model relationships.
     *
     * @param  string  $modelClass
     * @return array
     */
    public function getModelRelationships(string $modelClass): array
    {
        try {
            $model = App::make($modelClass);
            $reflection = new ReflectionClass($modelClass);
            $relationships = [];
            
            foreach ($reflection->getMethods() as $method) {
                if ($method->class !== $modelClass || !$method->isPublic() || $method->isStatic()) {
                    continue;
                }
                
                // Skip magic methods and common model methods
                if (str_starts_with($method->name, '__') || 
                    in_array($method->name, ['getTable', 'getKeyName', 'getFillable', 'getHidden', 'getDates'])) {
                    continue;
                }
                
                try {
                    // Try to call the method to see if it returns a relationship
                    $result = $model->{$method->name}();
                    
                    if ($this->isEloquentRelationship($result)) {
                        $relationshipType = class_basename(get_class($result));
                        $relatedModel = get_class($result->getRelated());
                        
                        $relationships[] = [
                            'name' => $method->name,
                            'type' => $relationshipType,
                            'related_model' => $relatedModel,
                            'foreign_key' => $this->getRelationshipForeignKey($result),
                            'local_key' => $this->getRelationshipLocalKey($result),
                            'pivot_table' => $this->getRelationshipPivotTable($result),
                        ];
                    }
                } catch (\Throwable $e) {
                    // Skip methods that throw exceptions
                    continue;
                }
            }
            
            return $relationships;
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting relationships for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return [];
        }
    }

    /**
     * Get field dependencies for conditional field handling.
     *
     * @param  string  $modelClass
     * @param  string  $fieldName
     * @return array
     */
    public function getFieldDependencies(string $modelClass, string $fieldName): array
    {
        try {
            $dependencies = [];
            
            // Check for enum dependencies
            $enumValues = $this->getFieldEnumValues($modelClass, $fieldName);
            if (!empty($enumValues)) {
                $dependencies['enum_values'] = $enumValues;
            }
            
            // Check for relationship dependencies
            $relationship = $this->getFieldRelationship(App::make($modelClass), $fieldName);
            if ($relationship) {
                $dependencies['relationship'] = $relationship;
                
                // Get related model options
                $relatedModel = $relationship['related_model'];
                if (class_exists($relatedModel)) {
                    $relatedInstance = App::make($relatedModel);
                    $dependencies['related_options'] = $relatedInstance->limit(100)->get(['id', 'name'])->toArray();
                }
            }
            
            // Check for validation dependencies
            $validationRules = $this->getFieldValidationRules($modelClass, $fieldName);
            if (!empty($validationRules)) {
                $dependencies['validation'] = $validationRules;
            }
            
            return $dependencies;
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting field dependencies for {$modelClass}::{$fieldName}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return [];
        }
    }

    /**
     * Get validation rules for a specific model.
     *
     * @param  string  $modelClass
     * @return array
     */
    public function getValidationRules(string $modelClass): array
    {
        try {
            $model = App::make($modelClass);
            
            // Check if model has a rules method or property
            if (method_exists($model, 'rules')) {
                return $model->rules();
            }
            
            if (property_exists($model, 'rules')) {
                return $model->rules;
            }
            
            // Try to get rules from form request if available
            $formRequestClass = str_replace('Models', 'Http\\Requests', $modelClass) . 'Request';
            if (class_exists($formRequestClass)) {
                $formRequest = App::make($formRequestClass);
                if (method_exists($formRequest, 'rules')) {
                    return $formRequest->rules();
                }
            }
            
            return [];
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error getting validation rules for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return [];
        }
    }

    /**
     * Get field categories for classification.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $fieldName
     * @param  array  $property
     * @return array
     */
    protected function getFieldCategories($model, string $fieldName, array $property): array
    {
        $categories = [];
        
        // Primary key
        if ($property['primary']) {
            $categories[] = 'primary';
        }
        
        // Fillable
        if ($property['fillable']) {
            $categories[] = 'fillable';
        }
        
        // Hidden
        if (in_array($fieldName, $model->getHidden())) {
            $categories[] = 'hidden';
        }
        
        // Dates
        if (in_array($fieldName, $model->getDates())) {
            $categories[] = 'date';
        }
        
        // Timestamps
        if (in_array($fieldName, ['created_at', 'updated_at', 'deleted_at'])) {
            $categories[] = 'timestamp';
        }
        
        // Foreign keys (ending with _id)
        if (str_ends_with($fieldName, '_id')) {
            $categories[] = 'foreign_key';
        }
        
        // JSON fields
        if (in_array($property['type'], ['json', 'jsonb'])) {
            $categories[] = 'json';
        }
        
        // Text fields
        if (in_array($property['type'], ['text', 'longtext', 'mediumtext'])) {
            $categories[] = 'text';
        }
        
        // Numeric fields
        if (in_array($property['type'], ['integer', 'bigint', 'decimal', 'float', 'double'])) {
            $categories[] = 'numeric';
        }
        
        // Boolean fields
        if ($property['type'] === 'boolean') {
            $categories[] = 'boolean';
        }
        
        return $categories;
    }

    /**
     * Get field validation rules.
     *
     * @param  string  $modelClass
     * @param  string  $fieldName
     * @return array
     */
    protected function getFieldValidationRules(string $modelClass, string $fieldName): array
    {
        $allRules = $this->getValidationRules($modelClass);
        
        return $allRules[$fieldName] ?? [];
    }

    /**
     * Get field relationship information.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $fieldName
     * @return array|null
     */
    protected function getFieldRelationship($model, string $fieldName): ?array
    {
        // Check if this is a foreign key field
        if (str_ends_with($fieldName, '_id')) {
            $relationshipName = Str::camel(str_replace('_id', '', $fieldName));
            
            if (method_exists($model, $relationshipName)) {
                try {
                    $relationship = $model->{$relationshipName}();
                    
                    if ($this->isEloquentRelationship($relationship)) {
                        return [
                            'name' => $relationshipName,
                            'type' => class_basename(get_class($relationship)),
                            'related_model' => get_class($relationship->getRelated()),
                        ];
                    }
                } catch (\Throwable $e) {
                    // Relationship method exists but throws exception
                }
            }
        }
        
        return null;
    }

    /**
     * Generate a human-readable label for a field.
     *
     * @param  string  $fieldName
     * @return string
     */
    protected function generateFieldLabel(string $fieldName): string
    {
        return Str::title(str_replace(['_', '-'], ' ', $fieldName));
    }

    /**
     * Determine the appropriate input type for a field.
     *
     * @param  array  $property
     * @param  array|null  $relationship
     * @return string
     */
    protected function determineInputType(array $property, ?array $relationship): string
    {
        if ($relationship) {
            return 'select'; // Dropdown for relationships
        }
        
        return match ($property['type']) {
            'boolean' => 'checkbox',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'time' => 'time',
            'text', 'longtext', 'mediumtext' => 'textarea',
            'json', 'jsonb' => 'json',
            'integer', 'bigint' => 'number',
            'decimal', 'float', 'double' => 'number',
            'email' => 'email',
            'password' => 'password',
            default => 'text',
        };
    }

    /**
     * Get field description from model docblocks.
     *
     * @param  \ReflectionClass  $reflection
     * @param  string  $fieldName
     * @return string|null
     */
    protected function getFieldDescription(ReflectionClass $reflection, string $fieldName): ?string
    {
        $docComment = $reflection->getDocComment();
        
        if ($docComment) {
            // Look for @property annotations
            if (preg_match("/@property\s+\S+\s+\\\${$fieldName}\s+(.+)/", $docComment, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Get enum values for a field.
     *
     * @param  string  $modelClass
     * @param  string  $fieldName
     * @return array
     */
    protected function getFieldEnumValues(string $modelClass, string $fieldName): array
    {
        try {
            $model = App::make($modelClass);
            $table = $model->getTable();
            $connection = $model->getConnection();
            
            // Get column information
            $column = $connection->getDoctrineColumn($table, $fieldName);
            
            // Check if it's an enum column
            if ($column->getType()->getName() === 'enum') {
                // This would need database-specific implementation
                // For now, return empty array
                return [];
            }
            
            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Check if a result is an Eloquent relationship.
     *
     * @param  mixed  $result
     * @return bool
     */
    protected function isEloquentRelationship($result): bool
    {
        return $result instanceof \Illuminate\Database\Eloquent\Relations\Relation;
    }

    /**
     * Get relationship foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relationship
     * @return string|null
     */
    protected function getRelationshipForeignKey($relationship): ?string
    {
        if (method_exists($relationship, 'getForeignKeyName')) {
            return $relationship->getForeignKeyName();
        }
        
        if (method_exists($relationship, 'getForeignKey')) {
            return $relationship->getForeignKey();
        }
        
        return null;
    }

    /**
     * Get relationship local key.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relationship
     * @return string|null
     */
    protected function getRelationshipLocalKey($relationship): ?string
    {
        if (method_exists($relationship, 'getLocalKeyName')) {
            return $relationship->getLocalKeyName();
        }
        
        if (method_exists($relationship, 'getOwnerKey')) {
            return $relationship->getOwnerKey();
        }
        
        return null;
    }

    /**
     * Get relationship pivot table.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relationship
     * @return string|null
     */
    protected function getRelationshipPivotTable($relationship): ?string
    {
        if (method_exists($relationship, 'getTable')) {
            return $relationship->getTable();
        }
        
        return null;
    }
} 