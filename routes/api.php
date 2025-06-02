<?php

use Illuminate\Support\Facades\Route;
use N8n\Eloquent\Http\Controllers\ModelController;
use N8n\Eloquent\Http\Controllers\WebhookController;
use N8n\Eloquent\Http\Controllers\WebhookManagementController;
use N8n\Eloquent\Http\Controllers\SubscriptionHealthController;
use N8n\Eloquent\Http\Middleware\AuthenticateN8n;
use N8n\Eloquent\Http\Middleware\ValidateHmacSignature;
use N8n\Eloquent\Http\Middleware\RateLimitWebhooks;

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
}); 