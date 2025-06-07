# Laravel n8n Eloquent Integration

[![Tests](https://img.shields.io/badge/tests-58%20passing-brightgreen)](https://github.com/n8n-io/n8n-eloquent)
[![Coverage](https://img.shields.io/badge/coverage-270%20assertions-brightgreen)](https://github.com/n8n-io/n8n-eloquent)
[![Laravel](https://img.shields.io/badge/laravel-8.x%20to%2012.x-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

A production-ready Laravel package that enables seamless bi-directional integration between Laravel Eloquent models and n8n workflows.

## ✨ Features

- **🔍 Model Discovery**: Automatically discover and expose Laravel Eloquent models to n8n
- **⚡ Event Triggering**: Trigger n8n workflows based on model events (created, updated, deleted, etc.)
- **🎯 Property Events**: Trigger workflows on property get/set operations with rate limiting
- **🔄 Model Operations**: Allow n8n to read, create, update, and delete model data
- **🔐 Secure Communication**: API key authentication with HMAC signature verification
- **⚙️ Advanced Configuration**: Whitelist/blacklist models, granular event control, watched attributes
- **🚀 Performance**: Built-in caching, rate limiting, and queue support
- **🛠️ Management Tools**: Comprehensive artisan commands for setup and monitoring
- **🧪 Production Ready**: 58 tests with 270 assertions, enterprise-grade error handling

## Installation

### Laravel Package

```bash
composer require n8n/eloquent
```

After installing the package, run the setup command:

```bash
php artisan n8n:setup
```

This will:
- Publish the configuration file
- Generate an API secret key
- Update your `.env` file
- Display setup instructions

Alternatively, you can manually publish the configuration file:

```bash
php artisan vendor:publish --tag=n8n-eloquent-config
```

And set up your `.env` file with the following variables:

```
N8N_ELOQUENT_API_SECRET=your-secret-key
N8N_URL=http://your-n8n-instance.com
```

### n8n Extension

Install the n8n Eloquent node extension in your n8n instance:

```bash
npm install n8n-nodes-laravel-eloquent
```

## Artisan Commands

The package provides several artisan commands for management:

### Setup Command
```bash
# Interactive setup with automatic configuration
php artisan n8n:setup

# Setup with custom API secret
php artisan n8n:setup --api-secret=your-custom-secret

# Force overwrite existing configuration
php artisan n8n:setup --force
```

### Status Command
```bash
# Show basic status information
php artisan n8n:status

# Show detailed status with configuration details
php artisan n8n:status --detailed
```

### Model Registration Command
```bash
# Register all models in whitelist mode
php artisan n8n:register-models --whitelist

# Register all models in blacklist mode
php artisan n8n:register-models --blacklist

# Register all discovered models
php artisan n8n:register-models --all

# Register a specific model
php artisan n8n:register-models --model="App\Models\User"
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
            'watched_attributes' => ['name', 'email'], // Only trigger update events for these
            'queue_events' => false,
            'queue_name' => 'default',
        ],
    ],
],
```

### Advanced Configuration

```php
'events' => [
    'enabled' => true,
    'default' => ['created', 'updated', 'deleted'],
    'property_events' => [
        'enabled' => true,
        'default' => [],
        'skip_unchanged' => true,
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 10,
            'decay_minutes' => 1,
        ],
    ],
    'transactions' => [
        'enabled' => true,
        'rollback_on_error' => true,
    ],
    'queue' => [
        'enabled' => false,
        'name' => 'default',
    ],
],

'api' => [
    'rate_limiting' => [
        'enabled' => true,
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],
],
```

## Usage

### Laravel Side

To enable n8n integration for a model, add it to your configuration in `config/n8n-eloquent.php`:

```php
'models' => [
    'mode' => 'whitelist', // or 'blacklist' or 'all'
    'whitelist' => [
        'App\\Models\\User',
    ],
    'config' => [
        'App\\Models\\User' => [
            'events' => ['created', 'updated', 'deleted'],
            'watched_attributes' => ['name', 'email'], // Only trigger update events for these
            'getters' => ['name', 'email'], // Optional: trigger events when these properties are accessed
            'setters' => ['name', 'email'], // Optional: trigger events when these properties are changed
        ],
    ],
],
```

The package will automatically register the model events with n8n based on your configuration. No trait or model modifications are required!

### n8n Side

In n8n, you can:

1. Trigger workflows on model events
2. Get model data
3. Update model data

## Example: User Creation Workflow

1. When a new User model is created in Laravel, it triggers an n8n workflow
2. The workflow sends a welcome email using Mailgrid
3. It also updates a counter in another model called UserCounter

## 🌐 API Endpoints

The package exposes a comprehensive REST API for n8n integration:

### Model Discovery & Metadata
- `GET /api/n8n/models` - List all available models
- `GET /api/n8n/models/{model}` - Get model metadata
- `GET /api/n8n/models/{model}/properties` - Get model properties

### Model Operations
- `GET /api/n8n/models/{model}/records` - List model records
- `GET /api/n8n/models/{model}/records/{id}` - Get a specific record
- `POST /api/n8n/models/{model}/records` - Create a new record
- `PUT /api/n8n/models/{model}/records/{id}` - Update a record
- `DELETE /api/n8n/models/{model}/records/{id}` - Delete a record

### Webhook Management
- `POST /api/n8n/webhooks/subscribe` - Subscribe to model events
- `DELETE /api/n8n/webhooks/unsubscribe` - Unsubscribe from model events
- `GET /api/n8n/webhooks` - List webhook subscriptions (with filtering)
- `GET /api/n8n/webhooks/stats` - Get webhook statistics
- `POST /api/n8n/webhooks/bulk` - Bulk webhook operations
- `GET /api/n8n/webhooks/{id}` - Get specific subscription
- `PUT /api/n8n/webhooks/{id}` - Update subscription
- `POST /api/n8n/webhooks/{id}/test` - Test webhook

All endpoints require authentication via `X-N8n-Api-Key` header.

## 🔐 Security

- **API Key Authentication**: Secure endpoints with configurable API keys
- **HMAC Signature Verification**: Ensure data integrity with HMAC signatures
- **Rate Limiting**: Configurable rate limits to prevent abuse
- **Input Validation**: Comprehensive validation of all inputs
- **Error Handling**: Secure error responses without sensitive data exposure

## 🧪 Testing

The package includes comprehensive test coverage:

```bash
# Run all tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Run specific test suites
vendor/bin/phpunit tests/Unit/
vendor/bin/phpunit tests/Feature/
```

**Test Statistics:**
- 58 tests with 270 assertions
- Unit tests for services and components
- Feature tests for API endpoints and commands
- Integration tests for end-to-end workflows

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [Full documentation](https://github.com/n8n-io/n8n-eloquent/wiki)
- **Issues**: [GitHub Issues](https://github.com/n8n-io/n8n-eloquent/issues)
- **Discussions**: [GitHub Discussions](https://github.com/n8n-io/n8n-eloquent/discussions)

## 🎯 Roadmap

- [ ] **Phase 2**: n8n Extension Development
- [ ] **Phase 3**: Advanced Workflow Templates
- [ ] **Phase 4**: Performance Optimizations
- [ ] **Phase 5**: Enterprise Features

---

**Made with ❤️ for the Laravel and n8n communities** 