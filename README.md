# Laravel n8n Eloquent Integration

A Laravel package that enables seamless integration between Laravel Eloquent models and n8n workflows.

## Features

- **Model Discovery**: Automatically discover and expose Laravel Eloquent models to n8n
- **Event Triggering**: Trigger n8n workflows based on model events (created, updated, deleted)
- **Property Events**: Trigger workflows on property get/set operations
- **Model Operations**: Allow n8n to read and write model data
- **Secure Communication**: API key authentication with HMAC signature verification
- **Configurability**: Whitelist/blacklist models, configure events per model

## Installation

### Laravel Package

```bash
composer require n8n/eloquent
```

After installing the package, publish the configuration file:

```bash
php artisan vendor:publish --tag=n8n-eloquent-config
```

Set up your `.env` file with the following variables:

```
N8N_ELOQUENT_API_SECRET=your-secret-key
N8N_URL=http://your-n8n-instance.com
```

### n8n Extension

Install the n8n Eloquent node extension in your n8n instance:

```bash
npm install n8n-nodes-laravel-eloquent
```

## Configuration

### Model Discovery

By default, the package discovers models in the `app/Models` directory. You can configure this in the `config/n8n-eloquent.php` file:

```php
'models' => [
    'namespace' => 'App\\Models',
    'directory' => app_path('Models'),
    'mode' => 'whitelist', // 'whitelist', 'blacklist', or 'all'
    'whitelist' => [
        'App\\Models\\User',
    ],
    'blacklist' => [
        'App\\Models\\PasswordReset',
    ],
],
```

### Event Configuration

Configure which events trigger n8n workflows:

```php
'events' => [
    'default' => ['created', 'updated', 'deleted'],
    'property_events' => [
        'enabled' => true,
        'default' => [],
    ],
],
```

### Model-specific Configuration

Configure events and properties for specific models:

```php
'models' => [
    // ...
    'config' => [
        'App\\Models\\User' => [
            'events' => ['created', 'updated', 'deleted'],
            'getters' => ['name', 'email'],
            'setters' => ['name', 'email'],
        ],
    ],
],
```

## Usage

### Laravel Side

To enable n8n integration for a model, use the `HasN8nEvents` trait:

```php
use N8n\Eloquent\Traits\HasN8nEvents;

class User extends Model
{
    use HasN8nEvents;
    
    // ...
}
```

This will automatically register the model with the n8n integration.

### n8n Side

In n8n, you can:

1. Trigger workflows on model events
2. Get model data
3. Update model data

## Example: User Creation Workflow

1. When a new User model is created in Laravel, it triggers an n8n workflow
2. The workflow sends a welcome email using Mailgrid
3. It also updates a counter in another model called UserCounter

## API Endpoints

The package exposes the following API endpoints:

- `GET /api/n8n/models`: List all available models
- `GET /api/n8n/models/{model}`: Get model metadata
- `GET /api/n8n/models/{model}/properties`: Get model properties
- `GET /api/n8n/models/{model}/records`: List model records
- `GET /api/n8n/models/{model}/records/{id}`: Get a specific record
- `POST /api/n8n/models/{model}/records`: Create a new record
- `PUT /api/n8n/models/{model}/records/{id}`: Update a record
- `DELETE /api/n8n/models/{model}/records/{id}`: Delete a record
- `POST /api/n8n/webhooks/subscribe`: Subscribe to model events
- `DELETE /api/n8n/webhooks/unsubscribe`: Unsubscribe from model events

## Security

The package uses API key authentication and HMAC signature verification for secure communication between Laravel and n8n.

## License

MIT 