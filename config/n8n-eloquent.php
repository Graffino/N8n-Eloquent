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
        'secret' => env('N8N_ELOQUENT_API_SECRET'),
        
        // The prefix for the API routes
        'prefix' => env('N8N_ELOQUENT_API_PREFIX', 'api/n8n'),
        
        // Middleware to apply to the API routes
        'middleware' => ['api'],
        
        // Enable/disable rate limiting for API requests
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => (int) env('N8N_ELOQUENT_RATE_LIMIT_ATTEMPTS', 60),
            'decay_minutes' => (int) env('N8N_ELOQUENT_RATE_LIMIT_DECAY', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Subscriptions
    |--------------------------------------------------------------------------
    |
    | Configure webhook subscription storage and caching.
    |
    */
    'webhooks' => [
        // Database configuration
        'database' => [
            'table' => 'n8n_webhook_subscriptions',
            'connection' => env('N8N_ELOQUENT_DB_CONNECTION', null), // Use default if null
            'soft_deletes' => true,
            'uuid_primary_key' => true,
        ],
        
        // Cache configuration for performance
        'cache' => [
            'enabled' => env('N8N_ELOQUENT_CACHE_ENABLED', true),
            'store' => env('N8N_ELOQUENT_CACHE_STORE', null), // Use default cache store if null
            'ttl' => (int) env('N8N_ELOQUENT_CACHE_TTL', 3600), // Cache TTL in seconds (1 hour)
            'key_prefix' => env('N8N_ELOQUENT_CACHE_PREFIX', 'n8n_eloquent'),
            'tags' => ['n8n', 'webhooks'], // Cache tags for easier invalidation
        ],
        
        // Subscription health monitoring
        'health' => [
            'stale_threshold_hours' => (int) env('N8N_ELOQUENT_STALE_HOURS', 24), // Consider subscriptions stale after this many hours
            'error_threshold_count' => (int) env('N8N_ELOQUENT_ERROR_THRESHOLD', 5), // Max consecutive errors before marking as problematic
            'health_check_interval' => (int) env('N8N_ELOQUENT_HEALTH_INTERVAL', 3600), // Health check interval in seconds
            'auto_deactivate_errors' => env('N8N_ELOQUENT_AUTO_DEACTIVATE', false), // Auto-deactivate subscriptions with persistent errors
        ],
        
        // Cleanup policies for subscription management
        'cleanup' => [
            'enabled' => env('N8N_ELOQUENT_CLEANUP_ENABLED', true),
            'inactive_days' => env('N8N_ELOQUENT_CLEANUP_INACTIVE_DAYS', 30), // Clean up inactive subscriptions after this many days
            'error_days' => env('N8N_ELOQUENT_CLEANUP_ERROR_DAYS', 7), // Clean up subscriptions with errors after this many days
            'never_triggered_days' => env('N8N_ELOQUENT_CLEANUP_NEVER_TRIGGERED_DAYS', 14), // Clean up subscriptions never triggered after this many days
            'batch_size' => env('N8N_ELOQUENT_CLEANUP_BATCH_SIZE', 100), // Number of records to process in each cleanup batch
            'schedule' => env('N8N_ELOQUENT_CLEANUP_SCHEDULE', 'daily'), // Cleanup schedule (daily, weekly, monthly)
        ],
        
        // Archiving configuration
        'archiving' => [
            'enabled' => env('N8N_ELOQUENT_ARCHIVING_ENABLED', false),
            'archive_after_days' => env('N8N_ELOQUENT_ARCHIVE_DAYS', 90), // Archive subscriptions after this many days
            'archive_table' => 'n8n_webhook_subscriptions_archive',
            'compress_archives' => env('N8N_ELOQUENT_COMPRESS_ARCHIVES', true),
            'retention_days' => env('N8N_ELOQUENT_ARCHIVE_RETENTION_DAYS', 365), // Keep archives for this many days
        ],
        
        // Backup configuration
        'backup' => [
            'enabled' => env('N8N_ELOQUENT_BACKUP_ENABLED', true),
            'auto_backup' => env('N8N_ELOQUENT_AUTO_BACKUP', false), // Automatically create backups
            'backup_schedule' => env('N8N_ELOQUENT_BACKUP_SCHEDULE', 'weekly'), // Backup schedule
            'retention_days' => env('N8N_ELOQUENT_BACKUP_RETENTION_DAYS', 30), // Keep backups for this many days
            'storage_disk' => env('N8N_ELOQUENT_BACKUP_DISK', 'local'), // Storage disk for backups
            'compression' => env('N8N_ELOQUENT_BACKUP_COMPRESSION', true), // Compress backup files
        ],
        
        // Performance tuning
        'performance' => [
            'query_cache_ttl' => (int) env('N8N_ELOQUENT_QUERY_CACHE_TTL', 300), // Query result cache TTL in seconds
            'bulk_operations_batch_size' => (int) env('N8N_ELOQUENT_BULK_BATCH_SIZE', 500), // Batch size for bulk operations
            'webhook_timeout' => (int) env('N8N_ELOQUENT_WEBHOOK_TIMEOUT', 5), // Webhook request timeout in seconds
            'max_retries' => (int) env('N8N_ELOQUENT_MAX_RETRIES', 3), // Max retry attempts for failed webhooks
            'retry_delay' => (int) env('N8N_ELOQUENT_RETRY_DELAY', 60), // Delay between retries in seconds
        ],
        
        // Migration settings
        'migration' => [
            'auto_migrate' => env('N8N_ELOQUENT_AUTO_MIGRATE', false),
            'backup_before_migration' => env('N8N_ELOQUENT_BACKUP_BEFORE_MIGRATION', true),
            'validate_data' => env('N8N_ELOQUENT_VALIDATE_MIGRATION_DATA', true),
            'rollback_on_error' => env('N8N_ELOQUENT_ROLLBACK_ON_ERROR', true),
        ],
        
        // Security settings
        'security' => [
            'encrypt_webhook_urls' => env('N8N_ELOQUENT_ENCRYPT_URLS', false), // Encrypt webhook URLs in database
            'validate_webhook_ssl' => env('N8N_ELOQUENT_VALIDATE_SSL', true), // Validate SSL certificates for webhook URLs
            'allowed_domains' => env('N8N_ELOQUENT_ALLOWED_DOMAINS', null), // Comma-separated list of allowed webhook domains
            'rate_limit_per_subscription' => (int) env('N8N_ELOQUENT_RATE_LIMIT_SUBSCRIPTION', 100), // Max triggers per subscription per hour
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
        'namespace' => env('N8N_ELOQUENT_MODELS_NAMESPACE', 'App\\Models'),
        
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
        'enabled' => env('N8N_ELOQUENT_EVENTS_ENABLED', true),
        
        // Default events to listen for
        'default' => ['created', 'updated', 'deleted'],
        
        // Whether to enable property getter/setter events
        'property_events' => [
            'enabled' => env('N8N_ELOQUENT_PROPERTY_EVENTS_ENABLED', true),
            'default' => [], // Default properties to trigger events for
            'skip_unchanged' => env('N8N_ELOQUENT_SKIP_UNCHANGED_PROPERTIES', true), // Skip setter events if value didn't change
            'rate_limit' => [
                'enabled' => env('N8N_ELOQUENT_RATE_LIMIT_ENABLED', true),
                'decay_minutes' => (int) env('N8N_ELOQUENT_RATE_LIMIT_DECAY_MINUTES', 1),
            ],
        ],
        
        // Transaction handling
        'transactions' => [
            'enabled' => env('N8N_ELOQUENT_TRANSACTIONS_ENABLED', true),
            'rollback_on_error' => env('N8N_ELOQUENT_ROLLBACK_ON_ERROR', true),
        ],
        
        // Queue configuration for events
        'queue' => [
            'enabled' => false,
            'name' => 'default',
        ],
        
        // Error handling
        'throw_on_error' => false,
        
        // Infinite loop prevention
        'loop_prevention' => [
            'enabled' => env('N8N_ELOQUENT_LOOP_PREVENTION_ENABLED', true),
            'max_trigger_depth' => (int) env('N8N_ELOQUENT_MAX_TRIGGER_DEPTH', 1),
            'same_model_cooldown' => (int) env('N8N_ELOQUENT_SAME_MODEL_COOLDOWN', 1), // minutes
            'track_chain' => env('N8N_ELOQUENT_TRACK_CHAIN', true),
        ],
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
        'enabled' => env('N8N_ELOQUENT_LOGGING_ENABLED', true),
        'channel' => env('N8N_ELOQUENT_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'level' => env('N8N_ELOQUENT_LOG_LEVEL', 'debug'),
    ],
]; 