# n8n-eloquent

<div align="center">
  <img src="docs/assets/logo.png" alt="n8n-eloquent Logo" width="200"/>
  <h3>Seamless Laravel Eloquent Integration for n8n</h3>
  <p>Build powerful workflows with your Laravel models</p>
</div>

<div align="center">
  <a href="#features">Features</a> •
  <a href="#installation">Installation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#documentation">Documentation</a> •
  <a href="#roadmap">Roadmap</a>
</div>

## 🌟 Features

### Current Features
- 🔄 **Model Event Integration**
  - Automatic webhook registration for Eloquent models
  - Real-time model event broadcasting to n8n
  - Support for model lifecycle events (create, update, delete)
  - Targeted property change tracking
  
- 🔐 **Security & Reliability**
  - Secure webhook endpoints with HMAC verification
  - IP whitelisting support
  - Automatic retry mechanism
  - Health monitoring and subscription management
  
- 🛠️ **Developer Tools**
  - Command-line tools for setup and maintenance
  - Comprehensive debugging with Laravel Telescope integration
  - Detailed logging and monitoring
  - Postman collection for API testing

### Available Nodes
1. **Laravel Eloquent Trigger Node**
   - Watch for model events (create, update, delete)
   - Filter by specific model properties
   - Configure security settings
   
2. **Laravel Eloquent CRUD Node**
   - Create, read, update, and delete model records
   - Batch operations support
   - Dynamic field mapping
   - Relationship handling

## 📦 Installation

1. Install via composer:
```bash
composer require shortinc/n8n-eloquent
```

2. Install n8n nodes:
```bash
cd n8n-extension
npm install
npm run build
```

3. Configure your `.env`:
```env
N8N_WEBHOOK_URL=https://your-n8n-instance.com/webhook/path
N8N_WEBHOOK_SECRET=your-secret-key
```

## 🚀 Quick Start

1. Add the `HasWebhooks` trait to your model:
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
}
```

2. Create a workflow in n8n:
   - Add the "Laravel Eloquent Trigger" node
   - Select your model and events
   - Connect to other nodes
   - Activate the workflow

3. Test the integration:
```php
User::create(['name' => 'Test User']); // Will trigger n8n workflow
```

## 📚 Documentation

- [Complete Setup Guide](docs/setup.md)
- [Node Documentation](docs/nodes.md)
- [Security Guide](docs/security.md)
- [Webhook Testing Guide](WEBHOOK_TESTING_GUIDE.md)
- [API Reference](docs/api.md)
- [Troubleshooting](docs/troubleshooting.md)

## 🗺️ Roadmap

### Coming Soon (Q3 2025)
1. **Laravel Event Dispatcher Node**
   - Dispatch any Laravel event
   - Support for broadcasting
   - Event payload builder
   
2. **Laravel Event Listener Node**
   - Listen for Laravel events
   - Event filtering and routing
   - Error recovery
   
3. **Laravel Job Dispatcher Node**
   - Dispatch Laravel jobs
   - Job scheduling and delays
   - Queue driver selection
   - Job status monitoring

### Future Plans (Q4 2025)
1. **Laravel Cache Node**
   - Cache operations
   - Multiple store support
   - Atomic operations
   
2. **Laravel Queue Node**
   - Queue management
   - Worker control
   - Failed job handling
   
3. **Laravel Notification Node**
   - Send Laravel notifications
   - Multiple channel support
   - Template system

### Under Consideration
- Laravel Broadcasting Node
- Laravel File Storage Node
- Laravel Mail Node
- Laravel Schedule Node
- Laravel Validation Node

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## 📜 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

<div align="center">
Built with ❤️ by Short Inc.<br>
Powered by n8n & Laravel
</div> 