<?php

namespace Shortinc\N8nEloquent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ValidateHmacSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $apiSecret = config('n8n-eloquent.api.secret');
        
        if (empty($apiSecret)) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->error('HMAC signature validation failed: API secret not configured.');
            
            return response()->json([
                'error' => 'API secret not configured. Please set N8N_ELOQUENT_API_SECRET in your .env file.'
            ], 500);
        }
        
        $signature = $request->header('X-N8n-Signature');
        
        if (empty($signature)) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->warning('HMAC signature missing in request', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            
            return response()->json(['error' => 'HMAC signature missing'], 401);
        }
        
        // Get request body
        $payload = $request->getContent();
        
        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $apiSecret);
        
        // Verify signature
        if (!hash_equals($expectedSignature, $signature)) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->warning('Invalid HMAC signature', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        return $next($request);
    }
} 