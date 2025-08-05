<?php

namespace Shortinc\N8nEloquent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Shortinc\N8nEloquent\Services\ModelDiscoveryService;
use Shortinc\N8nEloquent\Services\WebhookService;

class WebhookController extends Controller
{
    /**
     * The model discovery service.
     *
     * @var \N8n\Eloquent\Services\ModelDiscoveryService
     */
    protected $modelDiscovery;

    /**
     * The webhook service.
     *
     * @var \N8n\Eloquent\Services\WebhookService
     */
    protected $webhookService;

    /**
     * Create a new controller instance.
     *
     * @param  \N8n\Eloquent\Services\ModelDiscoveryService  $modelDiscovery
     * @param  \N8n\Eloquent\Services\WebhookService  $webhookService
     * @return void
     */
    public function __construct(
        ModelDiscoveryService $modelDiscovery,
        WebhookService $webhookService
    ) {
        $this->modelDiscovery = $modelDiscovery;
        $this->webhookService = $webhookService;
    }

    /**
     * Subscribe to model events.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'model' => 'required|string',
            'events' => 'required|array',
            'events.*' => 'string|in:created,updated,deleted,restored,saving,saved',
            'webhook_url' => 'required|url',
            'properties' => 'array',
            'properties.*' => 'string',
            'node_id' => 'nullable|string',
            'workflow_id' => 'nullable|string',
            'verify_hmac' => 'nullable|boolean',
            'require_timestamp' => 'nullable|boolean',
            'expected_source_ip' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // URL decode model name
        $modelClass = urldecode($request->input('model'));
        
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Model {$modelClass} not found or not accessible",
            ], 404);
        }

        try {
            // Prepare metadata for the webhook service
            $metadata = [
                'node_id' => $request->input('node_id'),
                'workflow_id' => $request->input('workflow_id'),
                'verify_hmac' => $request->input('verify_hmac', true),
                'require_timestamp' => $request->input('require_timestamp', true),
                'expected_source_ip' => $request->input('expected_source_ip'),
            ];
            
            // Register the webhook
            $subscription = $this->webhookService->subscribe(
                $modelClass,
                $request->input('events'),
                $request->input('webhook_url'),
                $request->input('properties', []),
                $metadata
            );
            
            return response()->json([
                'message' => 'Webhook subscription created successfully',
                'subscription' => $subscription,
            ], 201);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error creating webhook subscription for model {$modelClass}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error creating webhook subscription: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Unsubscribe from model events.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsubscribe(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $subscriptionId = $request->input('subscription_id');
        
        try {
            // Unregister the webhook
            $result = $this->webhookService->unsubscribe($subscriptionId);
            
            if (!$result) {
                return response()->json([
                    'error' => "Subscription with ID {$subscriptionId} not found",
                ], 404);
            }
            
            return response()->json([
                'message' => 'Webhook subscription deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error("Error deleting webhook subscription {$subscriptionId}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
            return response()->json([
                'error' => "Error deleting webhook subscription: {$e->getMessage()}",
            ], 500);
        }
    }
} 