<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the API settings for the n8n integration.
    |
    */
    'api' => [
        // The secret key used for authentication with n8n
        'secret' => env('N8N_ELOQUENT_API_SECRET', null),
        
        // The prefix for the API routes
        'prefix' => env('N8N_ELOQUENT_API_PREFIX', 'api/n8n'),
        
        // Middleware to apply to the API routes
        'middleware' => ['api'],
        
        // Enable/disable rate limiting for API requests
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => 60,
            'decay_minutes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    |
    | Configure how models are discovered and exposed to n8n.
    |
    */
    'models' => [
        // The base namespace for models
        'namespace' => 'App\\Models',
        
        // The directory where models are located
        'directory' => app_path('Models'),
        
        // Mode: 'whitelist', 'blacklist', or 'all'
        'mode' => env('N8N_ELOQUENT_MODEL_MODE', 'all'),
        
        // Models to include/exclude based on the mode
        'whitelist' => [
            // 'App\\Models\\User',
        ],
        
        'blacklist' => [
            // 'App\\Models\\PasswordReset',
        ],

        // Model-specific configurations
        'config' => [
            // Example: 'App\\Models\\User' => [
            //     'events' => ['created', 'updated', 'deleted'],
            //     'getters' => ['name', 'email'],
            //     'setters' => ['name', 'email'],
            //     'watched_attributes' => ['name', 'email'], // Only trigger update events for these attributes
            //     'queue_events' => false,
            //     'queue_name' => 'default',
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Configure which events should be fired and how they are handled.
    |
    */
    'events' => [
        // Enable/disable event processing globally
        'enabled' => true,
        
        // Default events to listen for
        'default' => ['created', 'updated', 'deleted'],
        
        // Whether to enable property getter/setter events
        'property_events' => [
            'enabled' => true,
            'default' => [], // Default properties to trigger events for
            'skip_unchanged' => true, // Skip setter events if value didn't change
            'rate_limit' => [
                'enabled' => true,
                'max_attempts' => 10,
                'decay_minutes' => 1,
            ],
        ],
        
        // Transaction handling
        'transactions' => [
            'enabled' => true,
            'rollback_on_error' => true,
        ],
        
        // Queue configuration for events
        'queue' => [
            'enabled' => false,
            'name' => 'default',
        ],
        
        // Error handling
        'throw_on_error' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | n8n Connection
    |--------------------------------------------------------------------------
    |
    | Configure the connection to your n8n instance.
    |
    */
    'n8n' => [
        // The URL of your n8n instance
        'url' => env('N8N_URL', 'http://localhost:5678'),
        
        // The webhook URLs to notify
        'webhooks' => [
            // 'model.created' => env('N8N_WEBHOOK_MODEL_CREATED', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for the n8n integration.
    |
    */
    'logging' => [
        'enabled' => true,
        'channel' => env('N8N_ELOQUENT_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'level' => env('N8N_ELOQUENT_LOG_LEVEL', 'debug'),
    ],
]; 