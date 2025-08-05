# Node Documentation

## Available Nodes

### 1. Laravel Eloquent Trigger Node

The trigger node watches for model events and starts workflows when they occur.

#### Configuration

- **Credentials**: Your Laravel API credentials
- **Model**: Select the Eloquent model to watch
- **Events**: Choose which events to monitor
  - Created
  - Updated
  - Deleted
- **Properties**: Optional specific properties to watch
- **Security**:
  - HMAC Verification
  - IP Whitelisting
  - Cooldown Period

#### Output

The node outputs the following data:
```json
{
  "model": "App\\Models\\User",
  "event": "created",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2025-06-02T22:00:00.000000Z",
    "updated_at": "2025-06-02T22:00:00.000000Z"
  },
  "changes": {
    "name": ["", "John Doe"],
    "email": ["", "john@example.com"]
  },
  "metadata": {
    "source_trigger": "n8n_workflow_1",
    "webhook_id": "123",
    "timestamp": "2025-06-02T22:00:00.000000Z"
  }
}
```

### 2. Laravel Eloquent CRUD Node

The CRUD node allows creating, reading, updating, and deleting model records.

#### Operations

1. **Create Record**
   - Input: Model data
   - Output: Created record
   
2. **Read Records**
   - Filters: Where conditions
   - Relations: Include related models
   - Pagination: Limit/offset
   
3. **Update Record**
   - Input: Model ID and data
   - Output: Updated record
   
4. **Delete Record**
   - Input: Model ID
   - Output: Operation status

#### Example Usage

```typescript
// Create record
{
  "operation": "create",
  "model": "App\\Models\\User",
  "data": {
    "name": "Jane Doe",
    "email": "jane@example.com"
  }
}

// Read records
{
  "operation": "read",
  "model": "App\\Models\\User",
  "filters": {
    "status": "active",
    "created_at": {
      "$gt": "2025-01-01"
    }
  },
  "relations": ["posts", "profile"],
  "limit": 10
}

// Update record
{
  "operation": "update",
  "model": "App\\Models\\User",
  "id": 1,
  "data": {
    "status": "inactive"
  }
}

// Delete record
{
  "operation": "delete",
  "model": "App\\Models\\User",
  "id": 1
}
```

## Coming Soon

### 3. Laravel Event Dispatcher Node (Q3 2025)

Will allow dispatching any Laravel event from n8n workflows.

#### Planned Features
- Event class discovery
- Payload builder
- Broadcasting support
- Error handling

### 4. Laravel Event Listener Node (Q3 2025)

Will listen for Laravel events and trigger workflows.

#### Planned Features
- Event filtering
- Conditional processing
- Error recovery
- Payload processing

### 5. Laravel Job Dispatcher Node (Q3 2025)

Will dispatch Laravel jobs from n8n workflows.

#### Planned Features
- Job scheduling
- Queue selection
- Status monitoring
- Retry configuration

## Future Nodes (Q4 2025)

1. **Laravel Cache Node**
   - Cache operations
   - Store selection
   - Atomic operations
   
2. **Laravel Queue Node**
   - Queue management
   - Worker control
   - Failed jobs
   
3. **Laravel Notification Node**
   - Send notifications
   - Channel selection
   - Template system

## Best Practices

1. **Error Handling**
   - Use try/catch nodes
   - Implement retry logic
   - Log failures

2. **Performance**
   - Limit relation depth
   - Use pagination
   - Cache when possible

3. **Security**
   - Validate input data
   - Use HMAC verification
   - Monitor usage

## Examples

See our [example workflows](examples/) for common use cases:
- User registration flow
- Order processing
- Content moderation
- Data synchronization 