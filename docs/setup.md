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

### 4. Install n8n Nodes

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

### 5. Configure Environment

Add these variables to your `.env` file:

```env
# n8n Connection
N8N_WEBHOOK_URL=https://your-n8n-instance.com/webhook/path
N8N_WEBHOOK_SECRET=your-secret-key

# Optional Configuration
N8N_WEBHOOK_TIMEOUT=30
N8N_WEBHOOK_RETRY_ATTEMPTS=3
N8N_WEBHOOK_COOLDOWN_MINUTES=5
```

### 6. Configure Your Models

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
}
```

### 7. Configure Events

The package now supports separate configuration for event listeners and event dispatchers:

#### Event Listeners (for n8n trigger nodes)

Configure which events can be listened to by n8n trigger nodes:

```php
// config/n8n-eloquent.php
'event_listeners' => [
    // List specific events that can be listened to
    'available' => [
        'App\\Events\\UserRegistered',
        'App\\Events\\OrderShipped',
    ],
    
    // Or use discovery mode with whitelist/blacklist
    'discovery' => [
        'mode' => 'whitelist', // 'all', 'whitelist', 'blacklist'
        'whitelist' => [
            'App\\Events\\UserRegistered',
            'App\\Events\\OrderShipped',
        ],
        'blacklist' => [
            'App\\Events\\InternalEvent',
        ],
    ],
],
```

#### Event Dispatchers (for n8n action nodes)

Configure which events can be dispatched by n8n action nodes:

```php
// config/n8n-eloquent.php
'event_dispatchers' => [
    // Only events listed here can be dispatched
    'available' => [
        'App\\Events\\SendEmailEvent',
        'App\\Events\\ProcessDataEvent',
    ],
],
```

### 8. Configure n8n

1. Open your n8n instance
2. Go to Settings → Credentials
3. Add new credential of type "Laravel Eloquent API"
4. Configure:
   - Base URL: Your Laravel application URL
   - API Key: Your webhook secret
   - HMAC Secret: Your HMAC secret (if using)

## Security Considerations

1. **API Security**
   - Use HTTPS in production
   - Set strong secrets
   - Consider IP whitelisting

2. **Webhook Security**
   - Enable HMAC verification
   - Use rate limiting
   - Monitor webhook health

3. **Data Security**
   - Configure model property visibility
   - Use event filters
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
```

## Troubleshooting

See our [Troubleshooting Guide](troubleshooting.md) for common issues and solutions.

## Next Steps

- [Node Documentation](nodes.md)
- [Security Guide](security.md)
- [API Reference](api.md)
