# 📮 Postman Collection Setup Guide for n8n-Eloquent API

## 🚀 Quick Start

### 1. Import the Collection

1. Open Postman
2. Click **Import** button
3. Select the `n8n-eloquent-postman-collection.json` file
4. The collection will be imported with all endpoints organized by category

### 2. Configure Environment Variables

Before testing, update these collection variables:

| Variable | Default Value | Description | Example |
|----------|---------------|-------------|---------|
| `base_url` | `http://127.0.0.1:8000` | Your Laravel app URL | `http://localhost:8000` |
| `api_secret` | `your-api-secret-here` | Your API secret key | `my-secret-key-123` |
| `model_class` | `App%5CModels%5CUser` | URL-encoded model class | `App%5CModels%5CPost` |
| `record_id` | `1` | Record ID for testing | `123` |
| `subscription_id` | `webhook-subscription-id` | Webhook subscription ID | `sub_abc123` |
| `job_class` | `App%5CJobs%5CExampleJob` | URL-encoded job class | `App%5CJobs%5CSendEmail` |
| `event_class` | `App%5CEvents%5CExampleEvent` | URL-encoded event class | `App%5CEvents%5CUserRegistered` |

**To update variables:**

1. Right-click on the collection
2. Select **Edit**
3. Go to **Variables** tab
4. Update the **Current Value** column

### 3. Set Your API Secret

⚠️ **Important**: Replace `your-api-secret-here` with your actual API secret from your Laravel `.env` file:

```env
N8N_ELOQUENT_API_SECRET=your-actual-secret-key
```

## 📁 Collection Structure

### 🔍 Model Discovery & Metadata

- **Get All Models** - Discover available models
- **Search Models** - Search models by name or class
- **Get Specific Model** - Get model metadata
- **Get Model Properties** - Get detailed field information
- **Get Model Fields** - Get model field definitions
- **Get Model Relationships** - Get model relationships
- **Get Validation Rules** - Get model validation rules
- **Get Field Dependencies** - Get field dependency information

### 📖 CRUD Operations - Read

- **Get All Records** - List records with pagination
- **Get Specific Record** - Retrieve single record by ID

### ✏️ CRUD Operations - Create

- **Create New Record** - Add new records

### 🔄 CRUD Operations - Update

- **Update Record** - Full record updates

### 🗑️ CRUD Operations - Delete

- **Delete Record** - Remove records by ID

### 🔗 Webhook Management

- **Subscribe to Webhook** - Set up event notifications
- **Unsubscribe from Webhook** - Remove subscriptions
- **List All Webhooks** - View all subscriptions
- **Get Webhook Statistics** - Usage metrics
- **Bulk Webhook Operations** - Manage multiple subscriptions
- **Get Specific Webhook** - Individual subscription details
- **Update Webhook** - Modify existing subscriptions
- **Test Webhook** - Send test notifications

### 🏥 Health Monitoring

- **Health Check** - Basic API connectivity check
- **Detailed Health Check** - Comprehensive health status
- **Health Analytics** - Performance and usage analytics
- **Validate Subscription** - Validate specific subscription
- **Test Credentials** - Verify API credentials

### ⚡ Job Management

- **Get All Jobs** - Discover available jobs
- **Get Specific Job** - Get job metadata
- **Get Job Parameters** - Get job parameter definitions
- **Dispatch Job** - Execute a job

### 🎯 Event Management

- **Get All Events** - Discover available events
- **Search Events** - Search events by name or class
- **Get Specific Event** - Get event metadata
- **Get Event Parameters** - Get event parameter definitions
- **Dispatch Event** - Manually trigger an event
- **Subscribe to Event** - Subscribe to specific events
- **Unsubscribe from Event** - Remove event subscriptions

### 🧪 Testing & Examples

- **Test Authentication Error** - Validate security
- **Test Rate Limiting** - Check rate limits

## 🔧 Usage Examples

### Testing Model Discovery

1. Start with **Get All Models** to see available models
2. Use **Search Models** to find specific models
3. Use **Get Specific Model** with a model class like `App%5CModels%5CUser`
4. Check **Get Model Properties** to understand field types
5. Explore **Get Model Relationships** for related data
6. Review **Get Validation Rules** for data validation

### Testing CRUD Operations

1. **Create**: Use **Create New Record** with valid data
2. **Read**: Use **Get All Records** to see your created record
3. **Update**: Use **Update Record** with the record ID
4. **Delete**: Use **Delete Record** to remove the record

### Testing Webhooks

1. **Subscribe**: Use **Subscribe to Webhook** with your n8n webhook URL
2. **Test**: Create/update/delete records to trigger events
3. **Monitor**: Check your n8n workflow for incoming webhooks
4. **Manage**: Use webhook management endpoints to control subscriptions
5. **Analytics**: Use **Get Webhook Statistics** to monitor usage

### Testing Health Monitoring

1. **Basic Check**: Use **Health Check** for quick status
2. **Detailed Analysis**: Use **Detailed Health Check** for comprehensive info
3. **Analytics**: Use **Health Analytics** for performance metrics
4. **Validation**: Use **Validate Subscription** to check specific subscriptions
5. **Credentials**: Use **Test Credentials** to verify API access

### Testing Job Management

1. **Discover Jobs**: Use **Get All Jobs** to see available jobs
2. **Job Details**: Use **Get Specific Job** to understand job structure
3. **Parameters**: Use **Get Job Parameters** to see required parameters
4. **Execute**: Use **Dispatch Job** to run jobs

### Testing Event Management

1. **Discover Events**: Use **Get All Events** to see available events
2. **Search Events**: Use **Search Events** to find specific events
3. **Event Details**: Use **Get Specific Event** to understand event structure
4. **Parameters**: Use **Get Event Parameters** to see event parameters
5. **Manual Trigger**: Use **Dispatch Event** to manually trigger events
6. **Subscribe**: Use **Subscribe to Event** for notifications

## 🔐 Authentication

All requests require the `X-N8n-Api-Key` header:

```
X-N8n-Api-Key: your-api-secret-here
```

The collection automatically includes this header using the `{{api_secret}}` variable.

## 📝 Class Name Encoding

When using class names in URLs, they must be URL-encoded:

- `App\Models\User` becomes `App%5CModels%5CUser`
- `App\Jobs\SendEmail` becomes `App%5CJobs%5CSendEmail`
- `App\Events\UserRegistered` becomes `App%5CEvents%5CUserRegistered`

The collection includes examples with proper encoding.

## 🧪 Automated Testing

The collection includes pre-request and test scripts that:

- Generate timestamps automatically
- Log request/response details
- Validate response status codes
- Check response times
- Store IDs for subsequent requests

## 🚨 Common Issues & Solutions

### 401 Unauthorized

- Check your `api_secret` variable
- Verify the secret matches your Laravel `.env` file
- Ensure the header `X-N8n-Api-Key` is included

### 404 Not Found

- Verify your `base_url` is correct
- Check if Laravel server is running
- Ensure the class name is properly URL-encoded

### 422 Validation Error

- Check required fields for the model/job/event
- Verify data types match expectations
- Use **Get Model Properties** to see field requirements

### Rate Limiting (429)

- Default limit is 60 requests per minute
- Wait for the rate limit window to reset
- Consider adjusting rate limits in config

## 🔄 Workflow Testing

### Complete CRUD Workflow

1. **Discover Models**: `Get All Models`
2. **Create Record**: `Create New Record`
3. **Read Record**: `Get Specific Record` (use returned ID)
4. **Update Record**: `Update Record` (use same ID)
5. **Delete Record**: `Delete Record` (use same ID)

### Webhook Integration Testing

1. **Setup n8n Webhook**: Create webhook trigger in n8n
2. **Subscribe**: Use `Subscribe to Webhook` with n8n URL
3. **Test Events**: Create/update/delete records
4. **Verify**: Check n8n workflow executions
5. **Monitor**: Use `Get Webhook Statistics` for analytics
6. **Cleanup**: Use `Unsubscribe from Webhook`

### Job Workflow Testing

1. **Discover Jobs**: `Get All Jobs`
2. **Job Details**: `Get Specific Job`
3. **Parameters**: `Get Job Parameters`
4. **Execute**: `Dispatch Job` with required parameters

### Event Workflow Testing

1. **Discover Events**: `Get All Events`
2. **Event Details**: `Get Specific Event`
3. **Parameters**: `Get Event Parameters`
4. **Manual Trigger**: `Dispatch Event` with parameters
5. **Subscribe**: `Subscribe to Event` for notifications

## 📊 Response Examples

### Successful Model List Response

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

### Successful Record Creation

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

### Webhook Subscription Response

```json
{
  "message": "Webhook subscription created successfully",
  "subscription": {
    "id": "sub_abc123",
    "model": "App\\Models\\User",
    "events": ["created", "updated", "deleted"],
    "webhook_url": "http://localhost:5678/webhook/user-events",
    "active": true
  }
}
```

### Health Check Response

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

### Job List Response

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

### Event List Response

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

## 🎯 Next Steps

1. **Import the collection** into Postman
2. **Configure your variables** (especially `api_secret`)
3. **Start with Health Check** to verify connectivity
4. **Explore Model Discovery** to understand your models
5. **Test CRUD operations** with your actual models
6. **Set up webhook integration** with n8n
7. **Explore Job Management** for background processing
8. **Test Event Management** for custom event handling

Happy testing! 🚀
