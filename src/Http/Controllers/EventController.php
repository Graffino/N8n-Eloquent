<?php

namespace Shortinc\N8nEloquent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Shortinc\N8nEloquent\Services\EventDiscoveryService;
use Shortinc\N8nEloquent\Services\WebhookService;

class EventController extends Controller
{
    /**
     * The event discovery service.
     *
     * @var \Shortinc\N8nEloquent\Services\EventDiscoveryService
     */
    protected $eventDiscovery;

    /**
     * The webhook service.
     *
     * @var \Shortinc\N8nEloquent\Services\WebhookService
     */
    protected $webhookService;

    /**
     * Create a new controller instance.
     *
     * @param  \Shortinc\N8nEloquent\Services\EventDiscoveryService  $eventDiscovery
     * @param  \Shortinc\N8nEloquent\Services\WebhookService  $webhookService
     * @return void
     */
    public function __construct(
        EventDiscoveryService $eventDiscovery,
        WebhookService $webhookService
    ) {
        $this->eventDiscovery = $eventDiscovery;
        $this->webhookService = $webhookService;
    }

    /**
     * Get all available events.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $events = $this->eventDiscovery->getEvents()->map(function ($eventClass) {
            $metadata = $this->eventDiscovery->getEventMetadata($eventClass);
            return $metadata;
        })->filter()->values();

        return response()->json([
            'events' => $events,
        ]);
    }

    /**
     * Get metadata for a specific event.
     *
     * @param  string  $event
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $event)
    {
        // URL decode event name (could be URL encoded fully qualified class name)
        $eventClass = urldecode($event);
        
        $metadata = $this->eventDiscovery->getEventMetadata($eventClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Event {$eventClass} not found or not accessible",
            ], 404);
        }
        
        return response()->json([
            'event' => $metadata,
        ]);
    }

    /**
     * Search events.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $events = $this->eventDiscovery->getEvents();
            $results = [];

            foreach ($events as $eventClass) {
                $metadata = $this->eventDiscovery->getEventMetadata($eventClass);
                if ($metadata) {
                    $results[] = $metadata;
                }
            }

            // Apply search filter
            if ($request->has('q')) {
                $query = strtolower($request->input('q'));
                $results = array_filter($results, function ($event) use ($query) {
                    return str_contains(strtolower($event['name']), $query) ||
                           str_contains(strtolower($event['class']), $query);
                });
            }

            // Apply namespace filter
            if ($request->has('namespace')) {
                $namespace = $request->input('namespace');
                $results = array_filter($results, function ($event) use ($namespace) {
                    return str_starts_with($event['class'], $namespace);
                });
            }

            // Apply sorting
            $sortBy = $request->input('sort', 'name');
            $sortDirection = $request->input('direction', 'asc');
            
            usort($results, function ($a, $b) use ($sortBy, $sortDirection) {
                $aValue = $a[$sortBy] ?? '';
                $bValue = $b[$sortBy] ?? '';
                
                if ($sortDirection === 'desc') {
                    return strcasecmp($bValue, $aValue);
                }
                
                return strcasecmp($aValue, $bValue);
            });

            // Apply pagination
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 15);
            $offset = ($page - 1) * $perPage;
            
            $paginatedResults = array_slice($results, $offset, $perPage);

            return response()->json([
                'events' => $paginatedResults,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => count($results),
                    'last_page' => ceil(count($results) / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to search events',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subscribe to event webhooks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'event' => 'required|string',
            'webhook_url' => 'required|url',
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

        // URL decode event name
        $eventClass = urldecode($request->input('event'));
        
        $metadata = $this->eventDiscovery->getEventMetadata($eventClass);
        
        if ($metadata === null) {
            return response()->json([
                'error' => "Event {$eventClass} not found or not accessible",
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
            
            // Register the webhook for the event
            $subscription = $this->webhookService->subscribeToEvent(
                $eventClass,
                $request->input('webhook_url'),
                $metadata
            );
            
            return response()->json([
                'message' => 'Event webhook subscription created successfully',
                'subscription' => $subscription,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to subscribe to event webhook', [
                'event' => $eventClass,
                'webhook_url' => $request->input('webhook_url'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to subscribe to event webhook',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unsubscribe from event webhooks.
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

        try {
            $this->webhookService->unsubscribeFromEvent($request->input('subscription_id'));
            
            return response()->json([
                'message' => 'Event webhook subscription removed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe from event webhook', [
                'subscription_id' => $request->input('subscription_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to unsubscribe from event webhook',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
} 