<?php

namespace Shortinc\N8nEloquent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitWebhooks
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $maxAttempts
     * @param  string|null  $decayMinutes
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $maxAttempts = null, $decayMinutes = null)
    {
        // Get rate limiting configuration
        $rateLimitConfig = config('n8n-eloquent.api.rate_limiting', []);
        
        if (!($rateLimitConfig['enabled'] ?? true)) {
            return $next($request);
        }
        
        $maxAttempts = $maxAttempts ?: ($rateLimitConfig['max_attempts'] ?? 60);
        $decayMinutes = $decayMinutes ?: ($rateLimitConfig['decay_minutes'] ?? 1);
        
        // Create rate limit key based on IP and API key
        $apiKey = $request->header('X-N8n-Api-Key');
        $rateLimitKey = sprintf(
            'n8n_webhook_rate_limit:%s:%s',
            $request->ip(),
            $apiKey ? hash('sha256', $apiKey) : 'anonymous'
        );
        
        // Get current attempt count
        $attempts = Cache::get($rateLimitKey, 0);
        
        // Check if rate limit exceeded
        if ($attempts >= $maxAttempts) {
            Log::channel(config('n8n-eloquent.logging.channel'))
                ->warning('Rate limit exceeded for webhook request', [
                    'ip' => $request->ip(),
                    'api_key_hash' => $apiKey ? hash('sha256', $apiKey) : null,
                    'attempts' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'user_agent' => $request->userAgent(),
                ]);
            
            return response()->json([
                'error' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $decayMinutes * 60,
            ], 429);
        }
        
        // Increment attempt count
        $expiresAt = now()->addMinutes($decayMinutes);
        Cache::put($rateLimitKey, $attempts + 1, $expiresAt);
        
        $response = $next($request);
        
        // Add rate limit headers to response
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $attempts - 1));
        $response->headers->set('X-RateLimit-Reset', $expiresAt->timestamp);
        
        return $response;
    }
} 