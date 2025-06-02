# Laravel n8n Eloquent Integration - Testing Instructions

## 🎉 Setup Complete!

You now have a clean Laravel installation with the n8n Eloquent integration and **Laravel Telescope** ready for testing and debugging.

## 🚀 What's Running

1. **Laravel Application**: `http://127.0.0.1:8002`
   - Location: `/Users/Nick/Sites/laravel-n8n-test`
   - API Key: `test-secret-key-for-integration`

2. **n8n Instance**: `http://localhost:5678`
   - Laravel Eloquent nodes are installed and linked
   - Official Laravel logos added to all nodes

3. **🔭 Laravel Telescope**: `http://127.0.0.1:8002/telescope`
   - Advanced debugging and monitoring dashboard
   - Custom n8n tagging for easy filtering
   - Real-time request, query, and model event monitoring

## 📋 Testing Steps

### 1. Set up n8n Credentials

1. Open n8n at `http://localhost:5678`
2. Go to **Settings** → **Credentials**
3. Create new **Laravel Eloquent API** credential:
   - **Base URL**: `http://127.0.0.1:8002`
   - **API Key**: `test-secret-key-for-integration`
   - **HMAC Secret**: (leave empty for testing)

### 2. Test Model Discovery

1. Create a new workflow
2. Add a **Laravel Eloquent Trigger** node
3. Select your credentials
4. Check that the **Model** dropdown populates with `App\Models\User`

### 3. Test Webhook Registration

1. Configure the trigger:
   - **Model**: `App\Models\User`
   - **Events**: `created`, `updated`
   - **Verify HMAC**: `false` (for testing)
   - **Require Timestamp**: `false` (for testing)

2. Save and activate the workflow
3. Check the n8n console for webhook registration logs
4. **🔭 Monitor in Telescope**: Go to `http://127.0.0.1:8002/telescope` and filter by `n8n-webhooks` tag

### 4. Test Data Retrieval

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

## 🔧 Manual Testing

### Test API Endpoints

```bash
# Test models endpoint
curl -H "X-N8n-Api-Key: test-secret-key-for-integration" \
     -H "Accept: application/json" \
     http://127.0.0.1:8002/api/n8n/models

# Test webhook subscription
curl -X POST \
     -H "X-N8n-Api-Key: test-secret-key-for-integration" \
     -H "Content-Type: application/json" \
     -d '{"model":"App\\Models\\User","events":["created"],"webhook_url":"http://test.com/webhook"}' \
     http://127.0.0.1:8002/api/n8n/webhooks/subscribe
```

### Create Test User

```bash
# SSH into Laravel app
cd /Users/Nick/Sites/laravel-n8n-test

# Create a test user via tinker
php artisan tinker
>>> User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password')])
```

**🔭 After creating a user, check Telescope's Models tab to see the `created` event!**

## 🎨 Features Implemented

✅ **Clean Laravel Installation** - Fresh Laravel 12 project  
✅ **Laravel Logo Integration** - Official Laravel logos on all nodes  
✅ **Model Dropdown** - Dynamic model discovery from Laravel API  
✅ **Enhanced Debugging** - Comprehensive logging and error handling  
✅ **Webhook Registration** - Automatic webhook lifecycle management  
✅ **Security Features** - HMAC verification, IP filtering, timestamp validation  
✅ **🔭 Laravel Telescope** - Advanced monitoring and debugging dashboard  
✅ **Custom n8n Tagging** - Easy filtering of n8n-related activities  

## 🐛 Troubleshooting

### Webhook Registration Issues

1. Check Laravel logs: `tail -f /Users/Nick/Sites/laravel-n8n-test/storage/logs/laravel.log`
2. Check n8n console output for detailed error messages
3. **🔭 Use Telescope**: Filter by `n8n-webhooks` tag to see webhook requests
4. **🔭 Check Exceptions**: Look for `n8n-error` tagged exceptions
5. Verify API key is correct in both Laravel `.env` and n8n credentials

### Model Discovery Issues

1. Ensure Laravel server is running on port 8002
2. Test the models endpoint manually (see curl command above)
3. **🔭 Monitor in Telescope**: Filter by `n8n-models` tag
4. Check that the API key matches in both applications

### Connection Issues

1. Verify Laravel is accessible: `curl http://127.0.0.1:8002`
2. **🔭 Check Telescope Requests**: Look for failed requests
3. Check firewall settings
4. Ensure both applications are running

### Authentication Issues

1. **🔭 Filter by `n8n-authenticated`**: See which requests have valid API keys
2. Check API key in Laravel `.env` file
3. Verify credentials in n8n match exactly

## 📁 File Structure

```
/Users/Nick/Sites/
├── n8n-eloquent/           # Main package
│   ├── n8n-extension/      # n8n nodes (linked to ~/.n8n/nodes/)
│   └── src/               # Laravel package source
└── laravel-n8n-test/     # Clean test Laravel app
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

Happy testing with enhanced monitoring! 🚀🔭 