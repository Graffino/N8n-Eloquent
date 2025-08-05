# Security Guide

## Overview

This guide covers security best practices for the n8n-eloquent integration. Following these guidelines is crucial for protecting your Laravel application and data when integrating with n8n.

## Authentication

### API Keys

1. **Generation**
   - Use strong, random keys
   - Minimum 32 characters
   - Store securely in `.env`

2. **Management**
   - Rotate keys regularly
   - Revoke compromised keys
   - Use different keys per environment

### HMAC Verification

1. **How it Works**
   ```php
   // Webhook payload is signed with HMAC
   $signature = hash_hmac('sha256', $payload, $secret);
   
   // n8n includes signature in headers
   X-N8n-Signature: sha256=...
   
   // Laravel verifies the signature
   if (!hash_equals($expected, $received)) {
       throw new SignatureInvalidException();
   }
   ```

2. **Configuration**
   ```env
   N8N_WEBHOOK_SECRET=your-secret-key
   N8N_WEBHOOK_SIGNATURE_HEADER=X-N8n-Signature
   ```

## Access Control

### IP Whitelisting

1. **Configuration**
   ```php
   // config/n8n-eloquent.php
   'security' => [
       'ip_whitelist' => [
           '192.168.1.1',
           '10.0.0.0/24'
       ]
   ]
   ```

2. **Middleware**
   - Automatically blocks non-whitelisted IPs
   - Logs unauthorized attempts
   - Supports CIDR notation

### Model Access

1. **Whitelist/Blacklist**
   ```php
   // config/n8n-eloquent.php
   'models' => [
       'mode' => 'whitelist',
       'whitelist' => [
           'App\\Models\\User',
           'App\\Models\\Order'
       ]
   ]
   ```

2. **Property Protection**
   ```php
   class User extends Model
   {
       protected static $webhookProperties = [
           'name',
           'email',
           'status'
       ];
       
       protected static $webhookHidden = [
           'password',
           'remember_token'
       ];
   }
   ```

## Rate Limiting

1. **Global Limits**
   ```php
   // config/n8n-eloquent.php
   'rate_limiting' => [
       'enabled' => true,
       'max_attempts' => 60,
       'decay_minutes' => 1
   ]
   ```

2. **Per-Model Limits**
   ```php
   class User extends Model
   {
       protected static $webhookRateLimit = 30;
       protected static $webhookRateLimitDecay = 1;
   }
   ```

## Error Handling

1. **Logging**
   - All security events are logged
   - Failed authentications tracked
   - Rate limit breaches recorded

2. **Notifications**
   ```php
   // config/n8n-eloquent.php
   'notifications' => [
       'security_events' => [
           'channels' => ['slack', 'email'],
           'recipients' => ['admin@example.com']
       ]
   ]
   ```

## Monitoring

1. **Health Checks**
   ```bash
   # Check webhook security status
   php artisan n8n:webhooks:health --security
   
   # View security logs
   php artisan n8n:webhooks:logs --security
   ```

2. **Telescope Integration**
   - Monitor webhook requests
   - Track authentication attempts
   - View security events

## Best Practices

1. **Environment Security**
   - Use HTTPS in production
   - Keep secrets out of version control
   - Use environment-specific keys

2. **Data Protection**
   - Minimize exposed data
   - Validate all inputs
   - Sanitize outputs

3. **Operational Security**
   - Monitor logs regularly
   - Set up alerts
   - Have incident response plan

## Security Checklist

- [ ] Strong API keys configured
- [ ] HMAC verification enabled
- [ ] IP whitelist configured
- [ ] Rate limiting enabled
- [ ] Sensitive data protected
- [ ] Logging configured
- [ ] Monitoring set up
- [ ] HTTPS enforced
- [ ] Regular key rotation planned
- [ ] Incident response documented 