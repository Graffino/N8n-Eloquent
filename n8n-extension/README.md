# n8n Laravel Eloquent Integration

Seamless Laravel Eloquent Integration for n8n - Build powerful workflows with your Laravel models.

## Features

### Current Features

- **Model Event Integration**
  - Automatic webhook registration for Eloquent models
  - Real-time model event broadcasting to n8n
  - Support for all model lifecycle events (create, update, delete, restore, saving, saved)
  - Targeted property change tracking with configurable field visibility
  - Automatic webhook lifecycle management with health monitoring

- **Job & Event System**
  - Dispatch Laravel jobs from n8n workflows with parameter validation
  - Listen for and dispatch custom Laravel events
  - Automatic job/event discovery and registration
  - Queue management with configurable options
  - Metadata tracking for workflow context

- **Enterprise-Grade Security**
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

2. **Laravel Event Listener Node**
   - Listen for custom Laravel events and trigger n8n workflows
   - Automatic event discovery and webhook registration
   - Full event payload serialization with metadata tracking
   - Loop prevention with n8n metadata detection
   - Security settings (HMAC verification, IP filtering, timestamp validation)

3. **Laravel Event Dispatcher Node**
   - Dispatch any Laravel event from n8n workflows
   - Automatic event discovery and parameter loading
   - Dynamic parameter validation and type checking
   - Metadata tracking for workflow context
   - Security-first approach with configurable options

4. **Laravel Eloquent CRUD Node**
   - Unified Operations: Create, read, update, and delete model records in a single node
   - Advanced Filtering: Multiple operators (equals, not equals, greater than, less than, like, in)
   - Relationship Support: Include related models with dynamic loading
   - Pagination & Sorting: Configurable limits, offsets, and order by clauses
   - Batch Operations: Support for multiple record operations
   - Enhanced Error Handling: Comprehensive validation and error categorization

5. **Laravel Job Dispatcher Node**
   - Dispatch Laravel jobs from n8n workflows with full parameter validation
   - Security-First Approach: Only configured jobs are discoverable and dispatchable
   - Multiple Dispatch Modes: Immediate, delayed, and synchronous execution
   - Queue Management: Configurable queues, connections, and advanced options
   - Automatic Parameter Discovery: Dynamic loading of job constructor parameters
   - Metadata Tracking: Jobs include workflow and execution context information

### Advanced Capabilities

- Laravel Telescope Integration: Monitor all n8n-related activities with custom tagging
- Health Monitoring: Automatic subscription recovery and health status tracking
- Comprehensive Logging: Detailed audit trails with n8n-specific tags for easy filtering
- Error Recovery: Robust error handling with automatic retry mechanisms
- Performance Optimization: Efficient request handling with minimal overhead
- Scalability: Designed for high-volume webhook processing with concurrent support

## Installation

### Prerequisites

1. **Laravel Package**: First, install the Laravel n8n Eloquent package in your Laravel application:

   ```bash
   composer require shortinc/n8n-eloquent
   ```

2. **n8n Instance**: You need a running n8n instance (self-hosted or n8n Cloud)

### Community Nodes (Recommended)

1. Go to **Settings > Community Nodes**
2. Select **Install**
3. Enter: `@shortinc/n8n-eloquent-nodes`
4. Click **Install**

### Manual Installation

```bash
npm install @shortinc/n8n-eloquent-nodes
```

### Building from Source

```bash
# Clone the repository
git clone https://github.com/shortinc/n8n-eloquent.git
cd n8n-eloquent/n8n-extension

# Install dependencies
npm install

# Build the project
npm run build

# Run linting
npm run lint
```

## Configuration

### 1. Laravel Setup

Configure your Laravel application with the n8n Eloquent package:

```bash
# Publish configuration
php artisan vendor:publish --provider="Shortinc\N8nEloquent\Providers\ShortincN8nEloquentServiceProvider"

# Run setup command
php artisan n8n:setup
```

### 2. n8n Credentials Setup

1. In n8n, go to **Credentials** → **Add Credential**
2. Search for "Laravel Eloquent API"
3. Configure:
   - **Base URL**: Your Laravel application URL (e.g., `https://your-app.com`)
   - **API Key**: Generated during Laravel setup
   - **HMAC Secret**: (Optional) For webhook signature verification

### 3. Environment Configuration

Configure your `.env`:

```env
N8N_WEBHOOK_URL=https://your-n8n-instance.com/webhook/path
N8N_WEBHOOK_SECRET=your-secret-key
```

## Quick Start

### Model Event Integration

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

## Node Documentation

### Laravel Eloquent Trigger

Triggers workflows when Laravel model events occur.

**Configuration:**
- **Model**: Laravel model class (e.g., `App\Models\User`)
- **Events**: Select which events to listen for:
  - `created` - When a new record is created
  - `updated` - When a record is updated
  - `deleted` - When a record is deleted
  - `restored` - When a soft-deleted record is restored
  - `saving` - Before a record is saved
  - `saved` - After a record is saved
- **Verify HMAC Signature**: Enable/disable signature verification
- **Require Timestamp Validation**: Reject webhooks older than 5 minutes
- **Expected Source IP**: Restrict webhooks to specific IP addresses

**Output:**
```json
{
  "event": "created",
  "model": "App\\Models\\User",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  },
  "changes": {},
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Laravel Event Listener

Triggers workflows when custom Laravel events are dispatched.

**Configuration:**
- **Event**: Laravel event class (e.g., `App\Events\UserRegistered`)
- **Verify HMAC Signature**: Enable/disable signature verification
- **Require Timestamp Validation**: Reject webhooks older than 5 minutes
- **Expected Source IP**: Restrict webhooks to specific IP addresses

**Output:**
```json
{
  "event": "dispatched",
  "event_class": "App\\Events\\UserRegistered",
  "data": {
    "userId": 123,
    "action": "user_login",
    "data": {
      "ip": "192.168.1.1",
      "userAgent": "Mozilla/5.0..."
    }
  },
  "metadata": {
    "source_trigger": {
      "node_id": "webhook-node-123",
      "workflow_id": "workflow-456",
      "event": "App\\Events\\UserRegistered",
      "timestamp": "2024-01-15T10:30:00Z"
    }
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Laravel Eloquent CRUD

Performs Create, Read, Update, and Delete operations on Laravel models.

**Operations:**
- **Create**: Create new records
- **Get All Records**: Retrieve all records with pagination and filtering
- **Get Record by ID**: Fetch a specific record by ID
- **Update**: Update existing records
- **Delete**: Delete records

**Configuration:**
- **Model**: Laravel model class
- **Operation**: Choose the CRUD operation to perform
- **Fields**: Define field names and values (for Create/Update)
- **Record ID**: Required for Get by ID/Update/Delete operations
- **Pagination**: Limit and offset for Get All operation
- **Additional Fields**:
  - **Where Conditions**: Advanced filtering with multiple operators
  - **Order By**: Sort results by multiple fields

**Example Where Conditions:**
```json
{
  "conditions": [
    {
      "field": "status",
      "operator": "=",
      "value": "active"
    },
    {
      "field": "created_at",
      "operator": ">=",
      "value": "2024-01-01"
    }
  ]
}
```

**Example Order By:**
```json
{
  "orders": [
    {
      "field": "created_at",
      "direction": "desc"
    },
    {
      "field": "name",
      "direction": "asc"
    }
  ]
}
```

## Workflow Examples

### Example 1: User Registration Notification
```
Laravel Eloquent Trigger (User created) 
→ Send Email Node 
→ Slack Notification
```

### Example 2: Data Synchronization
```
Schedule Trigger 
→ Laravel Eloquent CRUD (Get All Records) 
→ Transform Data 
→ External API Call
```

### Example 3: Order Processing
```
Laravel Eloquent Trigger (Order created) 
→ IF Node (check order amount) 
→ Laravel Eloquent CRUD (Update order status) 
→ Send confirmation email
```

### Example 4: Event-Driven Workflow
```
Laravel Event Listener (UserRegistered) 
→ Send Welcome Email 
→ Create User Profile 
→ Notify Admin
```

### Example 5: Job Processing Pipeline
```
Laravel Job Dispatcher (ProcessDataJob) 
→ Wait for Completion 
→ Laravel Event Dispatcher (DataProcessed) 
→ Update Dashboard
```

## Security

### Multi-Layer Security Architecture

Our extension implements comprehensive security measures to ensure safe communication:

#### 1. API Key Authentication
- All requests use API key authentication via `X-N8n-Api-Key` header
- Strong, randomly generated keys during Laravel package setup
- Support for key rotation and environment-specific keys

#### 2. HMAC Signature Verification
- HMAC-SHA256 signature verification for webhook payloads
- Timing-safe comparison to prevent timing attacks
- Configurable per trigger node with `verifyHmac` option
- Signature sent in `X-Laravel-Signature` header

#### 3. Timestamp Validation (Replay Attack Prevention)
- Validates webhook timestamps to prevent replay attacks
- Configurable time window (default: 5 minutes)
- Ensures webhook freshness and prevents captured payload reuse

#### 4. IP Address Restriction
- Optional IP address or CIDR range filtering
- Supports both single IP and subnet restrictions
- Configurable per trigger node for granular control

#### 5. Model and Event Validation
- Validates incoming webhooks match configured models and events
- Prevents unauthorized model access and cross-model data leakage
- Ensures webhook authenticity and integrity

#### 6. Enhanced Error Handling
- Comprehensive security violation logging
- Sanitized error messages (no sensitive data exposure)
- Detailed categorization of authentication and validation errors

### Security Configuration Example

```typescript
// Laravel Eloquent Trigger Node Configuration
{
  "model": "App\\Models\\User",
  "events": ["created", "updated"],
  "verifyHmac": true,
  "requireTimestamp": true,
  "expectedSourceIp": "192.168.1.0/24"
}
```

### Best Practices

- **Always use HTTPS** for all communications in production
- **Enable HMAC verification** for production environments
- **Regularly rotate API keys** (recommended: every 90 days)
- **Use IP restrictions** to limit access to known sources
- **Monitor security logs** for unusual activity
- **Keep timestamps synchronized** between systems
- **Use strong secrets** (minimum 32 characters for HMAC)

## Development

### Testing

```bash
# Run tests (when available)
npm test

# Type checking
npm run build
```

## API Reference

### Laravel Package Endpoints

The Laravel package provides these API endpoints:

- `GET /api/n8n/models` - List available models
- `GET /api/n8n/models/{model}` - Get all records
- `GET /api/n8n/models/{model}/{id}` - Get specific record
- `POST /api/n8n/models/{model}` - Create record
- `PUT /api/n8n/models/{model}/{id}` - Update record
- `DELETE /api/n8n/models/{model}/{id}` - Delete record
- `POST /api/n8n/models/{model}/upsert` - Upsert record
- `POST /api/n8n/webhooks/subscribe` - Subscribe to events
- `DELETE /api/n8n/webhooks/unsubscribe` - Unsubscribe from events
- `POST /api/n8n/events/dispatch` - Dispatch custom events
- `POST /api/n8n/jobs/dispatch` - Dispatch Laravel jobs

## Documentation

- [Complete Setup Guide](../docs/setup.md)
- [Node Documentation](../docs/nodes.md)
- [Security Guide](../docs/security.md)
- [Webhook Testing Guide](../WEBHOOK_TESTING_GUIDE.md)
- [API Reference](../docs/api.md)
- [Troubleshooting](../docs/troubleshooting.md)

## Roadmap

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
  
4. **Laravel Telescope Integration**
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

## Contributing

We welcome contributions! Please see our [Contributing Guide](../CONTRIBUTING.md) for details.

### Development Setup

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is licensed under the MIT License - see the [LICENSE](../LICENSE) file for details.

## Support

- **Documentation**: [Full Documentation](https://github.com/shortinc/n8n-eloquent/wiki)
- **Issues**: [GitHub Issues](https://github.com/shortinc/n8n-eloquent/issues)
- **Community**: [n8n Community Forum](https://community.n8n.io)
- **Discord**: [n8n Discord Server](https://discord.gg/n8n)

## Acknowledgments

- [n8n](https://n8n.io) - The workflow automation platform
- [Laravel](https://laravel.com) - The PHP framework
- [Eloquent ORM](https://laravel.com/docs/eloquent) - Laravel's ORM

---

Built with ❤️ by Short Inc.  
Powered by n8n & Laravel
