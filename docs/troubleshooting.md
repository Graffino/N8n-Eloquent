# Troubleshooting Guide

## Common Issues

### 1. Webhook Registration Issues

#### Problem: Webhook Not Registering
```
🔄 Laravel Eloquent webhook registration failed
```

**Possible Causes:**
1. n8n not running
2. Wrong base URL
3. Invalid credentials

**Solutions:**
1. Check n8n status:
   ```bash
   n8n status
   ```
2. Verify base URL in credentials
3. Regenerate API keys

#### Problem: Registration Timeout
```
Timeout while waiting for webhook registration
```

**Solutions:**
1. Increase timeout in config:
   ```php
   'webhook_timeout' => 60 // seconds
   ```
2. Check network connectivity
3. Verify n8n is responsive

### 2. Authentication Issues

#### Problem: Invalid Signature
```
SignatureInvalidException: HMAC signature does not match
```

**Solutions:**
1. Check HMAC secrets match
2. Verify signature header name
3. Ensure payload not modified

#### Problem: Unauthorized
```
AuthenticationException: Invalid API key
```

**Solutions:**
1. Check API key in n8n credentials
2. Verify key in .env
3. Regenerate if compromised

### 3. Model Event Issues

#### Problem: Events Not Triggering
```
No events received for model changes
```

**Solutions:**
1. Check model configuration:
   ```php
   protected static $webhookEvents = [
       'created',
       'updated',
       'deleted'
   ];
   ```
2. Verify trait is used:
   ```php
   use HasWebhooks;
   ```
3. Check subscription exists:
   ```bash
   php artisan n8n:webhooks:list
   ```

#### Problem: Missing Data
```
Expected model data not in payload
```

**Solutions:**
1. Check property configuration:
   ```php
   protected static $webhookProperties = [
       'name',
       'email'
   ];
   ```
2. Verify property visibility
3. Check relationship loading

### 4. Performance Issues

#### Problem: Slow Webhook Delivery
```
Webhook delivery taking >1s
```

**Solutions:**
1. Enable queue:
   ```php
   'queue' => [
       'enabled' => true,
       'connection' => 'redis'
   ]
   ```
2. Optimize payload size
3. Check network latency

#### Problem: High Memory Usage
```
Allowed memory size exhausted
```

**Solutions:**
1. Limit relation depth
2. Use pagination
3. Optimize queries

### 5. Security Issues

#### Problem: Failed HMAC Verification
```
HMAC verification failed for webhook
```

**Solutions:**
1. Check secret keys match
2. Verify payload not modified
3. Check signature algorithm

#### Problem: Rate Limit Exceeded
```
Too many webhook requests
```

**Solutions:**
1. Adjust rate limits:
   ```php
   'rate_limiting' => [
       'max_attempts' => 60,
       'decay_minutes' => 1
   ]
   ```
2. Implement caching
3. Optimize request frequency

## Debugging Tools

### 1. Laravel Telescope
Monitor:
- Webhook requests
- Model events
- Queue jobs
- Exceptions

### 2. Artisan Commands
```bash
# List webhooks
php artisan n8n:webhooks:list

# Check health
php artisan n8n:webhooks:health

# Test webhook
php artisan n8n:webhooks:test

# View logs
php artisan n8n:webhooks:logs
```

### 3. n8n Debug Tools
1. Enable debug mode
2. Use "Debug" nodes
3. Check execution logs

## Getting Help

1. **Documentation**
   - [Setup Guide](setup.md)
   - [Node Documentation](nodes.md)
   - [Security Guide](security.md)

2. **Community**
   - GitHub Issues
   - Discord Channel
   - Stack Overflow

3. **Support**
   - Email: support@example.com
   - Response time: 24-48 hours 