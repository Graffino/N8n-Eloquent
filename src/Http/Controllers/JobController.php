<?php

namespace Shortinc\N8nEloquent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionParameter;

class JobController extends Controller
{
    /**
     * Get all available jobs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $jobs = $this->discoverJobs();
            
            return response()->json([
                'jobs' => $jobs,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to discover jobs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to discover jobs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get metadata for a specific job.
     *
     * @param  string  $job
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $job)
    {
        try {
            // URL decode job name
            $jobClass = urldecode($job);
            
            if (!class_exists($jobClass)) {
                return response()->json([
                    'error' => "Job class {$jobClass} not found",
                ], 404);
            }
            
            $metadata = $this->getJobMetadata($jobClass);
            
            return response()->json([
                'job' => $metadata,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get job metadata', [
                'job' => $job,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to get job metadata',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get parameters for a specific job.
     *
     * @param  string  $job
     * @return \Illuminate\Http\JsonResponse
     */
    public function parameters(string $job)
    {
        try {
            // URL decode job name
            $jobClass = urldecode($job);
            
            if (!class_exists($jobClass)) {
                return response()->json([
                    'error' => "Job class {$jobClass} not found",
                ], 404);
            }
            
            $parameters = $this->getJobParameters($jobClass);
            
            return response()->json([
                'parameters' => $parameters,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get job parameters', [
                'job' => $job,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to get job parameters',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispatch a job.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $job
     * @return \Illuminate\Http\JsonResponse
     */
    public function dispatch(Request $request, string $job)
    {
        try {
            // URL decode job name
            $jobClass = urldecode($job);
            
            if (!class_exists($jobClass)) {
                return response()->json([
                    'error' => "Job class {$jobClass} not found",
                ], 404);
            }
            
            $data = $request->all();
            $metadata = $data['metadata'] ?? [];
            unset($data['metadata']); // Remove metadata from job parameters
            
            // Extract job configuration
            $queue = $data['queue'] ?? 'default';
            $connection = $data['connection'] ?? null;
            $delay = $data['delay'] ?? 0;
            $afterCommit = $data['afterCommit'] ?? false;
            $maxAttempts = $data['maxAttempts'] ?? null;
            $timeout = $data['timeout'] ?? null;
            
            // Remove configuration from job parameters
            unset($data['queue'], $data['connection'], $data['delay'], $data['afterCommit'], $data['maxAttempts'], $data['timeout']);
            
            // Get job parameters to map them correctly
            $parameters = $this->getJobParameters($jobClass);
            $constructorArgs = [];
            
            Log::info('Job dispatch parameters', [
                'job_class' => $jobClass,
                'received_data' => $data,
                'expected_parameters' => $parameters,
                'data_keys' => array_keys($data),
                'parameter_names' => array_column($parameters, 'name'),
            ]);
            
            // Map parameters to constructor arguments in the correct order
            foreach ($parameters as $param) {
                $paramName = $param['name'];
                Log::info("Processing parameter: {$paramName} (required: " . ($param['required'] ? 'true' : 'false') . ")");
                
                // Try exact match first
                if (isset($data[$paramName])) {
                    $value = $data[$paramName];
                    
                    // Try to convert string representations of arrays/objects
                    if (is_string($value)) {
                        // Try to decode JSON first
                        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $value = $decoded;
                                Log::info("Converted JSON string to array/object for parameter: {$paramName}");
                            }
                        }
                        
                        // Try to parse PHP array syntax (like "[["email"=>"test@example.com"]]")
                        if (is_string($value) && str_starts_with($value, '[[') && str_ends_with($value, ']]')) {
                            // Simple parsing for this specific format
                            $value = $this->parsePhpArraySyntax($value);
                            Log::info("Converted PHP array syntax for parameter: {$paramName}");
                        }
                    }
                    
                    $constructorArgs[] = $value;
                    Log::info("Parameter mapped (exact): {$paramName} = " . json_encode($value));
                } else {
                    // Try case-insensitive match
                    $found = false;
                    foreach ($data as $key => $value) {
                        if (strtolower($key) === strtolower($paramName)) {
                            // Try to convert string representations of arrays/objects
                            if (is_string($value)) {
                                // Try to decode JSON first
                                if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
                                    $decoded = json_decode($value, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $value = $decoded;
                                        Log::info("Converted JSON string to array/object for parameter: {$paramName}");
                                    }
                                }
                                
                                // Try to parse PHP array syntax (like "[["email"=>"test@example.com"]]")
                                if (is_string($value) && str_starts_with($value, '[[') && str_ends_with($value, ']]')) {
                                    // Simple parsing for this specific format
                                    $value = $this->parsePhpArraySyntax($value);
                                    Log::info("Converted PHP array syntax for parameter: {$paramName}");
                                }
                            }
                            
                            $constructorArgs[] = $value;
                            Log::info("Parameter mapped (case-insensitive): {$paramName} (from {$key}) = " . json_encode($value));
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        if ($param['required']) {
                            Log::error("Missing required parameter: {$paramName}");
                            Log::error("Available data keys: " . implode(', ', array_keys($data)));
                            throw new \InvalidArgumentException("Required parameter '{$paramName}' is missing for job {$jobClass}");
                        } else {
                            $constructorArgs[] = $param['default'];
                            Log::info("Using default for parameter: {$paramName} = " . json_encode($param['default']));
                        }
                    }
                }
            }
            
            Log::info('Final constructor arguments', [
                'constructor_args' => $constructorArgs,
            ]);
            
            // Create job instance with properly ordered arguments
            $jobInstance = new $jobClass(...$constructorArgs);
            
            // Configure job
            if ($maxAttempts) {
                $jobInstance->tries($maxAttempts);
            }
            
            if ($timeout) {
                $jobInstance->timeout($timeout);
            }
            
            if ($delay > 0) {
                $jobInstance->delay($delay);
            }
            
            if ($connection) {
                $jobInstance->onConnection($connection);
            }
            
            $jobInstance->onQueue($queue);
            
            // Dispatch job using the Queue facade
            if ($afterCommit) {
                $dispatchedJob = Queue::push($jobInstance);
            } else {
                $dispatchedJob = Queue::push($jobInstance);
            }
            
            // Log the dispatch
            Log::info('Job dispatched from n8n', [
                'job_class' => $jobClass,
                'job_id' => $dispatchedJob,
                'queue' => $queue,
                'connection' => $connection,
                'delay' => $delay,
                'metadata' => $metadata,
            ]);
            
            return response()->json([
                'success' => true,
                'job_id' => $dispatchedJob,
                'job_class' => $jobClass,
                'queue' => $queue,
                'dispatched_at' => now()->toISOString(),
                'metadata' => $metadata,
            ]);
            
        } catch (\InvalidArgumentException $e) {
            Log::error('Invalid job parameters', [
                'job' => $job,
                'data' => $request->all(),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Invalid job parameters',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch job', [
                'job' => $job,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to dispatch job',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Discover available jobs in the application.
     *
     * @return array
     */
    protected function discoverJobs()
    {
        $jobs = [];
        
        // Common job directories
        $jobDirectories = [
            app_path('Jobs'),
            app_path('Console/Commands'),
        ];
        
        foreach ($jobDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            
            $files = glob($directory . '/*.php');
            
            foreach ($files as $file) {
                $className = 'App\\' . str_replace('/', '\\', str_replace(app_path() . '/', '', str_replace('.php', '', $file)));
                
                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);
                    
                    // Check if it's a job class
                    if ($this->isJobClass($reflection)) {
                        $jobs[] = [
                            'name' => $reflection->getShortName(),
                            'class' => $className,
                            'file' => $file,
                            'namespace' => $reflection->getNamespaceName(),
                        ];
                    }
                }
            }
        }
        
        // Also check for jobs in other namespaces
        $this->discoverJobsInNamespace($jobs, 'App\\Jobs');
        $this->discoverJobsInNamespace($jobs, 'App\\Console\\Commands');
        
        return $jobs;
    }

    /**
     * Discover jobs in a specific namespace.
     *
     * @param  array  $jobs
     * @param  string  $namespace
     * @return void
     */
    protected function discoverJobsInNamespace(&$jobs, $namespace)
    {
        try {
            $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);
            $autoload = $composerJson['autoload']['psr-4'] ?? [];
            
            foreach ($autoload as $namespacePrefix => $path) {
                if (Str::startsWith($namespace, $namespacePrefix)) {
                    $relativePath = str_replace($namespacePrefix, '', $namespace);
                    $fullPath = base_path($path . '/' . str_replace('\\', '/', $relativePath));
                    
                    if (is_dir($fullPath)) {
                        $this->scanDirectoryForJobs($jobs, $fullPath, $namespace);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to discover jobs in namespace', [
                'namespace' => $namespace,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Scan directory for job classes.
     *
     * @param  array  $jobs
     * @param  string  $directory
     * @param  string  $namespace
     * @return void
     */
    protected function scanDirectoryForJobs(&$jobs, $directory, $namespace)
    {
        $files = glob($directory . '/*.php');
        
        foreach ($files as $file) {
            $className = $namespace . '\\' . basename($file, '.php');
            
            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);
                
                if ($this->isJobClass($reflection)) {
                    $jobs[] = [
                        'name' => $reflection->getShortName(),
                        'class' => $className,
                        'file' => $file,
                        'namespace' => $reflection->getNamespaceName(),
                    ];
                }
            }
        }
        
        // Scan subdirectories
        $subdirs = glob($directory . '/*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $subNamespace = $namespace . '\\' . basename($subdir);
            $this->scanDirectoryForJobs($jobs, $subdir, $subNamespace);
        }
    }

    /**
     * Check if a class is a job class.
     *
     * @param  \ReflectionClass  $reflection
     * @return bool
     */
    protected function isJobClass(ReflectionClass $reflection)
    {
        // Check if it extends Illuminate\Contracts\Queue\ShouldQueue
        if ($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class)) {
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
     * Get metadata for a job.
     *
     * @param  string  $jobClass
     * @return array|null
     */
    protected function getJobMetadata($jobClass)
    {
        if (!class_exists($jobClass)) {
            return null;
        }
        
        $reflection = new ReflectionClass($jobClass);
        
        return [
            'name' => $reflection->getShortName(),
            'class' => $jobClass,
            'namespace' => $reflection->getNamespaceName(),
            'file' => $reflection->getFileName(),
            'parameters' => $this->getJobParameters($jobClass),
        ];
    }

    /**
     * Get parameters for a job.
     *
     * @param  string  $jobClass
     * @return array
     */
    protected function getJobParameters($jobClass)
    {
        if (!class_exists($jobClass)) {
            return [];
        }
        
        $reflection = new ReflectionClass($jobClass);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return [];
        }
        
        $parameters = [];
        
        foreach ($constructor->getParameters() as $parameter) {
            $paramInfo = [
                'name' => $parameter->getName(),
                'type' => $this->getParameterType($parameter),
                'required' => !$parameter->isOptional(),
                'default' => $parameter->isOptional() ? $parameter->getDefaultValue() : null,
            ];
            
            // Try to get a more descriptive label
            $paramInfo['label'] = $this->getParameterLabel($parameter);
            
            $parameters[] = $paramInfo;
        }
        
        return $parameters;
    }

    /**
     * Get parameter type.
     *
     * @param  \ReflectionParameter  $parameter
     * @return string
     */
    protected function getParameterType(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();
        
        if ($type) {
            if ($type instanceof \ReflectionUnionType) {
                return implode('|', array_map(fn($t) => $t->getName(), $type->getTypes()));
            }
            
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
    protected function getParameterLabel(ReflectionParameter $parameter)
    {
        $name = $parameter->getName();
        
        // Convert snake_case to Title Case
        $label = str_replace('_', ' ', $name);
        $label = ucwords($label);
        
        return $label;
    }

    /**
     * Parse PHP array syntax string to array.
     *
     * @param  string  $value
     * @return array
     */
    protected function parsePhpArraySyntax(string $value)
    {
        // Remove outer brackets
        $value = trim($value, '[]');
        
        // Split by comma and parse each item
        $items = explode(',', $value);
        $result = [];
        
        foreach ($items as $item) {
            $item = trim($item);
            if (str_contains($item, '=>')) {
                [$key, $val] = explode('=>', $item, 2);
                $key = trim($key, '"\'');
                $val = trim($val, '"\'');
                $result[$key] = $val;
            } else {
                $result[] = trim($item, '"\'');
            }
        }
        
        return $result;
    }
} 