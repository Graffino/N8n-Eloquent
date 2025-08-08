# Troubleshooting Guide

## Overview

This guide covers common issues and solutions for the n8n-Eloquent integration, including webhook management, health monitoring, job dispatching, and event management.

## Common Issues

### 1. Authentication Issues

#### Problem: Invalid API Key

```
AuthenticationException: Invalid API key
```

**Possible Causes:**

1. API key not set in n8n credentials
2. API key mismatch between Laravel and n8n
3. API key not included in request headers

**Solutions:**

1. Check API key in n8n credentials:
   - Go to **Settings** → **Credentials**
   - Verify **API Key** matches Laravel `.env` file
2. Verify Laravel configuration:

   ```env
   N8N_ELOQUENT_API_SECRET=your-api-secret-key
   ```

3. Test credentials:

   ```bash
   curl -X POST \
        -H "X-N8n-Api-Key: your-api-secret-key" \
        -H "Content-Type: application/json" \
        http://127.0.0.1:8002/api/n8n/test-credentials
   ```

#### Problem: HMAC Signature Verification Failed

```
SignatureInvalidException: HMAC signature does not match
```

**Solutions:**

1. Check HMAC secrets match between Laravel and n8n
2. Verify signature header name configuration
3. Ensure payload not modified during transmission
4. Check timestamp validation settings

### 2. Model Discovery Issues

#### Problem: Model Dropdown Empty

```
No models available in n8n dropdown
```

**Possible Causes:**

1. Laravel server not running
2. Authentication failed
3. Models not properly configured
4. API endpoint not accessible

**Solutions:**

1. Check Laravel server status:

   ```bash
   curl http://127.0.0.1:8002/api/n8n/models
   ```

2. Verify model configuration in `config/n8n-eloquent.php`:

   ```php
   'models' => [
       'mode' => 'whitelist',
       'whitelist' => ['App\\Models\\User', 'App\\Models\\Post'],
   ]
   ```

3. Test model discovery endpoint:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/models
   ```

#### Problem: Model Not Found

```
Model App\Models\NonExistentModel not found
```

**Solutions:**

1. Verify model class exists and is properly namespaced
2. Check model is included in configuration whitelist
3. Ensure model extends `Illuminate\Database\Eloquent\Model`
4. Verify model is discoverable by Laravel

### 3. Webhook Registration Issues

#### Problem: Webhook Not Registering

```
🔄 Laravel Eloquent webhook registration failed
```

**Possible Causes:**

1. n8n not running
2. Wrong base URL in credentials
3. Invalid credentials
4. Network connectivity issues

**Solutions:**

1. Check n8n status:

   ```bash
   n8n status
   ```

2. Verify base URL in n8n credentials
3. Test connectivity:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/health
   ```

4. Check Laravel logs:

   ```bash
   tail -f storage/logs/laravel.log | grep n8n
   ```

#### Problem: Registration Timeout

```
Timeout while waiting for webhook registration
```

**Solutions:**

1. Increase timeout in configuration:

   ```php
   'webhook_timeout' => 60 // seconds
   ```

2. Check network connectivity between n8n and Laravel
3. Verify n8n is responsive and not overloaded
4. Check firewall settings

### 4. Webhook Delivery Issues

#### Problem: Webhooks Not Delivered

```
No webhook events received in n8n
```

**Possible Causes:**

1. Webhook subscription not active
2. Model events not configured
3. Webhook URL not accessible
4. HMAC verification failing

**Solutions:**

1. Check webhook subscription status:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/webhooks
   ```

2. Verify model events configuration:

   ```php
   protected static $webhookEvents = ['created', 'updated', 'deleted'];
   ```

3. Test webhook delivery:

   ```bash
   curl -X POST \
        -H "X-N8n-Api-Key: your-api-secret-key" \
        -H "Content-Type: application/json" \
        -d '{"test_data":"Hello"}' \
        http://127.0.0.1:8002/api/n8n/webhooks/{subscription-id}/test
   ```

#### Problem: Webhook Delivery Failures

```
Webhook delivery failed with status 500
```

**Solutions:**

1. Check webhook statistics:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/webhooks/stats
   ```

2. Validate specific subscription:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/health/validate/{subscription-id}
   ```

3. Check n8n webhook endpoint is accessible
4. Verify HMAC signature configuration

### 5. Health Monitoring Issues

#### Problem: Health Check Failing

```
Health check returns unhealthy status
```

**Solutions:**

1. Check detailed health status:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/health/detailed
   ```

2. Review health analytics:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/health/analytics
   ```

3. Check database connectivity
4. Verify webhook service is running

#### Problem: High Error Rates

```
Success rate below 95%
```

**Solutions:**

1. Review performance metrics in health analytics
2. Check for failed webhook deliveries
3. Investigate network connectivity issues
4. Review webhook timeout settings
5. Check n8n server performance

### 6. Job Management Issues

#### Problem: Jobs Not Found

```
Job App\Jobs\NonExistentJob not found
```

**Solutions:**

1. Verify job class exists and is properly namespaced
2. Check job is discoverable by Laravel
3. Ensure job extends `Illuminate\Bus\Queueable`
4. Test job discovery:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/jobs
   ```

#### Problem: Job Dispatch Failing

```
Job dispatch failed with validation errors
```

**Solutions:**

1. Check job parameters:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        "http://127.0.0.1:8002/api/n8n/jobs/App%5CJobs%5CSendEmail/parameters"
   ```

2. Verify required parameters are provided
3. Check job constructor requirements
4. Review job validation rules

### 7. Event Management Issues

#### Problem: Events Not Found

```
Event App\Events\NonExistentEvent not found
```

**Solutions:**

1. Verify event class exists and is properly namespaced
2. Check event is discoverable by Laravel
3. Ensure event extends `Illuminate\Foundation\Events\Dispatchable`
4. Test event discovery:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/events
   ```

#### Problem: Event Subscription Failing

```
Event subscription failed
```

**Solutions:**

1. Check event parameters:

   ```bash
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        "http://127.0.0.1:8002/api/n8n/events/App%5CEvents%5CUserRegistered/parameters"
   ```

2. Verify webhook URL is accessible
3. Check event subscription payload format
4. Review event serialization

### 8. Performance Issues

#### Problem: Slow Webhook Delivery

```
Webhook delivery taking >1s
```

**Solutions:**

1. Enable queue processing:

   ```php
   'queue' => [
       'enabled' => true,
       'connection' => 'redis'
   ]
   ```

2. Optimize payload size by limiting properties
3. Check network latency between services
4. Review webhook timeout settings

#### Problem: High Memory Usage

```
Allowed memory size exhausted
```

**Solutions:**

1. Limit relationship depth in webhook payloads
2. Use pagination for large datasets
3. Optimize database queries
4. Review model property configuration

#### Problem: Rate Limiting

```
Too many requests (429)
```

**Solutions:**

1. Check current rate limits:

   ```php
   'rate_limiting' => [
       'max_attempts' => 60,
       'decay_minutes' => 1
   ]
   ```

2. Implement request caching
3. Optimize request frequency
4. Consider increasing limits for high-traffic scenarios

### 9. Security Issues

#### Problem: Failed HMAC Verification

```
HMAC verification failed for webhook
```

**Solutions:**

1. Check secret keys match between Laravel and n8n
2. Verify payload not modified during transmission
3. Check signature algorithm configuration
4. Review timestamp validation settings

#### Problem: Unauthorized IP Access

```
IP address not in whitelist
```

**Solutions:**

1. Check IP whitelist configuration:

   ```php
   'security' => [
       'ip_whitelist' => [
           '192.168.1.1',
           '10.0.0.0/24'
       ]
   ]
   ```

2. Add n8n server IP to whitelist
3. Use CIDR notation for IP ranges
4. Review firewall settings

## Debugging Tools

### 1. Laravel Telescope

Monitor all n8n-related activities:

- **Requests Tab**: Monitor API calls from n8n
- **Models Tab**: Track Eloquent model events
- **Exceptions Tab**: Debug authentication and webhook errors
- **Logs Tab**: View detailed debug logs

**Filter by tags:**

- `n8n-api` - All n8n API requests
- `n8n-webhooks` - Webhook operations
- `n8n-health` - Health monitoring
- `n8n-jobs` - Job management
- `n8n-events` - Event management
- `n8n-error` - n8n-related exceptions

### 2. Artisan Commands

```bash
# List webhook subscriptions
php artisan n8n:webhooks:list

# Check webhook health
php artisan n8n:webhooks:health

# Test webhook delivery
php artisan n8n:webhooks:test

# View webhook logs
php artisan n8n:webhooks:logs

# Recover webhook subscriptions
php artisan n8n:webhooks:recover

# Clean up stale subscriptions
php artisan n8n:webhooks:cleanup
```

### 3. API Endpoints for Debugging

#### Health Monitoring

```bash
# Basic health check
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health

# Detailed health analysis
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/detailed

# Performance analytics
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/analytics
```

#### Webhook Management

```bash
# List all webhooks
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/webhooks

# Get webhook statistics
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/webhooks/stats

# Validate specific subscription
curl -H "X-N8n-Api-Key: your-api-secret-key" \
     http://127.0.0.1:8002/api/n8n/health/validate/{subscription-id}
```

### 4. n8n Debug Tools

1. **Enable Debug Mode**: Set `N8N_LOG_LEVEL=debug`
2. **Use Debug Nodes**: Add debug nodes to workflows
3. **Check Execution Logs**: Review workflow execution history
4. **Monitor Console Output**: Watch for error messages

## Common Error Messages

### Authentication Errors

- `401 Unauthorized`: Invalid or missing API key
- `403 Forbidden`: IP not in whitelist or insufficient permissions
- `SignatureInvalidException`: HMAC signature verification failed

### Validation Errors

- `422 Validation Error`: Invalid input data
- `Model not found`: Model class doesn't exist or not configured
- `Job not found`: Job class doesn't exist or not discoverable
- `Event not found`: Event class doesn't exist or not discoverable

### Network Errors

- `Connection refused`: Laravel server not running
- `Timeout`: Request took too long to complete
- `DNS resolution failed`: Invalid base URL

### Rate Limiting

- `429 Too Many Requests`: Rate limit exceeded
- `X-RateLimit-Remaining: 0`: No requests remaining in current window

## Performance Optimization

### 1. Webhook Optimization

- Use queue processing for webhook delivery
- Limit payload size by specifying properties
- Implement webhook batching for high-frequency events
- Use connection pooling for HTTP requests

### 2. Database Optimization

- Add indexes on frequently queried fields
- Use eager loading for relationships
- Implement query result caching
- Optimize model property serialization

### 3. Network Optimization

- Use HTTP/2 for better performance
- Implement request compression
- Use connection keep-alive
- Consider CDN for static assets

## Getting Help

### 1. Documentation

- [Setup Guide](setup.md)
- [API Reference](api.md)
- [Security Guide](security.md)
- [Node Documentation](nodes.md)

### 2. Community Resources

- GitHub Issues: Report bugs and request features
- Discord Channel: Get real-time help from community
- Stack Overflow: Search for existing solutions

### 3. Support Channels

- Email: <support@example.com>
- Response time: 24-48 hours
- Priority support available for enterprise customers

### 4. Self-Service Tools

- Health monitoring endpoints for system status
- Detailed error logging for debugging
- Performance analytics for optimization
- Automatic recovery mechanisms for common issues
