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
use Shortinc\N8nEloquent\Services\JobDiscoveryService;
use ReflectionClass;
use ReflectionParameter;

class JobController extends Controller
{
    /**
     * The job discovery service.
     *
     * @var \Shortinc\N8nEloquent\Services\JobDiscoveryService
     */
    protected $jobDiscovery;

    /**
     * Create a new controller instance.
     *
     * @param  \Shortinc\N8nEloquent\Services\JobDiscoveryService  $jobDiscovery
     * @return void
     */
    public function __construct(JobDiscoveryService $jobDiscovery)
    {
        $this->jobDiscovery = $jobDiscovery;
    }

    /**
     * Get all available jobs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $jobs = $this->jobDiscovery->getJobs()->map(function ($jobClass) {
                $metadata = $this->jobDiscovery->getJobMetadata($jobClass);
                return $metadata;
            })->filter()->values();
            
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
            
            // Check if job is configured as available
            if (!$this->jobDiscovery->isJobConfigured($jobClass)) {
                return response()->json([
                    'error' => "Job class {$jobClass} is not configured as available",
                ], 403);
            }
            
            $metadata = $this->jobDiscovery->getJobMetadata($jobClass);
            
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
            
            // Check if job is configured as available
            if (!$this->jobDiscovery->isJobConfigured($jobClass)) {
                return response()->json([
                    'error' => "Job class {$jobClass} is not configured as available",
                ], 403);
            }
            
            $parameters = $this->jobDiscovery->getJobParameters($jobClass);
            
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
            
            // Check if job is configured as available
            if (!$this->jobDiscovery->isJobConfigured($jobClass)) {
                return response()->json([
                    'error' => "Job class {$jobClass} is not configured as available",
                ], 403);
            }
            
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'metadata' => 'array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            // Extract parameters from request body (excluding metadata and configuration)
            $allData = $request->all();
            $metadata = $request->input('metadata', []);
            
            // Extract job configuration
            $queue = $allData['queue'] ?? 'default';
            $connection = $allData['connection'] ?? null;
            $delay = $allData['delay'] ?? 0;
            $afterCommit = $allData['afterCommit'] ?? false;
            $maxAttempts = $allData['maxAttempts'] ?? null;
            $timeout = $allData['timeout'] ?? null;
            
            // Remove metadata and configuration from parameters
            unset($allData['metadata'], $allData['queue'], $allData['connection'], $allData['delay'], $allData['afterCommit'], $allData['maxAttempts'], $allData['timeout']);
            
            // The remaining data is the parameters
            $parameters = $allData;
            
            // Debug logging
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->info('Job dispatch request received', [
                    'job_class' => $jobClass,
                    'parameters' => $parameters,
                    'metadata' => $metadata,
                ]);
            
            // Add n8n metadata to prevent loops
            $metadata['is_n8n_dispatched'] = true;
            $metadata['dispatched_at'] = now()->toISOString();
            
            // Create job instance using the JobDiscoveryService
            $jobInstance = $this->jobDiscovery->createJobInstance($jobClass, $parameters, $metadata);
            
            if (!$jobInstance) {
                return response()->json([
                    'error' => 'Failed to create job instance',
                ], 500);
            }
            
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


} 