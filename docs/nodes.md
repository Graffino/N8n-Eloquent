# Node Documentation

## Available Nodes

### 1. Laravel Eloquent Trigger Node

The trigger node watches for Laravel Eloquent model events and starts workflows when they occur.

#### Configuration

- **Credentials**: Laravel Eloquent API credentials
- **Model**: Select the Eloquent model to watch (dynamically loaded from your Laravel app)
- **Events**: Choose which events to monitor
  - Created
  - Updated
  - Deleted
  - Restored
  - Saving
  - Saved
- **Security Settings**:
  - **Verify HMAC Signature**: Enable HMAC-SHA256 signature verification (default: true)
  - **Require Timestamp Validation**: Reject webhooks older than 5 minutes to prevent replay attacks (default: true)
  - **Expected Source IP**: Optional IP whitelist with CIDR support

#### Output

The node outputs the following data structure:

```json
{
  "model": "App\\Models\\User",
  "event": "created",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2025-01-15T10:30:00.000000Z",
    "updated_at": "2025-01-15T10:30:00.000000Z"
  },
  "changes": {
    "name": ["", "John Doe"],
    "email": ["", "john@example.com"]
  },
  "metadata": {
    "source_trigger": {
      "node_id": "webhook_node_1",
      "workflow_id": "workflow_123",
      "model": "App\\Models\\User",
      "event": "created",
      "timestamp": "2025-01-15T10:30:00.000000Z"
    },
    "webhook_id": "uuid-123",
    "timestamp": "2025-01-15T10:30:00.000000Z"
  }
}
```

#### Features

- **Automatic Webhook Management**: Webhooks are automatically created, updated, and deleted when workflows are activated/deactivated
- **Real-time Event Broadcasting**: Immediate notification when model events occur
- **Loop Prevention**: Built-in metadata tracking to prevent infinite loops
- **Health Monitoring**: Automatic subscription recovery and health status tracking
- **Performance Optimized**: Efficient request handling with minimal overhead

### 2. Laravel Event Listener Node ⭐ **NEW**

The event listener node listens for custom Laravel events and triggers workflows when they are dispatched.

#### Configuration

- **Credentials**: Laravel Eloquent API credentials
- **Event**: Select the Laravel event class to listen for (dynamically loaded from your Laravel app)
- **Security Settings**:
  - **Verify HMAC Signature**: Enable HMAC-SHA256 signature verification (default: true)
  - **Require Timestamp Validation**: Reject webhooks older than 5 minutes to prevent replay attacks (default: true)
  - **Expected Source IP**: Optional IP whitelist with CIDR support

#### Output

The node outputs the complete event payload with metadata:

```json
{
  "event": "App\\Events\\UserRegistered",
  "payload": {
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com"
    },
    "registration_date": "2025-01-15T10:30:00.000000Z"
  },
  "metadata": {
    "source_trigger": {
      "node_id": "event_listener_1",
      "workflow_id": "workflow_456",
      "event": "App\\Events\\UserRegistered",
      "timestamp": "2025-01-15T10:30:00.000000Z"
    },
    "webhook_id": "uuid-456",
    "timestamp": "2025-01-15T10:30:00.000000Z"
  }
}
```

#### Features

- **Automatic Event Discovery**: Dynamically discovers available Laravel events
- **Full Payload Serialization**: Complete event payload with all properties
- **Loop Prevention**: Built-in n8n metadata detection to prevent infinite loops
- **Security-First**: HMAC verification, timestamp validation, and IP filtering
- **Automatic Webhook Management**: Seamless webhook lifecycle management

### 3. Laravel Event Dispatcher Node ⭐ **NEW**

The event dispatcher node allows you to dispatch any Laravel event from n8n workflows.

#### Configuration

- **Credentials**: Laravel Eloquent API credentials
- **Event**: Select the Laravel event class to dispatch (dynamically loaded)
- **Event Parameters**: Configure parameters to pass to the event constructor
  - **Parameter Name**: Select from available event constructor parameters
  - **Parameter Value**: Set the value for each parameter
- **Additional Fields**:
  - **Custom Metadata**: Add custom metadata to track workflow context

#### Operations

The node dispatches the selected Laravel event with the configured parameters and returns the dispatch result:

```json
{
  "success": true,
  "event": "App\\Events\\OrderShipped",
  "parameters": {
    "order": {
      "id": 123,
      "customer_name": "John Doe"
    },
    "tracking_number": "TRK123456789"
  },
  "metadata": {
    "workflow_id": "workflow_789",
    "node_id": "event_dispatcher_1",
    "execution_id": "exec_123",
    "is_n8n_event_dispatch": true,
    "timestamp": "2025-01-15T10:30:00.000000Z"
  }
}
```

#### Features

- **Dynamic Parameter Discovery**: Automatically discovers event constructor parameters
- **Type Validation**: Validates parameter types and values
- **Metadata Tracking**: Includes workflow and execution context
- **Error Handling**: Comprehensive error reporting and validation
- **Security**: Only configured events are discoverable and dispatchable

### 4. Laravel Eloquent CRUD Node ⭐ **CONSOLIDATED**

The CRUD node provides unified operations for creating, reading, updating, and deleting Laravel Eloquent model records.

#### Operations

1. **Create Record**
   - **Model**: Select the Eloquent model
   - **Data**: Input model data for creation
   - **Output**: Created record with all attributes

2. **Get All Records**
   - **Model**: Select the Eloquent model
   - **Filters**: Advanced filtering with multiple operators
     - Equals, Not Equals, Greater Than, Less Than, Like, In
   - **Relations**: Include related models
   - **Pagination**: Limit and offset configuration
   - **Sorting**: Order by clauses
   - **Output**: Paginated results with metadata

3. **Get Record by ID**
   - **Model**: Select the Eloquent model
   - **ID**: Record identifier
   - **Relations**: Include related models
   - **Output**: Single record with all attributes

4. **Update Record**
   - **Model**: Select the Eloquent model
   - **ID**: Record identifier
   - **Data**: Update data
   - **Output**: Updated record

5. **Delete Record**
   - **Model**: Select the Eloquent model
   - **ID**: Record identifier
   - **Output**: Operation status

#### Advanced Features

- **Dynamic Field Loading**: Automatically discovers model fields and relationships
- **Advanced Filtering**: Multiple operators for complex queries
- **Relationship Support**: Include related models with dynamic loading
- **Batch Operations**: Support for multiple record operations
- **Enhanced Error Handling**: Comprehensive validation and error categorization
- **Metadata Tracking**: Includes workflow context in operations

#### Example Usage

```typescript
// Create record
{
  "operation": "create",
  "model": "App\\Models\\User",
  "data": {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "status": "active"
  }
}

// Get records with filters
{
  "operation": "getAll",
  "model": "App\\Models\\User",
  "filters": [
    {
      "field": "status",
      "operator": "equals",
      "value": "active"
    },
    {
      "field": "created_at",
      "operator": "greater_than",
      "value": "2025-01-01"
    }
  ],
  "relations": ["posts", "profile"],
  "limit": 10,
  "offset": 0,
  "orderBy": [
    {
      "field": "created_at",
      "direction": "desc"
    }
  ]
}

// Update record
{
  "operation": "update",
  "model": "App\\Models\\User",
  "id": 1,
  "data": {
    "status": "inactive",
    "last_login_at": "2025-01-15T10:30:00.000000Z"
  }
}
```

### 5. Laravel Job Dispatcher Node ⭐ **ENHANCED**

The job dispatcher node allows you to dispatch Laravel jobs from n8n workflows with full parameter validation and queue management.

#### Operations

1. **Dispatch Job**
   - **Job**: Select the Laravel job class to dispatch
   - **Parameters**: Configure job constructor parameters
   - **Queue Options**: Configure queue name, connection, and options
   - **Output**: Job dispatch result with job ID

2. **Dispatch Job Later**
   - **Job**: Select the Laravel job class
   - **Parameters**: Configure job constructor parameters
   - **Delay**: Set delay time (minutes, hours, days)
   - **Queue Options**: Configure queue settings
   - **Output**: Scheduled job result

3. **Dispatch Job Sync**
   - **Job**: Select the Laravel job class
   - **Parameters**: Configure job constructor parameters
   - **Output**: Synchronous execution result

#### Configuration

- **Credentials**: Laravel Eloquent API credentials
- **Operation**: Select dispatch mode (dispatch, dispatchLater, dispatchSync)
- **Job**: Select from available Laravel jobs (configured in `config/n8n-eloquent.php`)
- **Job Parameters**: Configure parameters for job constructor
  - **Parameter Name**: Select from available job parameters
  - **Parameter Value**: Set the value for each parameter
- **Queue Options** (for dispatch and dispatchLater):
  - **Queue Name**: Custom queue name
  - **Connection**: Queue connection
  - **Delay**: Delay time for later dispatch
  - **After Commit**: Dispatch after database transaction commits
  - **Before Commit**: Dispatch before database transaction commits

#### Features

- **Security-First Approach**: Only configured jobs are discoverable and dispatchable
- **Dynamic Parameter Discovery**: Automatically discovers job constructor parameters
- **Multiple Dispatch Modes**: Immediate, delayed, and synchronous execution
- **Queue Management**: Configurable queues, connections, and advanced options
- **Metadata Tracking**: Jobs include workflow and execution context information
- **Error Handling**: Comprehensive validation and error reporting

#### Example Usage

```typescript
// Dispatch job immediately
{
  "operation": "dispatch",
  "job": "App\\Jobs\\SendEmailJob",
  "parameters": [
    {
      "parameterName": "user",
      "parameterValue": "{\"id\": 1, \"email\": \"user@example.com\"}"
    },
    {
      "parameterName": "template",
      "parameterValue": "welcome"
    }
  ],
  "queueOptions": {
    "queueName": "emails",
    "connection": "redis"
  }
}

// Dispatch job later
{
  "operation": "dispatchLater",
  "job": "App\\Jobs\\ProcessDataJob",
  "parameters": [
    {
      "parameterName": "data",
      "parameterValue": "{\"batch_id\": 123, \"records\": [1,2,3]}"
    }
  ],
  "queueOptions": {
    "delay": 30, // 30 minutes
    "queueName": "processing"
  }
}
```

## Configuration

### Laravel Configuration

Configure your available models, events, and jobs in `config/n8n-eloquent.php`:

```php
'models' => [
    'mode' => 'all', // 'all', 'whitelist', 'blacklist'
    'whitelist' => [
        'App\\Models\\User',
        'App\\Models\\Order',
    ],
],

'events' => [
    'discovery' => [
        'mode' => 'all',
        'whitelist' => [
            'App\\Events\\UserRegistered',
            'App\\Events\\OrderShipped',
        ],
    ],
],

'jobs' => [
    'available' => [
        'App\\Jobs\\SendEmailJob',
        'App\\Jobs\\ProcessDataJob',
    ],
],
```

### Environment Variables

```env
# API Configuration
N8N_ELOQUENT_API_SECRET=your-secret-key
N8N_ELOQUENT_API_PREFIX=api/n8n

# Webhook Security
N8N_HMAC_SECRET=your-hmac-secret
N8N_ELOQUENT_RATE_LIMIT_ATTEMPTS=60
N8N_ELOQUENT_RATE_LIMIT_DECAY=1

# Model Discovery
N8N_ELOQUENT_MODELS_NAMESPACE=App\\Models
N8N_ELOQUENT_MODEL_MODE=all

# Event Configuration
N8N_ELOQUENT_EVENTS_ENABLED=true
N8N_ELOQUENT_EVENTS_NAMESPACE=App\\Events
N8N_ELOQUENT_EVENT_WEBHOOKS_ENABLED=true

# Health Monitoring
N8N_ELOQUENT_STALE_HOURS=24
N8N_ELOQUENT_ERROR_THRESHOLD=5
N8N_ELOQUENT_HEALTH_INTERVAL=3600
```

## Best Practices

### Security

1. **Always enable HMAC verification** for production environments
2. **Use timestamp validation** to prevent replay attacks
3. **Configure IP whitelisting** for additional security
4. **Use strong, unique secrets** for API and HMAC keys
5. **Monitor webhook health** regularly

### Performance

1. **Limit relation depth** to avoid N+1 queries
2. **Use pagination** for large datasets
3. **Configure appropriate cache TTL** for model discovery
4. **Monitor webhook delivery** and retry failed requests
5. **Use appropriate queue connections** for job dispatching

### Error Handling

1. **Implement retry logic** in your workflows
2. **Use try/catch nodes** for critical operations
3. **Monitor error thresholds** and health status
4. **Log failures** for debugging and monitoring
5. **Use appropriate error categorization**

### Workflow Design

1. **Prevent infinite loops** by checking metadata
2. **Use appropriate node types** for your use case
3. **Validate input data** before processing
4. **Implement proper error recovery** mechanisms
5. **Monitor workflow performance** and optimize as needed

## Examples

### User Registration Flow

1. **Laravel Eloquent Trigger** → Watch for User creation
2. **Laravel Event Dispatcher** → Dispatch UserRegistered event
3. **Laravel Job Dispatcher** → Send welcome email
4. **Laravel Eloquent CRUD** → Update user profile

### Order Processing Workflow

1. **Laravel Event Listener** → Listen for OrderCreated event
2. **Laravel Job Dispatcher** → Process payment
3. **Laravel Eloquent CRUD** → Update order status
4. **Laravel Event Dispatcher** → Dispatch OrderShipped event

### Data Synchronization

1. **Laravel Eloquent Trigger** → Watch for model updates
2. **Laravel Job Dispatcher** → Sync to external systems
3. **Laravel Eloquent CRUD** → Update sync status
4. **Laravel Event Dispatcher** → Notify stakeholders

## Troubleshooting

### Common Issues

1. **Webhook not triggering**
   - Check model configuration and webhook events
   - Verify HMAC signature and timestamp validation
   - Check IP whitelist configuration

2. **Job dispatch failures**
   - Verify job is in the available jobs list
   - Check job constructor parameters
   - Validate queue configuration

3. **Event discovery issues**
   - Check event namespace configuration
   - Verify event class extends BaseEvent
   - Check event discovery mode settings

4. **Performance issues**
   - Monitor webhook delivery times
   - Check database query performance
   - Review cache configuration

### Health Monitoring

Use the built-in health monitoring commands:

```bash
# Check overall system health
php artisan n8n:status

# Monitor webhook subscriptions
php artisan n8n:health

# Test webhook delivery
php artisan n8n:test-webhook

# Clean up stale subscriptions
php artisan n8n:cleanup
```

For more detailed troubleshooting, see the [Troubleshooting Guide](troubleshooting.md) and [Webhook Testing Guide](../WEBHOOK_TESTING_GUIDE.md).
