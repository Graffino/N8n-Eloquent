# Complete Setup Guide

## Prerequisites

- Laravel 10.x or higher
- n8n 1.0.0 or higher
- PHP 8.1 or higher
- Node.js 16 or higher

## Installation Steps

### 1. Install the Laravel Package

```bash
composer require shortinc/n8n-eloquent
```

The package will automatically register its service provider in Laravel 5.5+ applications.

### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="Shortinc\N8nEloquent\N8nEloquentServiceProvider"
```

This will create:

- `config/n8n-eloquent.php` - Main configuration file
- `database/migrations/*_create_webhook_subscriptions_table.php` - Webhook subscription table

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Configure Environment Variables

Add these environment variables to your `.env` file:

```env
# n8n Eloquent API Configuration
N8N_ELOQUENT_API_SECRET=your-api-secret-key-here
N8N_HMAC_SECRET=your-hmac-secret-key-here
N8N_ELOQUENT_MODEL_MODE=all

# Optional Configuration
N8N_WEBHOOK_TIMEOUT=30
N8N_WEBHOOK_RETRY_ATTEMPTS=3
N8N_WEBHOOK_COOLDOWN_MINUTES=5
```

**Generate Secure Secrets:**

You can generate secure secrets using various methods:

```bash
# Using OpenSSL (recommended)
openssl rand -hex 32

# Using PHP
php -r "echo bin2hex(random_bytes(32));"

# Using Node.js
node -e "console.log(require('crypto').randomBytes(32).toString('hex'));"

# Using SHA256 of a random string
echo "your-random-string-here" | sha256sum
```

**Example generated secrets:**

- API Secret: `a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456`
- HMAC Secret: `f1e2d3c4b5a6789012345678901234567890fedcba1234567890fedcba123456`

### 5. Configure n8n-Eloquent

Edit `config/n8n-eloquent.php` to customize the integration:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'prefix' => env('N8N_ELOQUENT_API_PREFIX', 'api/n8n'),
        'middleware' => ['api'],
        'secret' => env('N8N_ELOQUENT_API_SECRET'),
        'hmac_secret' => env('N8N_HMAC_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    */
    'models' => [
        'mode' => env('N8N_ELOQUENT_MODEL_MODE', 'all'), // 'all', 'whitelist', 'blacklist'
        'whitelist' => [
            // Add specific models to whitelist if using whitelist mode
            // 'App\\Models\\User',
            // 'App\\Models\\Post',
        ],
        'blacklist' => [
            // Add specific models to blacklist if using blacklist mode
            // 'App\\Models\\SensitiveData',
        ],
        'config' => [
            // Model-specific configuration
            // 'App\\Models\\User' => [
            //     'events' => ['created', 'updated', 'deleted'],
            //     'properties' => ['name', 'email', 'status'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'timeout' => env('N8N_WEBHOOK_TIMEOUT', 30),
        'retry_attempts' => env('N8N_WEBHOOK_RETRY_ATTEMPTS', 3),
        'cooldown_minutes' => env('N8N_WEBHOOK_COOLDOWN_MINUTES', 5),
        'verify_hmac' => true,
        'require_timestamp' => true,
        'timestamp_tolerance' => 300, // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'ip_whitelist' => [
            // Add n8n server IPs here
            // '192.168.1.1',
            // '10.0.0.0/24',
        ],
        'ip_whitelist_enabled' => false, // Set to true to enable IP whitelisting
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => 60,
            'decay_minutes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Configuration
    |--------------------------------------------------------------------------
    */
    'jobs' => [
        'mode' => 'all', // 'all', 'whitelist', 'blacklist'
        'whitelist' => [
            // Add specific jobs to whitelist
            // 'App\\Jobs\\SendEmail',
        ],
        'blacklist' => [
            // Add specific jobs to blacklist
            // 'App\\Jobs\\SensitiveOperation',
        ],
        'max_execution_time' => 300, // 5 minutes
        'max_memory' => '512M',
        'allowed_queues' => ['default', 'emails'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Configuration
    |--------------------------------------------------------------------------
    */
    'events' => [
        'mode' => 'all', // 'all', 'whitelist', 'blacklist'
        'whitelist' => [
            // Add specific events to whitelist
            // 'App\\Events\\UserRegistered',
        ],
        'blacklist' => [
            // Add specific events to blacklist
            // 'App\\Events\\SensitiveEvent',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('N8N_ELOQUENT_LOG_CHANNEL', 'daily'),
        'level' => env('N8N_ELOQUENT_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications Configuration
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'security_events' => [
            'enabled' => false,
            'channels' => ['slack', 'email'],
            'recipients' => ['admin@example.com'],
            'events' => [
                'authentication_failed',
                'rate_limit_exceeded',
                'ip_blocked',
                'webhook_tampering',
            ],
        ],
    ],
];
```

### 6. Install n8n Nodes

```bash
# Navigate to n8n extension directory
cd n8n-extension

# Install dependencies
npm install

# Build the nodes
npm run build

# Link to your n8n installation (if using local n8n)
npm link
```

### 7. Configure n8n Credentials

1. Open your n8n instance
2. Go to **Settings** → **Credentials**
3. Click **Add Credential**
4. Select **Laravel Eloquent API**
5. Configure with the same secrets from your `.env` file:
   - **Base URL**: Your Laravel application URL (e.g., `http://localhost:8000`)
   - **API Key**: Use the same value as `N8N_ELOQUENT_API_SECRET` from your `.env` file
   - **HMAC Secret**: Use the same value as `N8N_HMAC_SECRET` from your `.env` file
6. Click **Test** to verify the connection
7. **Save** the credential

### 8. Docker Setup (Optional)

If you're using a custom-compiled or Docker-hosted n8n instance, add these volumes to your `docker-compose.yml`:

```yaml
version: '3.8'
services:
  n8n:
    image: n8nio/n8n:latest
    # ... other configuration
    volumes:
      # ... existing volumes
      # n8n-Eloquent extension volumes
      - ./n8n-extension/dist:/home/node/.n8n/nodes/node_modules/n8n-nodes-eloquent/dist
      - ./n8n-extension/package.json:/home/node/.n8n/nodes/node_modules/n8n-nodes-eloquent/package.json
      # ... other volumes
```

**Note**: Replace `./n8n-extension` with the actual path to your n8n-extension directory relative to your docker-compose.yml file.

### 9. Configure Your Models

Add the `HasWebhooks` trait to models you want to monitor:

```php
use Shortinc\N8nEloquent\Traits\HasWebhooks;

class User extends Model
{
    use HasWebhooks;
    
    protected static $webhookEvents = [
        'created',
        'updated',
        'deleted'
    ];
    
    // Optional: Specify properties to watch
    protected static $webhookProperties = [
        'email',
        'status'
    ];
    
    // Optional: Properties to exclude from webhooks
    protected static $webhookHidden = [
        'password',
        'remember_token'
    ];
}
```

## Testing the Setup

### 1. Test API Connectivity

```bash
# Test basic health check
curl -H "X-N8n-Api-Key: your-api-secret-key-here" \
     http://localhost:8000/api/n8n/health

# Test model discovery
curl -H "X-N8n-Api-Key: your-api-secret-key-here" \
     http://localhost:8000/api/n8n/models
```

### 2. Test n8n Integration

1. Create a new workflow in n8n
2. Add a **Laravel Eloquent Trigger** node
3. Select your Laravel Eloquent API credentials
4. Verify the **Model** dropdown populates with available models
5. Configure the trigger and activate the workflow

### 3. Test Webhook Registration

1. Activate a workflow with a Laravel Eloquent Trigger
2. Check the n8n console for webhook registration messages
3. Verify the webhook appears in Laravel:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key-here" \
        http://localhost:8000/api/n8n/webhooks
   ```

## Security Considerations

1. **API Security**
   - Use HTTPS in production
   - Generate your own strong secrets using the methods shown above
   - Consider IP whitelisting for production environments

2. **Webhook Security**
   - HMAC verification is enabled by default
   - Timestamp validation prevents replay attacks
   - Rate limiting is enabled by default

3. **Data Security**
   - Configure model property visibility using `$webhookProperties` and `$webhookHidden`
   - Use event filters to limit which events trigger webhooks
   - Implement proper access controls

## Health Monitoring

The package includes built-in health monitoring:

```bash
# Check webhook health
php artisan n8n:webhooks:health

# List active subscriptions
php artisan n8n:webhooks:list

# Clean up inactive subscriptions
php artisan n8n:webhooks:cleanup

# Test webhook delivery
php artisan n8n:webhooks:test

# Recover failed subscriptions
php artisan n8n:webhooks:recover
```

## Troubleshooting

See our [Troubleshooting Guide](troubleshooting.md) for common issues and solutions.

## Next Steps

- [Node Documentation](nodes.md)
- [Security Guide](security.md)
- [API Reference](api.md)
