<?php

use Illuminate\Support\Facades\Route;
use Shortinc\N8nEloquent\Http\Controllers\ModelController;
use Shortinc\N8nEloquent\Http\Controllers\WebhookController;
use Shortinc\N8nEloquent\Http\Controllers\WebhookManagementController;
use Shortinc\N8nEloquent\Http\Controllers\SubscriptionHealthController;
use Shortinc\N8nEloquent\Http\Controllers\JobController;
use Shortinc\N8nEloquent\Http\Controllers\EventController;
use Shortinc\N8nEloquent\Http\Middleware\AuthenticateN8n;
use Shortinc\N8nEloquent\Http\Middleware\ValidateHmacSignature;
use Shortinc\N8nEloquent\Http\Middleware\RateLimitWebhooks;

$prefix = config('n8n-eloquent.api.prefix', 'api/n8n');
$middleware = config('n8n-eloquent.api.middleware', ['api']);

Route::prefix($prefix)->middleware(array_merge($middleware, [AuthenticateN8n::class, RateLimitWebhooks::class]))->group(function () {
    // Models discovery and metadata
    Route::get('/models', [ModelController::class, 'index']);
    Route::get('/models/search', [ModelController::class, 'search']);
    Route::get('/models/{model}', [ModelController::class, 'show']);
    Route::get('/models/{model}/properties', [ModelController::class, 'properties']);
    Route::get('/models/{model}/fields', [ModelController::class, 'fields']);
    Route::get('/models/{model}/relationships', [ModelController::class, 'relationships']);
    Route::get('/models/{model}/validation-rules', [ModelController::class, 'validationRules']);
    Route::get('/models/{model}/fields/{field}/dependencies', [ModelController::class, 'fieldDependencies']);
    
    // Model records CRUD operations
    Route::get('/models/{model}/records', [ModelController::class, 'records']);
    Route::post('/models/{model}/records', [ModelController::class, 'store']);
    Route::get('/models/{model}/records/{id}', [ModelController::class, 'record']);
    Route::put('/models/{model}/records/{id}', [ModelController::class, 'update']);
    Route::delete('/models/{model}/records/{id}', [ModelController::class, 'destroy']);
    
    // Basic webhook operations
    Route::post('/webhooks/subscribe', [WebhookController::class, 'subscribe']);
    Route::delete('/webhooks/unsubscribe', [WebhookController::class, 'unsubscribe']);
    
    // Enhanced webhook management
    Route::get('/webhooks', [WebhookManagementController::class, 'index']);
    Route::get('/webhooks/stats', [WebhookManagementController::class, 'stats']);
    Route::post('/webhooks/bulk', [WebhookManagementController::class, 'bulk']);
    Route::get('/webhooks/{subscription}', [WebhookManagementController::class, 'show']);
    Route::put('/webhooks/{subscription}', [WebhookManagementController::class, 'update']);
    Route::post('/webhooks/{subscription}/test', [WebhookManagementController::class, 'test']);
    
    // Subscription health monitoring
    Route::get('/health', [SubscriptionHealthController::class, 'healthCheck']);
    Route::get('/health/detailed', [SubscriptionHealthController::class, 'detailedHealth']);
    Route::get('/health/analytics', [SubscriptionHealthController::class, 'analytics']);
    Route::get('/health/validate/{subscription}', [SubscriptionHealthController::class, 'validateSubscription']);
    Route::post('/test-credentials', [SubscriptionHealthController::class, 'testCredentials']);
    
    // Job discovery and dispatching
    Route::get('/jobs', [JobController::class, 'index']);
    Route::get('/jobs/{job}', [JobController::class, 'show']);
    Route::get('/jobs/{job}/parameters', [JobController::class, 'parameters']);
    Route::post('/jobs/{job}/dispatch', [JobController::class, 'dispatch']);
    
    // Event discovery and webhook subscription
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/search', [EventController::class, 'search']);
    Route::get('/events/{event}', [EventController::class, 'show']);
    Route::get('/events/{event}/parameters', [EventController::class, 'parameters']);
    Route::post('/events/{event}/dispatch', [EventController::class, 'dispatch']);
    Route::post('/events/subscribe', [EventController::class, 'subscribe']);
    Route::delete('/events/unsubscribe', [EventController::class, 'unsubscribe']);
}); 