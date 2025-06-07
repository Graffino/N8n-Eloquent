<?php

namespace Shortinc\N8nEloquent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthenticateN8n
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
                ->error('N8n Eloquent API secret is not configured.');
            
            return response()->json([
                'error' => 'API secret not configured. Please set N8N_ELOQUENT_API_SECRET in your .env file.'
            ], 500);
        }
        
        $providedApiKey = $request->header('X-N8n-Api-Key');
        
        if (empty($providedApiKey) || $providedApiKey !== $apiSecret) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->warning('Unauthorized N8n API access attempt', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
} 