# Security Guide

## Overview

This guide covers security best practices for the n8n-Eloquent integration. Following these guidelines is crucial for protecting your Laravel application and data when integrating with n8n, including webhook management, health monitoring, job dispatching, and event management.

## Authentication

### API Keys

1. **Generation**
   - Use strong, random keys (minimum 32 characters)
   - Generate using secure random functions
   - Store securely in `.env` file
   - Use different keys per environment

2. **Management**
   - Rotate keys regularly (recommended: every 90 days)
   - Revoke compromised keys immediately
   - Use different keys per environment (dev, staging, production)
   - Monitor key usage through health endpoints

3. **Configuration**

   ```env
   N8N_ELOQUENT_API_SECRET=your-strong-api-secret-key-here
   ```

4. **Testing**

   ```bash
   # Test API credentials
   curl -X POST \
        -H "X-N8n-Api-Key: your-api-secret-key" \
        -H "Content-Type: application/json" \
        http://127.0.0.1:8002/api/n8n/test-credentials
   ```

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
   N8N_WEBHOOK_SECRET=your-webhook-secret-key
   N8N_WEBHOOK_SIGNATURE_HEADER=X-N8n-Signature
   ```

3. **Verification Settings**

   ```php
   // config/n8n-eloquent.php
   'security' => [
       'verify_hmac' => true,
       'require_timestamp' => true,
       'timestamp_tolerance' => 300, // 5 minutes
   ]
   ```

## Access Control

### IP Whitelisting

1. **Configuration**

   ```php
   // config/n8n-eloquent.php
   'security' => [
       'ip_whitelist' => [
           '192.168.1.1',
           '10.0.0.0/24',
           '172.16.0.0/16'
       ],
       'ip_whitelist_enabled' => true
   ]
   ```

2. **Middleware Protection**
   - Automatically blocks non-whitelisted IPs
   - Logs unauthorized attempts
   - Supports CIDR notation for IP ranges
   - Configurable for different environments

3. **Dynamic IP Management**

   ```php
   // Add IPs programmatically
   $webhookService->addAllowedIp('203.0.113.1');
   $webhookService->removeAllowedIp('192.168.1.100');
   ```

### Model Access Control

1. **Whitelist/Blacklist Configuration**

   ```php
   // config/n8n-eloquent.php
   'models' => [
       'mode' => 'whitelist',
       'whitelist' => [
           'App\\Models\\User',
           'App\\Models\\Order',
           'App\\Models\\Product'
       ],
       'blacklist' => [
           'App\\Models\\SensitiveData'
       ]
   ]
   ```

2. **Property Protection**

   ```php
   class User extends Model
   {
       // Properties to include in webhooks
       protected static $webhookProperties = [
           'name',
           'email',
           'status',
           'created_at'
       ];
       
       // Properties to exclude from webhooks
       protected static $webhookHidden = [
           'password',
           'remember_token',
           'api_token',
           'credit_card_number'
       ];
       
       // Properties to mask in webhooks
       protected static $webhookMasked = [
           'email' => '***@***.com',
           'phone' => '***-***-****'
       ];
   }
   ```

3. **Event-Level Security**

   ```php
   class User extends Model
   {
       // Only allow specific events
       protected static $webhookEvents = [
           'created',
           'updated'
       ];
       
       // Custom security rules per event
       protected static function getWebhookSecurityRules($event)
       {
           return [
               'created' => ['include_relationships' => false],
               'updated' => ['include_relationships' => true]
           ];
       }
   }
   ```

### Job and Event Security

1. **Job Access Control**

   ```php
   // config/n8n-eloquent.php
   'jobs' => [
       'mode' => 'whitelist',
       'whitelist' => [
           'App\\Jobs\\SendEmail',
           'App\\Jobs\\ProcessOrder'
       ],
       'blacklist' => [
           'App\\Jobs\\SensitiveOperation'
       ]
   ]
   ```

2. **Event Access Control**

   ```php
   // config/n8n-eloquent.php
   'events' => [
       'mode' => 'whitelist',
       'whitelist' => [
           'App\\Events\\UserRegistered',
           'App\\Events\\OrderCreated'
       ],
       'blacklist' => [
           'App\\Events\\SensitiveEvent'
       ]
   ]
   ```

## Rate Limiting

### Global Rate Limiting

1. **Configuration**

   ```php
   // config/n8n-eloquent.php
   'rate_limiting' => [
       'enabled' => true,
       'max_attempts' => 60,
       'decay_minutes' => 1,
       'throttle_by' => 'ip', // or 'api_key'
       'exempt_ips' => ['127.0.0.1']
   ]
   ```

2. **Per-Endpoint Limits**

   ```php
   'rate_limiting' => [
       'endpoints' => [
           'models' => ['max_attempts' => 120, 'decay_minutes' => 1],
           'webhooks' => ['max_attempts' => 30, 'decay_minutes' => 1],
           'jobs' => ['max_attempts' => 20, 'decay_minutes' => 1],
           'events' => ['max_attempts' => 40, 'decay_minutes' => 1]
       ]
   ]
   ```

3. **Per-Model Limits**

   ```php
   class User extends Model
   {
       protected static $webhookRateLimit = 30;
       protected static $webhookRateLimitDecay = 1;
   }
   ```

### Rate Limit Headers

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1623456789
```

## Data Protection

### Input Validation

1. **Request Validation**

   ```php
   // All webhook subscriptions are validated
   $validator = Validator::make($request->all(), [
       'model' => 'required|string|max:255',
       'events' => 'required|array',
       'events.*' => 'string|in:created,updated,deleted,restored',
       'webhook_url' => 'required|url|max:500',
       'properties' => 'array',
       'properties.*' => 'string|max:100'
   ]);
   ```

2. **Model Data Validation**

   ```php
   // CRUD operations validate model data
   $rules = [
       'name' => 'required|string|max:255',
       'email' => 'required|email|unique:users',
       'password' => 'required|min:8'
   ];
   ```

### Output Sanitization

1. **Data Masking**

   ```php
   // Sensitive data is automatically masked
   protected static $webhookMasked = [
       'email' => '***@***.com',
       'phone' => '***-***-****',
       'ssn' => '***-**-****'
   ];
   ```

2. **Property Filtering**

   ```php
   // Only specified properties are included
   protected static $webhookProperties = [
       'id',
       'name',
       'email',
       'status'
   ];
   ```

### Encryption

1. **Database Encryption**

   ```php
   // Sensitive webhook data can be encrypted
   'encryption' => [
       'enabled' => true,
       'algorithm' => 'AES-256-CBC',
       'encrypt_webhook_urls' => true,
       'encrypt_metadata' => true
   ]
   ```

2. **Transport Security**
   - All webhook deliveries use HTTPS
   - TLS 1.2+ required for production
   - Certificate validation enforced

## Monitoring and Logging

### Security Event Logging

1. **Comprehensive Logging**

   ```php
   // All security events are logged
   Log::channel('n8n-security')->info('API key validation failed', [
       'ip' => $request->ip(),
       'user_agent' => $request->userAgent(),
       'endpoint' => $request->path()
   ]);
   ```

2. **Security Alerts**

   ```php
   // config/n8n-eloquent.php
   'notifications' => [
       'security_events' => [
           'channels' => ['slack', 'email'],
           'recipients' => ['admin@example.com'],
           'events' => [
               'authentication_failed',
               'rate_limit_exceeded',
               'ip_blocked',
               'webhook_tampering'
           ]
       ]
   ]
   ```

### Health Monitoring

1. **Security Health Checks**

   ```bash
   # Check security status
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/health/detailed
   ```

2. **Security Analytics**

   ```bash
   # Get security analytics
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/health/analytics
   ```

3. **Security Metrics Tracked**
   - Failed authentication attempts
   - Rate limit violations
   - IP blocking events
   - Webhook tampering attempts
   - Suspicious activity patterns

## Webhook Security

### Webhook Verification

1. **HMAC Signature Verification**

   ```php
   // Every webhook is verified
   if ($subscription->verify_hmac) {
       $expectedSignature = hash_hmac('sha256', $payload, $secret);
       if (!hash_equals($expectedSignature, $receivedSignature)) {
           throw new SignatureInvalidException();
       }
   }
   ```

2. **Timestamp Validation**

   ```php
   // Prevent replay attacks
   if ($subscription->require_timestamp) {
       $timestamp = $request->header('X-N8n-Timestamp');
       if (abs(time() - $timestamp) > 300) {
           throw new TimestampInvalidException();
       }
   }
   ```

3. **Source IP Validation**

   ```php
   // Validate webhook source
   if ($subscription->expected_source_ip) {
       if ($request->ip() !== $subscription->expected_source_ip) {
           throw new UnauthorizedException();
       }
   }
   ```

### Webhook Testing

1. **Secure Test Endpoints**

   ```bash
   # Test webhook security
   curl -X POST \
        -H "X-N8n-Api-Key: your-api-secret-key" \
        -H "Content-Type: application/json" \
        -d '{"test_data":"secure_test"}' \
        http://127.0.0.1:8002/api/n8n/webhooks/{subscription-id}/test
   ```

2. **Security Validation**

   ```bash
   # Validate webhook security
   curl -H "X-N8n-Api-Key: your-api-secret-key" \
        http://127.0.0.1:8002/api/n8n/health/validate/{subscription-id}
   ```

## Job and Event Security

### Job Security

1. **Job Parameter Validation**

   ```php
   // All job parameters are validated
   $validator = Validator::make($parameters, [
       'to' => 'required|email',
       'subject' => 'required|string|max:255',
       'body' => 'required|string'
   ]);
   ```

2. **Job Execution Limits**

   ```php
   // config/n8n-eloquent.php
   'jobs' => [
       'max_execution_time' => 300, // 5 minutes
       'max_memory' => '512M',
       'allowed_queues' => ['default', 'emails']
   ]
   ```

### Event Security

1. **Event Parameter Validation**

   ```php
   // Event parameters are validated before dispatch
   $validator = Validator::make($eventData, [
       'user' => 'required|array',
       'user.id' => 'required|integer',
       'user.email' => 'required|email'
   ]);
   ```

2. **Event Subscription Security**

   ```php
   // Event subscriptions require authentication
   $subscription = WebhookSubscription::create([
       'event_class' => $eventClass,
       'webhook_url' => $webhookUrl,
       'verify_hmac' => true,
       'require_timestamp' => true
   ]);
   ```

## Best Practices

### Environment Security

1. **Production Hardening**
   - Use HTTPS in production
   - Keep secrets out of version control
   - Use environment-specific keys
   - Enable all security features

2. **Development Security**
   - Use separate keys for development
   - Disable sensitive features in dev
   - Use local-only IP whitelists
   - Enable detailed logging

### Data Protection

1. **Minimize Data Exposure**
   - Only expose necessary properties
   - Use property masking for sensitive data
   - Implement field-level security
   - Regular security audits

2. **Input Validation**
   - Validate all inputs
   - Sanitize outputs
   - Use parameterized queries
   - Implement CSRF protection

### Operational Security

1. **Monitoring**
   - Monitor logs regularly
   - Set up security alerts
   - Review access patterns
   - Track security metrics

2. **Incident Response**
   - Have incident response plan
   - Document security procedures
   - Regular security training
   - Backup and recovery procedures

## Security Checklist

### Authentication & Authorization

- [ ] Strong API keys configured (32+ characters)
- [ ] HMAC verification enabled for webhooks
- [ ] IP whitelist configured and active
- [ ] Rate limiting enabled and configured
- [ ] Different keys per environment

### Data Protection

- [ ] Sensitive data properly masked
- [ ] Property filtering configured
- [ ] Input validation implemented
- [ ] Output sanitization active
- [ ] Encryption enabled for sensitive data

### Webhook Security

- [ ] HMAC signatures verified
- [ ] Timestamp validation enabled
- [ ] Source IP validation configured
- [ ] Webhook URLs use HTTPS
- [ ] Replay attack protection active

### Monitoring & Logging

- [ ] Security events logged
- [ ] Health monitoring active
- [ ] Security alerts configured
- [ ] Regular security audits scheduled
- [ ] Incident response plan documented

### Job & Event Security

- [ ] Job parameter validation active
- [ ] Event parameter validation enabled
- [ ] Execution limits configured
- [ ] Queue security implemented
- [ ] Access control lists configured

### Operational Security

- [ ] HTTPS enforced in production
- [ ] Secrets stored securely
- [ ] Regular key rotation planned
- [ ] Security training completed
- [ ] Backup procedures tested

## Security Tools

### Built-in Security Features

- **API Key Management**: Secure key generation and rotation
- **HMAC Verification**: Cryptographic signature validation
- **IP Whitelisting**: Network-level access control
- **Rate Limiting**: Request throttling and protection
- **Health Monitoring**: Security status tracking
- **Audit Logging**: Comprehensive security event logging

### Security Endpoints

```bash
# Test credentials
POST /api/n8n/test-credentials

# Health check with security status
GET /api/n8n/health/detailed

# Security analytics
GET /api/n8n/health/analytics

# Validate webhook security
GET /api/n8n/health/validate/{subscription}
```

### Security Commands

```bash
# Security health check
php artisan n8n:security:check

# Rotate API keys
php artisan n8n:security:rotate-keys

# Audit security logs
php artisan n8n:security:audit

# Generate security report
php artisan n8n:security:report
```
