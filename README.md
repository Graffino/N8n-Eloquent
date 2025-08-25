# n8n-eloquent

Seamless Laravel Eloquent integration for n8n workflows.

## Features

- **Model Event Integration**: Automatic webhook registration for Eloquent models with real-time event broadcasting
- **Security & Reliability**: Secure webhook endpoints with HMAC verification and IP whitelisting
- **Developer Tools**: Command-line tools for setup, maintenance, and debugging

## Available Nodes

This package provides several n8n community nodes that you need to install separately:

1. **Laravel Eloquent Trigger Node**
   - Watch for model events (create, update, delete)
   - Filter by specific model properties
   - Configure security settings
   
2. **Laravel Eloquent CRUD Node**
   - Create, read, update, and delete model records
   - Batch operations support
   - Dynamic field mapping
   - Relationship handling

3. **Laravel Job Dispatch Node**
   - Dispatch any Laravel job
   - Auto-discovery of job classes
   - Constructor parameter reflection
   - Queue selection and delay configuration
   - Job status tracking

4. **Laravel Event Listener Node**
   - Listen for Laravel events
   - Event filtering and routing
   - Error recovery

5. **Laravel Event Dispatcher Node**
   - Dispatch any Laravel event
   - Support for broadcasting
   - Event payload builder

## Installation

### 1. Install the Laravel Package

```bash
composer require shortinc/n8n-eloquent
```

### 2. Install the n8n Community Nodes

You must install the corresponding community nodes in n8n for this to work:

**Using n8n Interface:**
1. Go to Settings > Community Nodes
2. Click Install
3. Enter the npm package name for each node you want to use

**Manual Installation:**
```bash
# Access your n8n container
docker exec -it n8n sh

# Navigate to nodes directory
mkdir -p ~/.n8n/nodes
cd ~/.n8n/nodes

# Install the nodes
npm install n8n-nodes-laravel-eloquent-crud
npm install n8n-nodes-laravel-eloquent-trigger
npm install n8n-nodes-laravel-job-dispatch
npm install n8n-nodes-laravel-event-listener
npm install n8n-nodes-laravel-event-dispatcher

# Restart n8n
```

### 3. Configure Laravel

```bash
# Publish configuration
php artisan vendor:publish --provider="Shortinc\N8nEloquent\N8nEloquentServiceProvider"

# Run migrations
php artisan migrate
```

### 4. Set Environment Variables

Add to your `.env` file:
```env
N8N_WEBHOOK_URL=https://your-n8n-instance.com/webhook/path
N8N_WEBHOOK_SECRET=your-secret-key
```

## Quick Start

1. Models are automatically discovered and registered for webhooks
2. Create a workflow in n8n using the Laravel nodes
3. Test by creating/updating models in Laravel

## Documentation

- [Setup Guide](docs/setup.md)
- [Node Documentation](docs/nodes.md)
- [Security Guide](docs/security.md)
- [API Reference](docs/api.md)
- [Troubleshooting](docs/troubleshooting.md)

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

---

Built with ❤️ by Short Inc. Assisted by AI