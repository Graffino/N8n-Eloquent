# Laravel Eloquent n8n Webhook Testing Guide

## 🎯 Overview

This guide covers comprehensive testing of the n8n-Eloquent webhook integration, including the enhanced webhook management system, health monitoring, and event-driven webhooks.

## 🚀 Current Status ✅

- ✅ **Authentication working** (model dropdown loads)
- ✅ **Laravel API endpoints working** (40+ endpoints available)
- ✅ **n8n nodes loading properly**
- ✅ **Enhanced webhook management** (subscription CRUD, health monitoring)
- ✅ **Event-driven webhooks** (custom event subscriptions)
- ✅ **Health monitoring system** (analytics, validation, recovery)
- ✅ **Bulk webhook operations** (manage multiple subscriptions)

## 📋 Available Webhook Endpoints

### Basic Webhook Operations

- `POST /api/n8n/webhooks/subscribe` - Subscribe to model events
- `DELETE /api/n8n/webhooks/unsubscribe` - Unsubscribe from webhooks

### Enhanced Webhook Management

- `GET /api/n8n/webhooks` - List all webhook subscriptions
- `GET /api/n8n/webhooks/stats` - Get webhook statistics
- `POST /api/n8n/webhooks/bulk` - Bulk webhook operations
- `GET /api/n8n/webhooks/{subscription}` - Get specific subscription
- `PUT /api/n8n/webhooks/{subscription}` - Update subscription
- `POST /api/n8n/webhooks/{subscription}/test` - Test webhook delivery

### Health Monitoring

- `GET /api/n8n/health` - Basic health check
- `GET /api/n8n/health/detailed` - Detailed health status
- `GET /api/n8n/health/analytics` - Performance analytics
- `GET /api/n8n/health/validate/{subscription}` - Validate subscription
- `POST /api/n8n/test-credentials` - Test API credentials

### Event Management

- `GET /api/n8n/events` - Discover available events
- `POST /api/n8n/events/subscribe` - Subscribe to custom events
- `DELETE /api/n8n/events/unsubscribe` - Unsubscribe from events

## 🔧 Testing Setup

### Step 1: Ensure Services Are Running

```bash
# Terminal 1: Start Laravel
cd /path/to/your/laravel-app
php artisan serve --port=8002

# Terminal 2: Start n8n
cd /path/to/n8n-eloquent
n8n start
```

### Step 2: Create Credentials in n8n

1. Open <http://localhost:5678>
2. Go to **Settings** → **Credentials**
3. Click **Add Credential**
4. Select **Laravel Eloquent API**
5. Configure:
   - **Base URL**: `http://127.0.0.1:8002`
   - **API Key**: `your-api-secret-key`
   - **HMAC Secret**: (optional for testing)
6. Click **Test** - should show ✅ success
7. **Save** the credential

## 🧪 Testing Scenarios

### 1. Basic Webhook Registration Testing

#### Create Test Workflow

1. Click **+ Add Workflow**
2. **Add Node** → Search for "Laravel Eloquent Trigger"
3. Configure the trigger node:
   - **Credentials**: Select your Laravel API credential
   - **Model**: Select "User" from dropdown
   - **Events**: Select "Created", "Updated", "Deleted"
   - **Verify HMAC**: Enable (optional for testing)
   - **Expected Source IP**: Leave empty
4. **Add Debug Helper** node to see webhook data
5. **Connect** the Laravel trigger to Debug Helper
6. **Save** and **Activate** the workflow

#### Expected Console Output

```
🔄 Laravel Eloquent webhook registration starting...
📋 Registration details: { model: 'App\\Models\\User', events: [...], webhookUrl: '...' }
🌐 Making authenticated request to webhook subscription endpoint
✅ Registration response: {...}
💾 Stored subscription ID: ...
🎉 Laravel Eloquent webhook registered successfully!
```

### 2. Enhanced Webhook Management Testing

#### Test Webhook Listing

```bash
# List all webhook subscriptions
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/webhooks

# Get webhook statistics
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/webhooks/stats
```

#### Test Webhook Updates

```bash
# Update a specific subscription
curl -X PUT \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{"events":["created","updated"],"active":true}' \
     http://127.0.0.1:8002/api/n8n/webhooks/{subscription-id}

# Test webhook delivery
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{"test_data":"Hello from test!"}' \
     http://127.0.0.1:8002/api/n8n/webhooks/{subscription-id}/test
```

### 3. Health Monitoring Testing

#### Basic Health Check

```bash
# Quick health status
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health
```

#### Detailed Health Analysis

```bash
# Comprehensive health status
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/detailed

# Performance analytics
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/analytics

# Validate specific subscription
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/validate/{subscription-id}
```

#### Test Credentials

```bash
# Verify API credentials
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     http://127.0.0.1:8002/api/n8n/test-credentials
```

### 4. Event-Driven Webhook Testing

#### Discover Available Events

```bash
# List all available events
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/events

# Search for specific events
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     "http://127.0.0.1:8002/api/n8n/events/search?q=User"
```

#### Subscribe to Custom Events

```bash
# Subscribe to a custom event
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{
       "event": "App\\Events\\UserRegistered",
       "webhook_url": "http://localhost:5678/webhook/custom-event",
       "metadata": {
         "node_id": "custom-node-123",
         "workflow_id": "workflow-456"
       }
     }' \
     http://127.0.0.1:8002/api/n8n/events/subscribe
```

### 5. Bulk Operations Testing

#### Bulk Webhook Management

```bash
# Bulk subscribe to multiple webhooks
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{
       "operations": [
         {
           "action": "subscribe",
           "model": "App\\Models\\User",
           "events": ["created"],
           "webhook_url": "http://localhost:5678/webhook/user-created"
         },
         {
           "action": "subscribe", 
           "model": "App\\Models\\Post",
           "events": ["created", "updated"],
           "webhook_url": "http://localhost:5678/webhook/post-events"
         }
       ]
     }' \
     http://127.0.0.1:8002/api/n8n/webhooks/bulk
```

## 🔍 Webhook Flow Diagram

```
1. User activates n8n workflow
   ↓
2. n8n calls webhookCreate() method
   ↓  
3. webhookCreate() calls POST /api/n8n/webhooks/subscribe
   ↓
4. Laravel stores webhook subscription in database
   ↓
5. When model events occur (create/update/delete)
   ↓
6. Laravel ModelObserver triggers ModelLifecycleEvent
   ↓
7. ModelLifecycleListener calls WebhookService
   ↓
8. WebhookService sends POST to n8n webhook URL
   ↓
9. n8n receives webhook and executes workflow
   ↓
10. Health monitoring tracks delivery success/failure
```

## 🏥 Health Monitoring Features

### Health Status Levels

- **🟢 Healthy** - All subscriptions active, no errors
- **🟡 Warning** - Some issues detected, but functional
- **🔴 Critical** - Multiple failures, requires attention

### Health Metrics Tracked

- **Total Subscriptions** - Number of active webhook subscriptions
- **Active Subscriptions** - Subscriptions with recent successful deliveries
- **Failed Deliveries** - Subscriptions with recent delivery failures
- **Stale Subscriptions** - Subscriptions without recent activity
- **Response Times** - Average webhook delivery response times
- **Error Rates** - Percentage of failed webhook deliveries

### Health Recommendations

The system provides automatic recommendations based on health metrics:

- **Recovery Actions** - Automatic recovery of failed subscriptions
- **Performance Optimization** - Suggestions for improving delivery times
- **Security Alerts** - Notifications about suspicious activity
- **Maintenance Reminders** - When to review and clean up subscriptions

## 🐛 Debugging & Troubleshooting

### Webhook Registration Issues

#### Check n8n Console

Look for detailed error messages in the n8n console output:

```
❌ Webhook registration failed: Connection refused
❌ Authentication failed: Invalid API key
❌ Model not found: App\Models\NonExistentModel
```

#### Check Laravel Logs

```bash
tail -f storage/logs/laravel.log | grep n8n
```

#### Use Health Monitoring

```bash
# Check overall health
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health

# Get detailed health information
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/detailed
```

### Webhook Delivery Issues

#### Check Webhook Statistics

```bash
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/webhooks/stats
```

#### Validate Specific Subscription

```bash
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/validate/{subscription-id}
```

#### Test Webhook Manually

```bash
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{"test":"payload"}' \
     http://127.0.0.1:8002/api/n8n/webhooks/{subscription-id}/test
```

### Common Error Solutions

#### 401 Unauthorized

- Verify API key in Laravel `.env` file
- Check credentials in n8n match exactly
- Ensure `X-N8n-Api-Key` header is included

#### 404 Not Found

- Verify Laravel server is running on correct port
- Check model class exists and is properly configured
- Ensure URL encoding for class names (e.g., `App%5CModels%5CUser`)

#### 422 Validation Error

- Check required fields in webhook subscription request
- Verify event names are valid (`created`, `updated`, `deleted`, etc.)
- Ensure webhook URL is properly formatted

#### 429 Rate Limiting

- Default limit is 60 requests per minute
- Wait for rate limit window to reset
- Consider adjusting limits in configuration

#### Connection Refused

- Verify Laravel server is running
- Check firewall settings
- Ensure correct port configuration

## 📊 Monitoring & Analytics

### Real-time Monitoring

- **Webhook Delivery Status** - Track success/failure rates
- **Response Time Analytics** - Monitor performance trends
- **Error Pattern Analysis** - Identify common failure causes
- **Subscription Health** - Monitor individual subscription status

### Performance Metrics

- **Average Response Time** - Time from event to webhook delivery
- **Success Rate** - Percentage of successful webhook deliveries
- **Error Distribution** - Breakdown of error types and frequencies
- **Subscription Utilization** - How often each subscription is used

### Alert System

- **Automatic Recovery** - System attempts to recover failed subscriptions
- **Health Notifications** - Alerts when health status changes
- **Performance Warnings** - Notifications about slow response times
- **Security Alerts** - Suspicious activity detection

## 🎯 Success Indicators

### ✅ Webhook Registration Working

- n8n console shows registration success messages
- Laravel receives `/api/n8n/webhooks/subscribe` request
- Database has new webhook_subscription record
- Health check shows subscription as active

### ✅ Webhook Delivery Working

- Creating/updating models triggers n8n workflow executions
- Webhook statistics show successful deliveries
- Health monitoring reports healthy status
- n8n executions tab shows triggered workflows

### ✅ Health Monitoring Working

- Health endpoints return detailed status information
- Analytics provide performance insights
- Validation endpoints identify issues
- Recovery system automatically fixes problems

### ✅ Event Management Working

- Custom events can be discovered and subscribed to
- Event webhooks are delivered successfully
- Event parameters are properly serialized
- Event subscriptions can be managed independently

## 🔄 Advanced Testing Workflows

### Complete Integration Test

1. **Setup**: Create n8n workflow with Laravel trigger
2. **Register**: Activate workflow to register webhook
3. **Monitor**: Use health endpoints to verify registration
4. **Test**: Create/update/delete models to trigger events
5. **Verify**: Check n8n executions and webhook statistics
6. **Analyze**: Use analytics to review performance
7. **Cleanup**: Deactivate workflow to unsubscribe

### Event-Driven Workflow Test

1. **Discover**: List available custom events
2. **Subscribe**: Subscribe to specific events
3. **Trigger**: Manually dispatch events
4. **Monitor**: Track event webhook deliveries
5. **Validate**: Use health monitoring to verify delivery
6. **Optimize**: Review performance and adjust as needed

### Bulk Operations Test

1. **Setup**: Prepare multiple webhook configurations
2. **Bulk Subscribe**: Use bulk endpoint to create multiple subscriptions
3. **Monitor**: Track all subscriptions via health monitoring
4. **Test**: Trigger events for all subscribed models
5. **Analyze**: Review bulk delivery performance
6. **Manage**: Use bulk operations to update or remove subscriptions

Happy testing! 🚀
