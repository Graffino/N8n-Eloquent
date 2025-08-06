# API Reference

## Endpoints

### Webhook Management

#### Register Webhook

```http
POST /api/n8n/webhooks/subscribe
```

**Request:**

```json
{
  "model": "App\\Models\\User",
  "events": ["created", "updated", "deleted"],
  "webhook_url": "https://n8n.example.com/webhook/123",
  "secret": "your-webhook-secret"
}
```

**Response:**

```json
{
  "id": "webhook_123",
  "status": "active",
  "created_at": "2025-06-02T22:00:00.000000Z"
}
```

#### Unregister Webhook

```http
DELETE /api/n8n/webhooks/unsubscribe
```

**Request:**

```json
{
  "webhook_id": "webhook_123"
}
```

**Response:**

```json
{
  "status": "success",
  "message": "Webhook unsubscribed"
}
```

### Event Operations

#### Get Listenable Events

```http
GET /api/n8n/events
```

**Response:**

```json
{
  "events": [
    {
      "class": "App\\Events\\UserRegistered",
      "name": "UserRegistered",
      "properties": ["user", "timestamp"],
      "config": {}
    }
  ]
}
```

#### Get Dispatchable Events

```http
GET /api/n8n/events/dispatchable
```

**Response:**

```json
{
  "events": [
    {
      "class": "App\\Events\\SendEmailEvent",
      "name": "SendEmailEvent",
      "properties": ["recipient", "subject", "body"],
      "config": {}
    }
  ]
}
```

#### Get Event Parameters

```http
GET /api/n8n/events/{event}/parameters
```

**Response:**

```json
{
  "parameters": [
    {
      "name": "recipient",
      "type": "string",
      "required": true,
      "default": null,
      "label": "Recipient"
    }
  ]
}
```

#### Subscribe to Event Webhook

```http
POST /api/n8n/events/subscribe
```

**Request:**

```json
{
  "event": "App\\Events\\UserRegistered",
  "webhook_url": "https://n8n.example.com/webhook/123",
  "node_id": "node_123",
  "workflow_id": "workflow_456",
  "verify_hmac": true,
  "require_timestamp": true,
  "expected_source_ip": "192.168.1.1"
}
```

#### Dispatch Event

```http
POST /api/n8n/events/{event}/dispatch
```

**Request:**

```json
{
  "recipient": "user@example.com",
  "subject": "Welcome!",
  "body": "Welcome to our platform!",
  "metadata": {
    "source": "n8n",
    "workflow_id": "workflow_456"
  }
}
```

**Response:**

```json
{
  "message": "Event dispatched successfully",
  "event_class": "App\\Events\\SendEmailEvent",
  "dispatched_at": "2025-06-02T22:00:00.000000Z"
}
```

### Model Operations

#### Get Model Schema

```http
GET /api/n8n/models/{model}/schema
```

**Response:**

```json
{
  "name": "User",
  "table": "users",
  "fillable": ["name", "email"],
  "dates": ["created_at", "updated_at"],
  "relationships": {
    "posts": {
      "type": "hasMany",
      "model": "Post"
    }
  }
}
```

#### Create Record

```http
POST /api/n8n/models/{model}/records
```

**Request:**

```json
{
  "data": {
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

**Response:**

```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "created_at": "2025-06-02T22:00:00.000000Z"
}
```

#### Read Records

```http
GET /api/n8n/models/{model}/records
```

**Query Parameters:**

- `filter`: JSON encoded filter conditions
- `with`: Comma-separated relationship names
- `page`: Page number
- `per_page`: Items per page

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50,
    "per_page": 15
  }
}
```

#### Update Record

```http
PUT /api/n8n/models/{model}/records/{id}
```

**Request:**

```json
{
  "data": {
    "status": "active"
  }
}
```

**Response:**

```json
{
  "id": 1,
  "status": "active",
  "updated_at": "2025-06-02T22:00:00.000000Z"
}
```

#### Delete Record

```http
DELETE /api/n8n/models/{model}/records/{id}
```

**Response:**

```json
{
  "status": "success",
  "message": "Record deleted"
}
```

### Health & Monitoring

#### Health Check

```http
GET /api/n8n/health
```

**Response:**

```json
{
  "status": "healthy",
  "webhooks": {
    "active": 5,
    "total": 6
  },
  "last_event": "2025-06-02T22:00:00.000000Z"
}
```

#### Webhook Status

```http
GET /api/n8n/webhooks/{id}/status
```

**Response:**

```json
{
  "id": "webhook_123",
  "status": "active",
  "last_called": "2025-06-02T22:00:00.000000Z",
  "success_rate": 99.9,
  "error_count": 1
}
```

## Authentication

All API endpoints require authentication using:

1. **API Key Header:**

```http
X-API-Key: your-api-key
```

2. **HMAC Signature** (for webhooks):

```http
X-N8n-Signature: sha256=...
```

## Error Responses

### Standard Error Format

```json
{
  "error": {
    "code": "unauthorized",
    "message": "Invalid API key provided",
    "details": {
      "header": "X-API-Key missing or invalid"
    }
  }
}
```

### Common Error Codes

- `unauthorized`: Authentication failed
- `forbidden`: Permission denied
- `not_found`: Resource not found
- `validation_error`: Invalid input
- `rate_limited`: Too many requests

## Webhook Events

### Event Payload Format

```json
{
  "event": "model.created",
  "model": "App\\Models\\User",
  "data": {
    "id": 1,
    "attributes": {
      "name": "John Doe",
      "email": "john@example.com"
    },
    "changes": {
      "name": ["", "John Doe"],
      "email": ["", "john@example.com"]
    }
  },
  "metadata": {
    "webhook_id": "webhook_123",
    "timestamp": "2025-06-02T22:00:00.000000Z"
  }
}
```

### Available Events

- `model.created`
- `model.updated`
- `model.deleted`
- `model.restored`
- `model.force_deleted`

## Rate Limiting

- Default: 60 requests per minute
- Headers:

  ```http
  X-RateLimit-Limit: 60
  X-RateLimit-Remaining: 59
  X-RateLimit-Reset: 1623456789
  ```

## Pagination

### Request

```http
GET /api/n8n/models/user/records?page=2&per_page=15
```

### Response

```json
{
  "data": [...],
  "meta": {
    "current_page": 2,
    "from": 16,
    "to": 30,
    "total": 50,
    "per_page": 15,
    "last_page": 4
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": "...",
    "next": "..."
  }
}
```
