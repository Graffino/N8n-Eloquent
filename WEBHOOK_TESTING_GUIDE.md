# Laravel Eloquent n8n Webhook Testing Guide

## The Issue
The webhook registration (`/api/n8n/webhooks/subscribe`) is not being called automatically because **n8n only calls the `webhookCreate()` method when a workflow with a trigger node is properly activated**.

## Current Status ✅
- ✅ Authentication working (model dropdown loads)
- ✅ Laravel API endpoints working
- ✅ n8n nodes loading properly
- ✅ `webhookCreate()` method implemented correctly
- ❌ **Webhook registration not triggered automatically**

## Why Webhook Registration Isn't Happening

The `webhookCreate()` method in the LaravelEloquentTrigger node is only called when:

1. **A workflow is created** with the LaravelEloquentTrigger node
2. **All required parameters are configured** (model, events, credentials)
3. **The workflow is saved and activated** (turned ON)

## Testing Steps

### Step 1: Ensure Services Are Running

```bash
# Terminal 1: Start Laravel
cd /Users/Nick/Sites/laravel-n8n-test
php artisan serve --port=8002

# Terminal 2: Start n8n
cd /Users/Nick/Sites/n8n-eloquent
n8n start
```

### Step 2: Create Credentials in n8n

1. Open http://localhost:5678
2. Go to **Settings** → **Credentials**
3. Click **Add Credential**
4. Select **Laravel Eloquent API**
5. Configure:
   - **Base URL**: `http://127.0.0.1:8002`
   - **API Key**: `test-secret-key-for-integration`
   - **HMAC Secret**: (leave empty for now)
6. Click **Test** - should show ✅ success
7. **Save** the credential

### Step 3: Create Test Workflow

1. Click **+ Add Workflow**
2. **Add Node** → Search for "Laravel Eloquent Trigger"
3. Configure the trigger node:
   - **Credentials**: Select your Laravel API credential
   - **Model**: Select "User" from dropdown (this should load automatically)
   - **Events**: Select "Created", "Updated", "Deleted"
   - **Verify HMAC**: Enable
   - **Expected Source IP**: Leave empty
4. **Add another node** → Search for "Debug Helper" (to see the webhook data)
5. **Connect** the Laravel trigger to the Debug Helper
6. **Save** the workflow with a name like "Laravel User Events"

### Step 4: Activate the Workflow

1. Click the **toggle switch** in the top-right to activate the workflow
2. **Watch the n8n console output** - you should see:
   ```
   🔄 Laravel Eloquent webhook registration starting...
   📋 Registration details: { model: 'App\\Models\\User', events: [...], webhookUrl: '...' }
   🌐 Making authenticated request to webhook subscription endpoint
   ✅ Registration response: {...}
   💾 Stored subscription ID: ...
   🎉 Laravel Eloquent webhook registered successfully!
   ```

3. **Watch the Laravel console output** - you should see:
   ```
   2025-06-02 22:xx:xx /api/n8n/webhooks/subscribe ........................... ~ xxms
   ```

### Step 5: Test the Webhook

1. **Create a test user** in Laravel:
   ```bash
   cd /Users/Nick/Sites/laravel-n8n-test
   php test_webhook.php
   ```

2. **Check n8n executions**:
   - Go to the **Executions** tab in your workflow
   - You should see new executions triggered by the Laravel events

3. **Check Laravel Telescope**:
   - Open http://127.0.0.1:8002/telescope
   - Look for webhook delivery requests to n8n

## Expected Webhook Flow

```
1. User activates n8n workflow
   ↓
2. n8n calls webhookCreate() method
   ↓  
3. webhookCreate() calls POST /api/n8n/webhooks/subscribe
   ↓
4. Laravel stores webhook subscription in database
   ↓
5. When User model events occur (create/update/delete)
   ↓
6. Laravel ModelObserver triggers ModelLifecycleEvent
   ↓
7. ModelLifecycleListener calls WebhookService
   ↓
8. WebhookService sends POST to n8n webhook URL
   ↓
9. n8n receives webhook and executes workflow
```

## Debugging Tips

### If webhook registration fails:
1. Check n8n console for error messages
2. Check Laravel logs: `tail -f storage/logs/laravel.log`
3. Verify credentials are correct
4. Ensure Laravel server is running on port 8002

### If webhook delivery fails:
1. Check Laravel Telescope for failed HTTP requests
2. Verify n8n webhook URL is accessible
3. Check HMAC signature validation
4. Verify model has `HasN8nEvents` trait

### If no events are triggered:
1. Ensure User model has `HasN8nEvents` trait
2. Check if ModelObserver is registered
3. Verify webhook subscription exists in database:
   ```sql
   SELECT * FROM webhook_subscriptions;
   ```

## Import Test Workflow

You can import the test workflow from `test-laravel-eloquent-workflow.json`:

1. In n8n, click **Import from File**
2. Select the JSON file
3. Configure credentials
4. Activate the workflow

## Success Indicators

✅ **Webhook Registration Working**:
- n8n console shows registration success messages
- Laravel receives `/api/n8n/webhooks/subscribe` request
- Database has new webhook_subscription record

✅ **Webhook Delivery Working**:
- Creating/updating users triggers n8n workflow executions
- Laravel Telescope shows successful webhook deliveries
- n8n executions tab shows triggered workflows

## Common Issues

1. **"Model dropdown empty"** → Authentication issue
2. **"Webhook registration not called"** → Workflow not activated
3. **"Connection refused"** → Laravel server not running
4. **"Unauthorized"** → Wrong API key or credentials
5. **"No webhook events"** → Model missing `HasN8nEvents` trait 