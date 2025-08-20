<?php

namespace Shortinc\N8nEloquent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Shortinc\N8nEloquent\Services\ModelDiscoveryService;

class ModelController extends Controller
{
    /**
     * The model discovery service.
     *
     * @var \N8n\Eloquent\Services\ModelDiscoveryService
     */
    protected $modelDiscovery;

    /**
     * Create a new controller instance.
     *
     * @param  \N8n\Eloquent\Services\ModelDiscoveryService  $modelDiscovery
     * @return void
     */
    public function __construct(ModelDiscoveryService $modelDiscovery)
    {
        $this->modelDiscovery = $modelDiscovery;
    }

    /**
     * Get all available models.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $models = $this->modelDiscovery->getModels()->map(function ($modelClass) {
            $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
            return $metadata;
        })->filter()->values();

        return response()->json([
            'models' => $models,
        ]);
    }

    /**
     * Get metadata for a specific model.
     *
     * @param  string  $model
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $model)
    {
        // URL decode model name (could be URL encoded fully qualified class name)
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }
        
        return response()->json([
            'model' => $metadata,
        ]);
    }

    /**
     * Get properties for a specific model.
     *
     * @param  string  $model
     * @return \Illuminate\Http\JsonResponse
     */
    public function properties(string $model)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $properties = $this->modelDiscovery->getModelProperties($modelClass);
        
        if ($properties === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }
        
        return response()->json([
            'properties' => $properties,
        ]);
    }

    /**
     * Get enhanced field metadata for a specific model.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $model
     * @return \Illuminate\Http\JsonResponse
     */
    public function fields(Request $request, string $model)
    {
        // Convert forward slashes back to backslashes for namespace
        $modelClass = str_replace('/', '\\', $model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }

        try {
            $fields = $this->modelDiscovery->getEnhancedFieldMetadata($modelClass);
            
            // Apply filters if provided
            if ($request->has('category')) {
                $category = $request->input('category');
                $fields = array_filter($fields, function ($field) use ($category) {
                    return in_array($category, $field['categories'] ?? []);
                });
            }

            if ($request->has('type')) {
                $type = $request->input('type');
                $fields = array_filter($fields, function ($field) use ($type) {
                    return $field['type'] === $type;
                });
            }

            if ($request->has('search')) {
                $search = strtolower($request->input('search'));
                $fields = array_filter($fields, function ($field) use ($search) {
                    return str_contains(strtolower($field['name']), $search) ||
                           str_contains(strtolower($field['label'] ?? ''), $search);
                });
            }

            return response()->json([
                'fields' => array_values($fields),
                'meta' => [
                    'total' => count($fields),
                    'model' => $modelClass,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error fetching fields: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Get relationship metadata for a specific model.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $model
     * @return \Illuminate\Http\JsonResponse
     */
    public function relationships(Request $request, string $model)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }

        try {
            $relationships = $this->modelDiscovery->getModelRelationships($modelClass);
            
            // Apply filters if provided
            if ($request->has('type')) {
                $type = $request->input('type');
                $relationships = array_filter($relationships, function ($relationship) use ($type) {
                    return $relationship['type'] === $type;
                });
            }

            if ($request->has('related_model')) {
                $relatedModel = $request->input('related_model');
                $relationships = array_filter($relationships, function ($relationship) use ($relatedModel) {
                    return $relationship['related_model'] === $relatedModel;
                });
            }

            return response()->json([
                'relationships' => array_values($relationships),
                'meta' => [
                    'total' => count($relationships),
                    'model' => $modelClass,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error fetching relationships: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Search models with enhanced filtering.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $models = $this->modelDiscovery->getModels();
            $results = [];

            foreach ($models as $modelClass) {
                $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
                if ($metadata) {
                    $results[] = $metadata;
                }
            }

            // Apply search filter
            if ($request->has('q')) {
                $query = strtolower($request->input('q'));
                $results = array_filter($results, function ($model) use ($query) {
                    return str_contains(strtolower($model['name']), $query) ||
                           str_contains(strtolower($model['class']), $query) ||
                           str_contains(strtolower($model['table'] ?? ''), $query);
                });
            }

            // Apply namespace filter
            if ($request->has('namespace')) {
                $namespace = $request->input('namespace');
                $results = array_filter($results, function ($model) use ($namespace) {
                    return str_starts_with($model['class'], $namespace);
                });
            }

            // Apply category filter
            if ($request->has('category')) {
                $category = $request->input('category');
                $results = array_filter($results, function ($model) use ($category) {
                    return in_array($category, $model['categories'] ?? []);
                });
            }

            // Apply sorting
            $sortBy = $request->input('sort', 'name');
            $sortDirection = $request->input('direction', 'asc');
            
            usort($results, function ($a, $b) use ($sortBy, $sortDirection) {
                $valueA = $a[$sortBy] ?? '';
                $valueB = $b[$sortBy] ?? '';
                
                $comparison = strcasecmp($valueA, $valueB);
                return $sortDirection === 'desc' ? -$comparison : $comparison;
            });

            // Apply pagination
            $perPage = min($request->input('per_page', 20), 100);
            $page = max($request->input('page', 1), 1);
            $offset = ($page - 1) * $perPage;
            
            $total = count($results);
            $paginatedResults = array_slice($results, $offset, $perPage);

            return response()->json([
                'models' => $paginatedResults,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error searching models: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Get field dependencies for conditional field handling.
     *
     * @param  string  $model
     * @param  string  $field
     * @return \Illuminate\Http\JsonResponse
     */
    public function fieldDependencies(string $model, string $field)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }

        try {
            $dependencies = $this->modelDiscovery->getFieldDependencies($modelClass, $field);
            
            return response()->json([
                'dependencies' => $dependencies,
                'meta' => [
                    'model' => $modelClass,
                    'field' => $field,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error fetching field dependencies: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Get validation rules for a specific model.
     *
     * @param  string  $model
     * @return \Illuminate\Http\JsonResponse
     */
    public function validationRules(string $model)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }

        try {
            $rules = $this->modelDiscovery->getValidationRules($modelClass);
            
            return response()->json([
                'rules' => $rules,
                'meta' => [
                    'model' => $modelClass,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error fetching validation rules: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Get records for a specific model.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $model
     * @return \Illuminate\Http\JsonResponse
     */
    public function records(Request $request, string $model)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }
        
        try {
            // Get the model instance
            $modelInstance = App::make($modelClass);
            
            // Start with base query
            $query = $modelInstance->query();
            
            // Apply filters if provided
            if ($request->has('filters') && is_array($request->input('filters'))) {
                foreach ($request->input('filters') as $field => $value) {
                    $query->where($field, $value);
                }
            }
            
            // Apply sorting if provided
            if ($request->has('sort')) {
                $sortField = $request->input('sort');
                $sortDirection = $request->input('direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            }
            
            // Apply pagination
            $perPage = $request->input('per_page', 15);
            $page = $request->input('page', 1);
            
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error fetching records: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Get a specific record for a model.
     *
     * @param  string  $model
     * @param  string|int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function record(string $model, $id)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }
        
        try {
            // Get the model instance
            $modelInstance = App::make($modelClass);
            
            // Find the record
            $record = $modelInstance->find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => "Record with ID {$id} not found",
                ], 404);
            }
            
            return response()->json([
                'data' => $record,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error fetching record: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Create a new record for a model.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $model
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, string $model)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }
        
        try {
            // Get the model instance
            $modelInstance = App::make($modelClass);
            
            // Get the fillable attributes
            $fillable = $metadata['fillable'];
            
            // Validate the request data
            $validationRules = [];
            $properties = $this->modelDiscovery->getModelProperties($modelClass);
            
            foreach ($fillable as $field) {
                if (isset($properties[$field])) {
                    $prop = $properties[$field];
                    $rule = [];
                    
                    if (!$prop['nullable']) {
                        $rule[] = 'required';
                    } else {
                        $rule[] = 'nullable';
                    }
                    
                    // Add type validation based on column type
                    switch ($prop['type']) {
                        case 'integer':
                        case 'bigint':
                        case 'smallint':
                            $rule[] = 'integer';
                            break;
                        case 'decimal':
                        case 'float':
                            $rule[] = 'numeric';
                            break;
                        case 'boolean':
                            $rule[] = 'boolean';
                            break;
                        case 'date':
                            $rule[] = 'date';
                            break;
                        case 'datetime':
                            $rule[] = 'date_format:Y-m-d H:i:s';
                            break;
                        default:
                            $rule[] = 'string';
                    }
                    
                    $validationRules[$field] = implode('|', $rule);
                }
            }
            
            $validator = Validator::make($request->all(), $validationRules);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            // Extract metadata from request for loop detection
            $metadata = $request->input('metadata', []);
            
            // Store metadata in request context for observers to access
            if (!empty($metadata)) {
                // Store metadata in request attributes for observers to access
                $request->attributes->set('n8n_metadata', $metadata);
                // Also store in session as backup
                session(['n8n_metadata' => $metadata]);
            }
            
            // Create the record
            $record = $modelInstance->create($request->only($fillable));
            
            return response()->json([
                'data' => $record,
                'message' => 'Record created successfully',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error creating record: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Update a specific record for a model.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $model
     * @param  string|int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $model, $id)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }
        
        try {
            // Get the model instance
            $modelInstance = App::make($modelClass);
            
            // Find the record
            $record = $modelInstance->find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => "Record with ID {$id} not found",
                ], 404);
            }
            
            // Get the fillable attributes
            $fillable = $metadata['fillable'];
            
            // Validate the request data
            $validationRules = [];
            $properties = $this->modelDiscovery->getModelProperties($modelClass);
            
            foreach ($fillable as $field) {
                if (isset($properties[$field]) && $request->has($field)) {
                    $prop = $properties[$field];
                    $rule = [];
                    
                    // Fields are optional on update
                    $rule[] = 'nullable';
                    
                    // Add type validation based on column type
                    switch ($prop['type']) {
                        case 'integer':
                        case 'bigint':
                        case 'smallint':
                            $rule[] = 'integer';
                            break;
                        case 'decimal':
                        case 'float':
                            $rule[] = 'numeric';
                            break;
                        case 'boolean':
                            $rule[] = 'boolean';
                            break;
                        case 'date':
                            $rule[] = 'date';
                            break;
                        case 'datetime':
                            $rule[] = 'date_format:Y-m-d H:i:s';
                            break;
                        default:
                            $rule[] = 'string';
                    }
                    
                    $validationRules[$field] = implode('|', $rule);
                }
            }
            
            $validator = Validator::make($request->all(), $validationRules);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            // Extract metadata from request for loop detection
            $metadata = $request->input('metadata', []);
            
            // Store metadata in request context for observers to access
            if (!empty($metadata)) {
                // Store metadata in request attributes for observers to access
                $request->attributes->set('n8n_metadata', $metadata);
                // Also store in session as backup
                session(['n8n_metadata' => $metadata]);
            }
            
            // Update the record
            $record->update($request->only($fillable));
            
            return response()->json([
                'data' => $record->fresh(),
                'message' => 'Record updated successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error updating record: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Delete a specific record for a model.
     *
     * @param  string  $model
     * @param  string|int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $model, $id)
    {
        // URL decode model name
        $modelClass = urldecode($model);
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }
        
        try {
            // Get the model instance
            $modelInstance = App::make($modelClass);
            
            // Find the record
            $record = $modelInstance->find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => "Record with ID {$id} not found",
                ], 404);
            }
            
            // Delete the record
            $record->delete();
            
            return response()->json([
                'message' => 'Record deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => "Error deleting record: {$e->getMessage()}",
            ], 500);
        }
    }
} 