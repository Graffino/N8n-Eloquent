# API Reference

## Overview

The n8n-Eloquent API provides comprehensive access to Laravel models, webhook management, health monitoring, job dispatching, and event management. All endpoints require authentication via the `X-N8n-Api-Key` header.

## Authentication

All API endpoints require authentication using:

```http
X-N8n-Api-Key: your-api-secret-key
```

## Endpoints

### 🔍 Model Discovery & Metadata

#### Get All Models

```http
GET /api/n8n/models
```

**Response:**

```json
{
  "models": [
    {
      "class": "App\\Models\\User",
      "name": "User",
      "table": "users",
      "primaryKey": "id",
      "fillable": ["name", "email", "password"],
      "events": ["created", "updated", "deleted"]
    }
  ]
}
```

#### Search Models

```http
GET /api/n8n/models/search?q={query}
```

**Query Parameters:**

- `q`: Search query for model name or class

**Response:**

```json
{
  "models": [
    {
      "class": "App\\Models\\User",
      "name": "User",
      "table": "users"
    }
  ]
}
```

#### Get Model Details

```http
GET /api/n8n/models/{model}
```

**Response:**

```json
{
  "class": "App\\Models\\User",
  "name": "User",
  "table": "users",
  "primaryKey": "id",
  "fillable": ["name", "email", "password"],
  "hidden": ["password", "remember_token"],
  "casts": {
    "email_verified_at": "datetime"
  },
  "events": ["created", "updated", "deleted"]
}
```

#### Get Model Properties

```http
GET /api/n8n/models/{model}/properties
```

**Response:**

```json
{
  "properties": [
    {
      "name": "name",
      "type": "string",
      "nullable": false,
      "default": null
    },
    {
      "name": "email",
      "type": "string",
      "nullable": false,
      "unique": true
    }
  ]
}
```

#### Get Model Fields

```http
GET /api/n8n/models/{model}/fields
```

**Response:**

```json
{
  "fields": [
    {
      "name": "id",
      "type": "bigint",
      "primary": true,
      "auto_increment": true
    },
    {
      "name": "name",
      "type": "varchar",
      "length": 255,
      "nullable": false
    }
  ]
}
```

#### Get Model Relationships

```http
GET /api/n8n/models/{model}/relationships
```

**Response:**

```json
{
  "relationships": [
    {
      "name": "posts",
      "type": "hasMany",
      "related": "App\\Models\\Post",
      "foreignKey": "user_id"
    }
  ]
}
```

#### Get Validation Rules

```http
GET /api/n8n/models/{model}/validation-rules
```

**Response:**

```json
{
  "rules": {
    "name": ["required", "string", "max:255"],
    "email": ["required", "string", "email", "max:255", "unique:users"]
  }
}
```

#### Get Field Dependencies

```http
GET /api/n8n/models/{model}/fields/{field}/dependencies
```

**Response:**

```json
{
  "field": "email",
  "dependencies": [
    {
      "type": "unique",
      "table": "users",
      "column": "email"
    }
  ]
}
```

### 📖 CRUD Operations

#### List Records

```http
GET /api/n8n/models/{model}/records
```

**Query Parameters:**

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15)
- `with`: Comma-separated relationships to load
- `filter`: JSON encoded filter conditions
- `sort`: Sort field and direction (e.g., `name:asc`)

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50,
    "per_page": 15,
    "last_page": 4
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
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secret123"
}
```

**Response:**

```json
{
  "message": "Record created successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

#### Get Record

```http
GET /api/n8n/models/{model}/records/{id}
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2024-01-15T10:30:00.000000Z"
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
  "name": "John Smith",
  "email": "johnsmith@example.com"
}
```

**Response:**

```json
{
  "message": "Record updated successfully",
  "data": {
    "id": 1,
    "name": "John Smith",
    "email": "johnsmith@example.com",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

#### Delete Record

```http
DELETE /api/n8n/models/{model}/records/{id}
```

**Response:**

```json
{
  "message": "Record deleted successfully"
}
```

### 🔗 Webhook Management

#### Subscribe to Webhook

```http
POST /api/n8n/webhooks/subscribe
```

**Request:**

```json
{
  "model": "App\\Models\\User",
  "events": ["created", "updated", "deleted"],
  "webhook_url": "https://n8n.example.com/webhook/123",
  "properties": ["name", "email"],
  "node_id": "node-123",
  "workflow_id": "workflow-456",
  "verify_hmac": true,
  "require_timestamp": true,
  "expected_source_ip": "192.168.1.1"
}
```

**Response:**

```json
{
  "message": "Webhook subscription created successfully",
  "subscription": {
    "id": "sub_abc123",
    "model": "App\\Models\\User",
    "events": ["created", "updated", "deleted"],
    "webhook_url": "https://n8n.example.com/webhook/123",
    "active": true,
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

#### Unsubscribe from Webhook

```http
DELETE /api/n8n/webhooks/unsubscribe
```

**Request:**

```json
{
  "subscription_id": "sub_abc123"
}
```

**Response:**

```json
{
  "message": "Webhook subscription removed successfully"
}
```

#### List Webhook Subscriptions

```http
GET /api/n8n/webhooks
```

**Query Parameters:**

- `model`: Filter by model class
- `event`: Filter by event type

**Response:**

```json
{
  "subscriptions": [
    {
      "id": "sub_abc123",
      "model": "App\\Models\\User",
      "events": ["created", "updated"],
      "webhook_url": "https://n8n.example.com/webhook/123",
      "active": true,
      "last_delivery": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "total": 1
}
```

#### Get Webhook Statistics

```http
GET /api/n8n/webhooks/stats
```

**Response:**

```json
{
  "total_subscriptions": 5,
  "active_subscriptions": 4,
  "subscriptions_with_errors": 1,
  "stale_subscriptions": 0,
  "average_response_time": 0.25,
  "success_rate": 99.5,
  "recent_deliveries": {
    "successful": 150,
    "failed": 1,
    "total": 151
  }
}
```

#### Get Specific Webhook

```http
GET /api/n8n/webhooks/{subscription}
```

**Response:**

```json
{
  "subscription": {
    "id": "sub_abc123",
    "model": "App\\Models\\User",
    "events": ["created", "updated"],
    "webhook_url": "https://n8n.example.com/webhook/123",
    "active": true,
    "last_delivery": "2024-01-15T10:30:00.000000Z",
    "delivery_stats": {
      "total": 50,
      "successful": 49,
      "failed": 1,
      "success_rate": 98.0
    }
  }
}
```

#### Update Webhook

```http
PUT /api/n8n/webhooks/{subscription}
```

**Request:**

```json
{
  "events": ["created", "updated", "deleted"],
  "active": true,
  "properties": ["name", "email", "status"]
}
```

**Response:**

```json
{
  "message": "Webhook subscription updated successfully",
  "subscription": {
    "id": "sub_abc123",
    "events": ["created", "updated", "deleted"],
    "active": true
  }
}
```

#### Test Webhook

```http
POST /api/n8n/webhooks/{subscription}/test
```

**Request:**

```json
{
  "test_data": "Hello from test webhook!"
}
```

**Response:**

```json
{
  "message": "Test webhook sent successfully",
  "delivery_time": 0.15,
  "status_code": 200
}
```

#### Bulk Webhook Operations

```http
POST /api/n8n/webhooks/bulk
```

**Request:**

```json
{
  "operations": [
    {
      "action": "subscribe",
      "model": "App\\Models\\User",
      "events": ["created"],
      "webhook_url": "https://n8n.example.com/webhook/user-created"
    },
    {
      "action": "subscribe",
      "model": "App\\Models\\Post",
      "events": ["created", "updated"],
      "webhook_url": "https://n8n.example.com/webhook/post-events"
    }
  ]
}
```

**Response:**

```json
{
  "results": [
    {
      "action": "subscribe",
      "model": "App\\Models\\User",
      "status": "success",
      "subscription_id": "sub_abc123"
    },
    {
      "action": "subscribe",
      "model": "App\\Models\\Post",
      "status": "success",
      "subscription_id": "sub_def456"
    }
  ]
}
```

### 🏥 Health Monitoring

#### Basic Health Check

```http
GET /api/n8n/health
```

**Response:**

```json
{
  "status": "healthy",
  "timestamp": "2024-01-15T10:30:00.000000Z",
  "version": "1.0.0",
  "services": {
    "database": "connected",
    "webhooks": "active",
    "jobs": "available"
  }
}
```

#### Detailed Health Check

```http
GET /api/n8n/health/detailed
```

**Response:**

```json
{
  "status": "success",
  "data": {
    "overall_health": "healthy",
    "statistics": {
      "total_subscriptions": 5,
      "active_subscriptions": 4,
      "subscriptions_with_errors": 1
    },
    "recent_activity": [
      {
        "type": "webhook_delivery",
        "subscription_id": "sub_abc123",
        "status": "success",
        "timestamp": "2024-01-15T10:30:00.000000Z"
      }
    ],
    "recommendations": [
      "Consider reviewing subscription sub_abc123 due to recent errors"
    ]
  }
}
```

#### Health Analytics

```http
GET /api/n8n/health/analytics
```

**Response:**

```json
{
  "performance_metrics": {
    "average_response_time": 0.25,
    "success_rate": 99.5,
    "error_rate": 0.5,
    "throughput": 150
  },
  "trends": {
    "response_time_trend": "stable",
    "success_rate_trend": "improving",
    "error_rate_trend": "decreasing"
  },
  "top_issues": [
    {
      "type": "webhook_timeout",
      "count": 3,
      "subscription_id": "sub_abc123"
    }
  ]
}
```

#### Validate Subscription

```http
GET /api/n8n/health/validate/{subscription}
```

**Response:**

```json
{
  "subscription_id": "sub_abc123",
  "status": "healthy",
  "issues": [],
  "recommendations": [],
  "last_validation": "2024-01-15T10:30:00.000000Z"
}
```

#### Test Credentials

```http
POST /api/n8n/test-credentials
```

**Response:**

```json
{
  "status": "success",
  "message": "Credentials are valid",
  "permissions": {
    "models": true,
    "webhooks": true,
    "jobs": true,
    "events": true
  }
}
```

### ⚡ Job Management

#### List Available Jobs

```http
GET /api/n8n/jobs
```

**Response:**

```json
{
  "jobs": [
    {
      "class": "App\\Jobs\\SendEmail",
      "name": "SendEmail",
      "description": "Send email notification",
      "parameters": ["to", "subject", "body"]
    }
  ]
}
```

#### Get Job Details

```http
GET /api/n8n/jobs/{job}
```

**Response:**

```json
{
  "class": "App\\Jobs\\SendEmail",
  "name": "SendEmail",
  "description": "Send email notification",
  "parameters": ["to", "subject", "body"],
  "queue": "default",
  "timeout": 60
}
```

#### Get Job Parameters

```http
GET /api/n8n/jobs/{job}/parameters
```

**Response:**

```json
{
  "parameters": [
    {
      "name": "to",
      "type": "string",
      "required": true,
      "description": "Recipient email address"
    },
    {
      "name": "subject",
      "type": "string",
      "required": true,
      "description": "Email subject"
    },
    {
      "name": "body",
      "type": "string",
      "required": true,
      "description": "Email body content"
    }
  ]
}
```

#### Dispatch Job

```http
POST /api/n8n/jobs/{job}/dispatch
```

**Request:**

```json
{
  "to": "user@example.com",
  "subject": "Welcome!",
  "body": "Welcome to our platform!"
}
```

**Response:**

```json
{
  "message": "Job dispatched successfully",
  "job_id": "job_abc123",
  "queue": "default",
  "estimated_completion": "2024-01-15T10:31:00.000000Z"
}
```

### 🎯 Event Management

#### List Available Events

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
      "description": "Fired when user registers",
      "parameters": ["user"]
    }
  ]
}
```

#### Search Events

```http
GET /api/n8n/events/search?q={query}
```

**Query Parameters:**

- `q`: Search query for event name or class

**Response:**

```json
{
  "events": [
    {
      "class": "App\\Events\\UserRegistered",
      "name": "UserRegistered",
      "description": "Fired when user registers"
    }
  ]
}
```

#### Get Event Details

```http
GET /api/n8n/events/{event}
```

**Response:**

```json
{
  "class": "App\\Events\\UserRegistered",
  "name": "UserRegistered",
  "description": "Fired when user registers",
  "parameters": ["user"],
  "listeners": ["App\\Listeners\\SendWelcomeEmail"]
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
      "name": "user",
      "type": "App\\Models\\User",
      "description": "The registered user instance"
    }
  ]
}
```

#### Dispatch Event

```http
POST /api/n8n/events/{event}/dispatch
```

**Request:**

```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

**Response:**

```json
{
  "message": "Event dispatched successfully",
  "event_id": "evt_abc123",
  "listeners_triggered": 2
}
```

#### Subscribe to Event

```http
POST /api/n8n/events/subscribe
```

**Request:**

```json
{
  "event": "App\\Events\\UserRegistered",
  "webhook_url": "https://n8n.example.com/webhook/user-registered",
  "metadata": {
    "node_id": "node-123",
    "workflow_id": "workflow-456"
  }
}
```

**Response:**

```json
{
  "message": "Event subscription created successfully",
  "subscription": {
    "id": "evt_sub_abc123",
    "event": "App\\Events\\UserRegistered",
    "webhook_url": "https://n8n.example.com/webhook/user-registered",
    "active": true
  }
}
```

#### Unsubscribe from Event

```http
DELETE /api/n8n/events/unsubscribe
```

**Request:**

```json
{
  "subscription_id": "evt_sub_abc123"
}
```

**Response:**

```json
{
  "message": "Event subscription removed successfully"
}
```

## Error Responses

### Standard Error Format

```json
{
  "error": "Error message",
  "errors": {
    "field": ["Validation error message"]
  },
  "status_code": 422
}
```

### Common Error Codes

- `401` - Unauthorized (Invalid API key)
- `403` - Forbidden (Insufficient permissions)
- `404` - Not Found (Resource not found)
- `422` - Validation Error (Invalid input)
- `429` - Too Many Requests (Rate limited)
- `500` - Internal Server Error

## Rate Limiting

- Default: 60 requests per minute
- Headers:

  ```http
  X-RateLimit-Limit: 60
  X-RateLimit-Remaining: 59
  X-RateLimit-Reset: 1623456789
  ```

## Webhook Events

### Model Event Payload

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
    "webhook_id": "sub_abc123",
    "timestamp": "2024-01-15T10:30:00.000000Z",
    "hmac_signature": "sha256=..."
  }
}
```

### Custom Event Payload

```json
{
  "event": "App\\Events\\UserRegistered",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  },
  "metadata": {
    "subscription_id": "evt_sub_abc123",
    "timestamp": "2024-01-15T10:30:00.000000Z",
    "hmac_signature": "sha256=..."
  }
}
```

### Available Model Events

- `model.created`
- `model.updated`
- `model.deleted`
- `model.restored`
- `model.saving`
- `model.saved`

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
