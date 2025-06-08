# n8n Laravel Eloquent Integration

![n8n](https://img.shields.io/badge/n8n-community--node-FF6D5A)
![Laravel](https://img.shields.io/badge/Laravel-8.x%20%7C%209.x%20%7C%2010.x%20%7C%2011.x%20%7C%2012.x-FF2D20)
![License](https://img.shields.io/badge/license-MIT-blue)

A comprehensive n8n community node package that provides seamless integration with Laravel Eloquent models. This extension allows you to trigger workflows on Laravel model events and perform CRUD operations directly from n8n.

## 🚀 Features

- **🔔 Real-time Triggers**: Listen to Laravel Eloquent model events (created, updated, deleted, etc.)
- **💾 CRUD Operations**: Create, Read, Update, and Delete Laravel model records
- **🔐 Secure Authentication**: API key and HMAC signature verification
- **🔗 Relationship Support**: Include related models in your queries
- **🎯 Advanced Filtering**: Search records with multiple filter conditions
- **⚡ High Performance**: Optimized for production use

## 📦 Installation

### Prerequisites

1. **Laravel Package**: First, install the Laravel n8n Eloquent package in your Laravel application:
   ```bash
   composer require n8n-eloquent/laravel-package
   ```

2. **n8n Instance**: You need a running n8n instance (self-hosted or n8n Cloud)

### Community Nodes (Recommended)

1. Go to **Settings > Community Nodes**
2. Select **Install**
3. Enter: `@shortinc/n8n-eloquent-nodes`
4. Click **Install**

### Manual Installation

You can also install the package manually:

```bash
npm install @shortinc/n8n-eloquent-nodes
```

## 🔧 Configuration

### 1. Laravel Setup

Configure your Laravel application with the n8n Eloquent package:

```bash
# Publish configuration
php artisan vendor:publish --provider="N8nEloquent\LaravelPackage\N8nEloquentServiceProvider"

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

## 📋 Available Nodes

### 🔔 Laravel Eloquent Trigger

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

### 💾 Laravel Eloquent CRUD

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

## 🔄 Workflow Examples

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

## 🔐 Security

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

### Security Documentation
- [Complete Security Guide](SECURITY.md) - Comprehensive security features and best practices
- [Testing Guide](TESTING.md) - Security testing procedures and automation

## 🛠️ Development

### Building from Source

```bash
# Clone the repository
git clone https://github.com/n8n-io/n8n-eloquent.git
cd n8n-eloquent/n8n-extension

# Install dependencies
npm install

# Build the project
npm run build

# Run linting
npm run lint
```

### Testing

```bash
# Run tests (when available)
npm test

# Type checking
npm run build
```

## 📚 API Reference

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

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [Full Documentation](https://github.com/n8n-io/n8n-eloquent/wiki)
- **Issues**: [GitHub Issues](https://github.com/n8n-io/n8n-eloquent/issues)
- **Community**: [n8n Community Forum](https://community.n8n.io)
- **Discord**: [n8n Discord Server](https://discord.gg/n8n)

## 🗺️ Roadmap

- [ ] Advanced relationship handling
- [ ] Bulk operations support
- [ ] Custom validation rules
- [ ] Performance monitoring
- [ ] GraphQL support
- [ ] Real-time data streaming

## 🙏 Acknowledgments

- [n8n](https://n8n.io) - The workflow automation platform
- [Laravel](https://laravel.com) - The PHP framework
- [Eloquent ORM](https://laravel.com/docs/eloquent) - Laravel's ORM

---

**Made with ❤️ for the n8n and Laravel communities** 