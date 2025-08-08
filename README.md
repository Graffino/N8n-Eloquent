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
  - Support for all model lifecycle events (create, update, delete, restore, saving, saved)
  - Targeted property change tracking with configurable field visibility
  - Automatic webhook lifecycle management with health monitoring

- 🎯 **Job & Event System**
  - Dispatch Laravel jobs from n8n workflows with parameter validation
  - Listen for and dispatch custom Laravel events
  - Automatic job/event discovery and registration
  - Queue management with configurable options
  - Metadata tracking for workflow context

- 🔐 **Enterprise-Grade Security**
  - Multi-layer security architecture with HMAC-SHA256 signature verification
  - API key authentication with timing-safe comparisons
  - IP whitelisting with CIDR support for webhook sources
  - Timestamp validation to prevent replay attacks

### Available Nodes

1. **Laravel Eloquent Trigger Node**
   - Watch for model events (create, update, delete, restore, saving, saved)
   - Filter by specific model properties with advanced operators
   - Configure security settings (HMAC verification, IP filtering, timestamp validation)
   - Real-time event broadcasting with metadata tracking

2. **Laravel Event Listener Node** ⭐ **NEW**
   - Listen for custom Laravel events and trigger n8n workflows
   - Automatic event discovery and webhook registration
   - Full event payload serialization with metadata tracking
   - Loop prevention with n8n metadata detection
   - Security settings (HMAC verification, IP filtering, timestamp validation)

3. **Laravel Event Dispatcher Node** ⭐ **NEW**
   - Dispatch any Laravel event from n8n workflows
   - Automatic event discovery and parameter loading
   - Dynamic parameter validation and type checking
   - Metadata tracking for workflow context
   - Security-first approach with configurable options

4. **Laravel Eloquent CRUD Node** ⭐ **CONSOLIDATED**
   - **Unified Operations**: Create, read, update, and delete model records in a single node
   - **Advanced Filtering**: Multiple operators (equals, not equals, greater than, less than, like, in)
   - **Relationship Support**: Include related models with dynamic loading
   - **Pagination & Sorting**: Configurable limits, offsets, and order by clauses
   - **Batch Operations**: Support for multiple record operations
   - **Enhanced Error Handling**: Comprehensive validation and error categorization

5. **Laravel Job Dispatcher Node** ⭐ **ENHANCED**
   - Dispatch Laravel jobs from n8n workflows with full parameter validation
   - **Security-First Approach**: Only configured jobs are discoverable and dispatchable
   - **Multiple Dispatch Modes**: Immediate, delayed, and synchronous execution
   - **Queue Management**: Configurable queues, connections, and advanced options
   - **Automatic Parameter Discovery**: Dynamic loading of job constructor parameters
   - **Metadata Tracking**: Jobs include workflow and execution context information

### Advanced Capabilities

- **🔭 Laravel Telescope Integration**: Monitor all n8n-related activities with custom tagging
- **Health Monitoring**: Automatic subscription recovery and health status tracking
- **Comprehensive Logging**: Detailed audit trails with n8n-specific tags for easy filtering
- **Error Recovery**: Robust error handling with automatic retry mechanisms
- **Performance Optimization**: Efficient request handling with minimal overhead
- **Scalability**: Designed for high-volume webhook processing with concurrent support

## ⚠️ Security Warning

**SSL Certificate Validation**: This package allows connecting to non-SSL certificated APIs (HTTP) by skipping all certificate validations for development and testing purposes. However, **we strongly recommend against using public non-HTTPS websites in production environments**.

**Security Best Practices**:

- Always use HTTPS in production environments
- Ensure your Laravel application and n8n instance communicate over secure connections
- Regularly update your SSL certificates
- Consider using a reverse proxy (like nginx) with proper SSL termination
- Monitor your webhook endpoints for any suspicious activity

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

### Event Listener Quick Start

1. Create a custom Laravel event:

```php
use Shortinc\N8nEloquent\Events\BaseEvent;

class UserRegistered extends BaseEvent
{
    public $user;
    
    public function __construct($user)
    {
        $this->user = $user;
    }
}
```

2. Create a workflow in n8n:
   - Add the "Laravel Event Listener" node
   - Select your custom event
   - Configure security settings
   - Connect to other nodes
   - Activate the workflow

3. Dispatch the event from Laravel:

```php
event(new UserRegistered($user)); // Will trigger n8n workflow
```

### Event Dispatcher Quick Start

1. Create a workflow in n8n:
   - Add the "Laravel Event Dispatcher" node
   - Select from available Laravel events
   - Configure event parameters
   - Connect to other nodes
   - Activate the workflow

2. Dispatch events from n8n workflows with full parameter validation and metadata tracking.

### Job Dispatcher Quick Start

1. Configure available jobs in `config/n8n-eloquent.php`:

```php
'jobs' => [
    'available' => [
        'App\\Jobs\\SendEmailJob',
        'App\\Jobs\\ProcessDataJob',
    ],
],
```

2. Create a workflow in n8n:
   - Add the "Laravel Job Dispatcher" node
   - Select from your configured jobs
   - Set parameters and queue options
   - Activate the workflow

3. Dispatch jobs from n8n workflows with full parameter validation and queue management.

## 📚 Documentation

- [Complete Setup Guide](docs/setup.md)
- [Node Documentation](docs/nodes.md)
- [Security Guide](docs/security.md)
- [Webhook Testing Guide](WEBHOOK_TESTING_GUIDE.md)
- [API Reference](docs/api.md)
- [Troubleshooting](docs/troubleshooting.md)

## 🗺️ Roadmap

### Future Plans (Q4 2025)

1. **Laravel Cache Node**
   - Cache operations with multiple store support
   - Atomic operations and cache tagging
   - Practical examples: API response caching, analytics storage, session management

2. **Laravel Queue Node**
   - Queue management and worker control
   - Failed job handling and retry mechanisms
   - Practical examples: job monitoring, queue optimization, workload distribution

3. **Laravel Notification Node**
   - Send Laravel notifications through multiple channels
   - Template system with dynamic content
   - Practical examples: multi-channel alerts, marketing communications, appointment reminders
  
4. **🔭 Laravel Telescope Integration**
   - Advanced debugging and monitoring dashboard with n8n-specific panels
   - Real-time request tracking and performance profiling
   - Custom n8n tagging for easy log filtering and analysis
   - Detailed webhook and job execution monitoring
   - Automatic model discovery with dynamic field loading
   - Health monitoring with subscription recovery mechanisms
   - Integration with Laravel's error tracking and reporting

### Under Consideration

- **Laravel Broadcasting Node**: Real-time broadcasting for chat applications, live dashboards, and collaborative features
- **Laravel File Storage Node**: File operations across different storage providers with backup and sharing capabilities
- **Laravel Mail Node**: Email management with templates, queuing, and delivery tracking
- **Laravel Schedule Node**: Task scheduling for backups, cleanup, and recurring operations
- **Laravel Validation Node**: Data validation with custom rules and form validation logic

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## 📜 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

<div align="center">
Built with ❤️ by Short Inc.<br>
Powered by n8n & Laravel
</div>
