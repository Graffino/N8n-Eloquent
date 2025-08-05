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

### 🔍 Model Discovery
- **Get All Models** - Discover available models
- **Get Specific Model** - Get model metadata
- **Get Model Properties** - Get detailed field information

### 📖 CRUD Operations - Read
- **Get All Records** - List records with pagination
- **Get Records with Filters** - Search and filter records
- **Get Specific Record** - Retrieve single record by ID

### ✏️ CRUD Operations - Create
- **Create New Record** - Add new records
- **Create User Example** - Specific User model example

### 🔄 CRUD Operations - Update
- **Update Record** - Full record updates
- **Partial Update Example** - Update specific fields only

### 🗑️ CRUD Operations - Delete
- **Delete Record** - Remove records by ID
- **Delete User Example** - Specific deletion example

### 🔗 Webhook Management
- **Subscribe to Webhook** - Set up event notifications
- **Unsubscribe from Webhook** - Remove subscriptions
- **List All Webhooks** - View all subscriptions
- **List Webhooks with Filters** - Filter by model/event
- **Get Webhook Statistics** - Usage metrics
- **Get Specific Webhook** - Individual subscription details
- **Update Webhook** - Modify existing subscriptions
- **Test Webhook** - Send test notifications
- **Bulk Webhook Operations** - Manage multiple subscriptions

### 🧪 Testing & Examples
- **Health Check** - Verify API connectivity
- **Test Authentication Error** - Validate security
- **Test Rate Limiting** - Check rate limits

## 🔧 Usage Examples

### Testing Model Discovery
1. Start with **Get All Models** to see available models
2. Use **Get Specific Model** with a model class like `App%5CModels%5CUser`
3. Check **Get Model Properties** to understand field types

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

## 🔐 Authentication

All requests require the `X-N8n-Api-Key` header:
```
X-N8n-Api-Key: your-api-secret-here
```

The collection automatically includes this header using the `{{api_secret}}` variable.

## 📝 Model Class Encoding

When using model class names in URLs, they must be URL-encoded:
- `App\Models\User` becomes `App%5CModels%5CUser`
- `App\Models\Post` becomes `App%5CModels%5CPost`

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
- Ensure the model class is properly URL-encoded

### 422 Validation Error
- Check required fields for the model
- Verify data types match model expectations
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
5. **Cleanup**: Use `Unsubscribe from Webhook`

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

## 🎯 Next Steps

1. **Import the collection** into Postman
2. **Configure your variables** (especially `api_secret`)
3. **Start with Health Check** to verify connectivity
4. **Explore Model Discovery** to understand your models
5. **Test CRUD operations** with your actual models
6. **Set up webhook integration** with n8n

Happy testing! 🚀 