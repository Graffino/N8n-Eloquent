<?php

use Illuminate\Support\Facades\Route;
use N8n\Eloquent\Http\Controllers\ModelController;
use N8n\Eloquent\Http\Controllers\WebhookController;
use N8n\Eloquent\Http\Middleware\AuthenticateN8n;
use N8n\Eloquent\Http\Middleware\ValidateHmacSignature;

$prefix = config('n8n-eloquent.api.prefix', 'api/n8n');
$middleware = config('n8n-eloquent.api.middleware', ['api']);

Route::prefix($prefix)->middleware(array_merge($middleware, [AuthenticateN8n::class]))->group(function () {
    // Models discovery and metadata
    Route::get('/models', [ModelController::class, 'index']);
    Route::get('/models/{model}', [ModelController::class, 'show']);
    Route::get('/models/{model}/properties', [ModelController::class, 'properties']);
    
    // Model operations
    Route::get('/models/{model}/records', [ModelController::class, 'records']);
    Route::get('/models/{model}/records/{id}', [ModelController::class, 'record']);
    Route::post('/models/{model}/records', [ModelController::class, 'store']);
    Route::put('/models/{model}/records/{id}', [ModelController::class, 'update']);
    Route::delete('/models/{model}/records/{id}', [ModelController::class, 'destroy']);
    
    // Webhooks for n8n to listen to events
    Route::post('/webhooks/subscribe', [WebhookController::class, 'subscribe']);
    Route::delete('/webhooks/unsubscribe', [WebhookController::class, 'unsubscribe']);
}); 