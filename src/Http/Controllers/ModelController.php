<?php

namespace N8n\Eloquent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use N8n\Eloquent\Services\ModelDiscoveryService;

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
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error fetching records for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
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
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error fetching record {$id} for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
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
            
            // Create the record
            $record = $modelInstance->create($request->only($fillable));
            
            return response()->json([
                'data' => $record,
                'message' => 'Record created successfully',
            ], 201);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error creating record for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
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
            
            // Update the record
            $record->update($request->only($fillable));
            
            return response()->json([
                'data' => $record->fresh(),
                'message' => 'Record updated successfully',
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error updating record {$id} for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
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
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error deleting record {$id} for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error deleting record: {$e->getMessage()}",
            ], 500);
        }
    }
} 