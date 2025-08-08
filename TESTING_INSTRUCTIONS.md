# Laravel n8n Eloquent Integration - Testing Instructions

## 🎉 Setup Complete

You now have a clean Laravel installation with the n8n Eloquent integration and **Laravel Telescope** ready for testing and debugging.

## 🚀 What's Running

1. **Laravel Application**: `http://127.0.0.1:8002`
   - Location: `/path/to/your/laravel-app`
   - API Key: `your-api-secret-key`
   - **40+ API endpoints** available for comprehensive testing

2. **n8n Instance**: `http://localhost:5678`
   - Laravel Eloquent nodes are installed and linked
   - Official Laravel logos added to all nodes
   - Enhanced webhook management capabilities

3. **🔭 Laravel Telescope**: `http://127.0.0.1:8002/telescope`
   - Advanced debugging and monitoring dashboard
   - Custom n8n tagging for easy filtering
   - Real-time request, query, and model event monitoring

## 📋 Available API Endpoints

### 🔍 Model Discovery & Metadata

- `GET /api/n8n/models` - Discover available models
- `GET /api/n8n/models/search` - Search models by name or class
- `GET /api/n8n/models/{model}` - Get model metadata
- `GET /api/n8n/models/{model}/properties` - Get detailed field information
- `GET /api/n8n/models/{model}/fields` - Get model field definitions
- `GET /api/n8n/models/{model}/relationships` - Get model relationships
- `GET /api/n8n/models/{model}/validation-rules` - Get validation rules
- `GET /api/n8n/models/{model}/fields/{field}/dependencies` - Get field dependencies

### 📖 CRUD Operations

- `GET /api/n8n/models/{model}/records` - List records with pagination
- `POST /api/n8n/models/{model}/records` - Create new records
- `GET /api/n8n/models/{model}/records/{id}` - Get specific record
- `PUT /api/n8n/models/{model}/records/{id}` - Update record
- `DELETE /api/n8n/models/{model}/records/{id}` - Delete record

### 🔗 Webhook Management

- `POST /api/n8n/webhooks/subscribe` - Subscribe to model events
- `DELETE /api/n8n/webhooks/unsubscribe` - Unsubscribe from webhooks
- `GET /api/n8n/webhooks` - List all webhook subscriptions
- `GET /api/n8n/webhooks/stats` - Get webhook statistics
- `POST /api/n8n/webhooks/bulk` - Bulk webhook operations
- `GET /api/n8n/webhooks/{subscription}` - Get specific subscription
- `PUT /api/n8n/webhooks/{subscription}` - Update subscription
- `POST /api/n8n/webhooks/{subscription}/test` - Test webhook delivery

### 🏥 Health Monitoring

- `GET /api/n8n/health` - Basic health check
- `GET /api/n8n/health/detailed` - Detailed health status
- `GET /api/n8n/health/analytics` - Performance analytics
- `GET /api/n8n/health/validate/{subscription}` - Validate subscription
- `POST /api/n8n/test-credentials` - Test API credentials

### ⚡ Job Management

- `GET /api/n8n/jobs` - Discover available jobs
- `GET /api/n8n/jobs/{job}` - Get job metadata
- `GET /api/n8n/jobs/{job}/parameters` - Get job parameter definitions
- `POST /api/n8n/jobs/{job}/dispatch` - Execute a job

### 🎯 Event Management

- `GET /api/n8n/events` - Discover available events
- `GET /api/n8n/events/search` - Search events by name or class
- `GET /api/n8n/events/{event}` - Get event metadata
- `GET /api/n8n/events/{event}/parameters` - Get event parameter definitions
- `POST /api/n8n/events/{event}/dispatch` - Manually trigger an event
- `POST /api/n8n/events/subscribe` - Subscribe to specific events
- `DELETE /api/n8n/events/unsubscribe` - Unsubscribe from events

## 📋 Testing Steps

### 1. Set up n8n Credentials

1. Open n8n at `http://localhost:5678`
2. Go to **Settings** → **Credentials**
3. Create new **Laravel Eloquent API** credential:
   - **Base URL**: `http://127.0.0.1:8002`
   - **API Key**: `your-api-secret-key`
   - **HMAC Secret**: (leave empty for testing)

### 2. Test Model Discovery

1. Create a new workflow
2. Add a **Laravel Eloquent Trigger** node
3. Select your credentials
4. Check that the **Model** dropdown populates with available models
5. **🔭 Monitor in Telescope**: Filter by `n8n-models` tag

### 3. Test Enhanced Model Metadata

```bash
# Test model search
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     "http://127.0.0.1:8002/api/n8n/models/search?q=User"

# Test model properties
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     "http://127.0.0.1:8002/api/n8n/models/App%5CModels%5CUser/properties"

# Test model relationships
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     "http://127.0.0.1:8002/api/n8n/models/App%5CModels%5CUser/relationships"
```

### 4. Test Webhook Registration & Management

1. Configure the trigger:
   - **Model**: `App\Models\User`
   - **Events**: `created`, `updated`
   - **Verify HMAC**: `false` (for testing)
   - **Require Timestamp**: `false` (for testing)

2. Save and activate the workflow
3. Check the n8n console for webhook registration logs
4. **🔭 Monitor in Telescope**: Go to `http://127.0.0.1:8002/telescope` and filter by `n8n-webhooks` tag

#### Test Enhanced Webhook Management

```bash
# List all webhook subscriptions
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/webhooks

# Get webhook statistics
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/webhooks/stats

# Test webhook delivery
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{"test_data":"Hello from test!"}' \
     http://127.0.0.1:8002/api/n8n/webhooks/{subscription-id}/test
```

### 5. Test Health Monitoring

```bash
# Basic health check
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health

# Detailed health status
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/detailed

# Performance analytics
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/analytics

# Test credentials
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     http://127.0.0.1:8002/api/n8n/test-credentials
```

### 6. Test Job Management

```bash
# Discover available jobs
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/jobs

# Get job details
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     "http://127.0.0.1:8002/api/n8n/jobs/App%5CJobs%5CSendEmail"

# Get job parameters
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     "http://127.0.0.1:8002/api/n8n/jobs/App%5CJobs%5CSendEmail/parameters"

# Dispatch a job
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{"to":"test@example.com","subject":"Test","body":"Hello"}' \
     "http://127.0.0.1:8002/api/n8n/jobs/App%5CJobs%5CSendEmail/dispatch"
```

### 7. Test Event Management

```bash
# Discover available events
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/events

# Search for events
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     "http://127.0.0.1:8002/api/n8n/events/search?q=User"

# Subscribe to custom events
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{
       "event": "App\\Events\\UserRegistered",
       "webhook_url": "http://localhost:5678/webhook/custom-event"
     }' \
     http://127.0.0.1:8002/api/n8n/events/subscribe
```

### 8. Test Data Retrieval

1. Add a **Laravel Eloquent Get** node
2. Configure:
   - **Operation**: `Get All Records`
   - **Model**: `App\Models\User`
   - **Limit**: `10`

3. Execute the node to test data retrieval
4. **🔭 Monitor in Telescope**: Filter by `n8n-api` tag to see API requests

## 🔭 Laravel Telescope Monitoring

### Access Telescope Dashboard

Visit `http://127.0.0.1:8002/telescope` to access the monitoring dashboard.

### Custom n8n Tags

Telescope is configured with custom tags for easy filtering:

- **`n8n-api`** - All n8n API requests
- **`n8n-models`** - Model discovery requests
- **`n8n-webhooks`** - Webhook subscription/unsubscription
- **`n8n-authenticated`** - Requests with valid n8n API key
- **`n8n-model-event`** - Eloquent model events
- **`n8n-health`** - Health monitoring requests
- **`n8n-jobs`** - Job management requests
- **`n8n-events`** - Event management requests
- **`n8n-error`** - n8n-related exceptions

### Key Telescope Features for n8n Debugging

1. **Requests Tab** - Monitor all API calls from n8n
2. **Models Tab** - Track Eloquent model events and webhook triggers
3. **Exceptions Tab** - Debug authentication and webhook errors
4. **Queries Tab** - Monitor database performance
5. **Logs Tab** - View debug logs (set to debug level)

### Filtering Examples

- View all n8n requests: Filter by `n8n-api` tag
- Debug webhook issues: Filter by `n8n-webhooks` tag
- Monitor model events: Filter by `n8n-model-event` tag
- Check authentication: Filter by `n8n-authenticated` tag
- Monitor health: Filter by `n8n-health` tag
- Track jobs: Filter by `n8n-jobs` tag
- Monitor events: Filter by `n8n-events` tag

## 🔧 Manual Testing

### Test API Endpoints

```bash
# Test models endpoint
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Accept: application/json" \
     http://127.0.0.1:8002/api/n8n/models

# Test webhook subscription
curl -X POST \
     -H "X-N8n-Api-Key: your-api-secret-key" \
     -H "Content-Type: application/json" \
     -d '{"model":"App\\Models\\User","events":["created"],"webhook_url":"http://test.com/webhook"}' \
     http://127.0.0.1:8002/api/n8n/webhooks/subscribe

# Test health check
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health
```

### Create Test User

```bash
# SSH into Laravel app
cd /path/to/your/laravel-app

# Create a test user via tinker
php artisan tinker
>>> User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password')])
```

**🔭 After creating a user, check Telescope's Models tab to see the `created` event!**

## 🎨 Features Implemented

✅ **Clean Laravel Installation** - Fresh Laravel 12 project  
✅ **Laravel Logo Integration** - Official Laravel logos on all nodes  
✅ **Enhanced Model Discovery** - Dynamic model discovery with metadata  
✅ **Comprehensive CRUD Operations** - Full model record management  
✅ **Advanced Webhook Management** - Subscription CRUD, health monitoring  
✅ **Health Monitoring System** - Analytics, validation, recovery  
✅ **Job Management** - Discover, configure, and dispatch jobs  
✅ **Event Management** - Custom event subscriptions and dispatching  
✅ **Bulk Operations** - Manage multiple webhooks efficiently  
✅ **Enhanced Debugging** - Comprehensive logging and error handling  
✅ **Security Features** - HMAC verification, IP filtering, timestamp validation  
✅ **🔭 Laravel Telescope** - Advanced monitoring and debugging dashboard  
✅ **Custom n8n Tagging** - Easy filtering of n8n-related activities  

## 🐛 Troubleshooting

### Webhook Registration Issues

1. Check Laravel logs: `tail -f /path/to/your/laravel-app/storage/logs/laravel.log`
2. Check n8n console output for detailed error messages
3. **🔭 Use Telescope**: Filter by `n8n-webhooks` tag to see webhook requests
4. **🔭 Check Exceptions**: Look for `n8n-error` tagged exceptions
5. Verify API key is correct in both Laravel `.env` and n8n credentials
6. **Use Health Monitoring**: Check `/api/n8n/health` for system status

### Model Discovery Issues

1. Ensure Laravel server is running on port 8002
2. Test the models endpoint manually (see curl command above)
3. **🔭 Monitor in Telescope**: Filter by `n8n-models` tag
4. Check that the API key matches in both applications
5. Verify models are properly configured in `n8n-eloquent.php` config

### Health Monitoring Issues

1. **🔭 Filter by `n8n-health`**: See health monitoring requests
2. Check health endpoints manually for detailed error information
3. Use `/api/n8n/health/detailed` for comprehensive status
4. Review `/api/n8n/health/analytics` for performance insights

### Job Management Issues

1. **🔭 Filter by `n8n-jobs`**: Monitor job-related requests
2. Verify job classes exist and are properly configured
3. Check job parameters match expected format
4. Review job dispatch logs for execution details

### Event Management Issues

1. **🔭 Filter by `n8n-events`**: Monitor event-related requests
2. Verify event classes exist and are properly configured
3. Check event parameter serialization
4. Review event subscription and dispatch logs

### Connection Issues

1. Verify Laravel is accessible: `curl http://127.0.0.1:8002`
2. **🔭 Check Telescope Requests**: Look for failed requests
3. Check firewall settings
4. Ensure both applications are running

### Authentication Issues

1. **🔭 Filter by `n8n-authenticated`**: See which requests have valid API keys
2. Check API key in Laravel `.env` file
3. Verify credentials in n8n match exactly
4. Use `/api/n8n/test-credentials` to verify API access

## 📁 File Structure

```
/path/to/your/
├── n8n-eloquent/           # Main package
│   ├── n8n-extension/      # n8n nodes (linked to ~/.n8n/nodes/)
│   └── src/               # Laravel package source
└── laravel-app/           # Clean test Laravel app
    ├── config/n8n-eloquent.php
    ├── config/telescope.php    # 🔭 Telescope configuration
    └── .env               # Contains API key + Telescope settings
```

## 🔗 Import Test Workflow

You can import the test workflow from `test-workflow.json` to quickly test the integration.

## 🔭 Telescope Configuration

The Telescope installation includes:

- **Local-only registration** - Only runs in local environment
- **Custom n8n tagging** - Automatic tagging of n8n-related activities
- **Security headers hidden** - API keys and signatures are masked
- **Debug-level logging** - Captures detailed logs for debugging
- **Optimized watchers** - Focused on relevant monitoring for n8n integration

## 🎯 Advanced Testing Scenarios

### Complete Integration Test

1. **Setup**: Create n8n workflow with Laravel trigger
2. **Register**: Activate workflow to register webhook
3. **Monitor**: Use health endpoints to verify registration
4. **Test**: Create/update/delete models to trigger events
5. **Verify**: Check n8n executions and webhook statistics
6. **Analyze**: Use analytics to review performance
7. **Cleanup**: Deactivate workflow to unsubscribe

### Job Workflow Test

1. **Discover**: List available jobs
2. **Configure**: Get job parameters and requirements
3. **Execute**: Dispatch jobs with proper parameters
4. **Monitor**: Track job execution and results
5. **Optimize**: Review performance and adjust as needed

### Event-Driven Workflow Test

1. **Discover**: List available custom events
2. **Subscribe**: Subscribe to specific events
3. **Trigger**: Manually dispatch events
4. **Monitor**: Track event webhook deliveries
5. **Validate**: Use health monitoring to verify delivery
6. **Optimize**: Review performance and adjust as needed

Happy testing with enhanced monitoring! 🚀🔭
