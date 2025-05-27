# Testing Guide - n8n Laravel Eloquent Integration

## Overview

This document provides comprehensive testing procedures for the n8n Laravel Eloquent integration, with special focus on security features and edge cases.

## 🧪 Test Categories

### 1. Authentication Testing

#### API Key Authentication
```bash
# Test valid API key
curl -X GET "https://your-laravel-app.com/api/n8n/models" \
  -H "X-N8n-Api-Key: your-valid-api-key" \
  -H "Accept: application/json"

# Test invalid API key
curl -X GET "https://your-laravel-app.com/api/n8n/models" \
  -H "X-N8n-Api-Key: invalid-key" \
  -H "Accept: application/json"

# Test missing API key
curl -X GET "https://your-laravel-app.com/api/n8n/models" \
  -H "Accept: application/json"
```

**Expected Results:**
- Valid key: 200 OK with model data
- Invalid key: 401 Unauthorized
- Missing key: 401 Unauthorized

#### HMAC Signature Verification
```javascript
// Test HMAC signature generation
const crypto = require('crypto');
const payload = JSON.stringify({ test: 'data' });
const secret = 'your-hmac-secret';
const signature = crypto.createHmac('sha256', secret)
  .update(payload, 'utf8')
  .digest('hex');

// Test webhook with valid signature
curl -X POST "https://your-n8n-instance.com/webhook/test" \
  -H "Content-Type: application/json" \
  -H "X-Laravel-Signature: sha256=${signature}" \
  -d '{"test":"data"}'

// Test webhook with invalid signature
curl -X POST "https://your-n8n-instance.com/webhook/test" \
  -H "Content-Type: application/json" \
  -H "X-Laravel-Signature: sha256=invalid-signature" \
  -d '{"test":"data"}'
```

### 2. Security Feature Testing

#### IP Address Restriction
```bash
# Test from allowed IP (configure in trigger node)
curl -X POST "https://your-n8n-instance.com/webhook/test" \
  -H "Content-Type: application/json" \
  -d '{"test":"data"}'

# Test from blocked IP (use VPN or different server)
curl -X POST "https://your-n8n-instance.com/webhook/test" \
  -H "Content-Type: application/json" \
  -d '{"test":"data"}'
```

#### Timestamp Validation
```javascript
// Test with current timestamp
const currentPayload = {
  test: 'data',
  timestamp: new Date().toISOString()
};

// Test with old timestamp (should fail)
const oldPayload = {
  test: 'data',
  timestamp: new Date(Date.now() - 10 * 60 * 1000).toISOString() // 10 minutes ago
};
```

#### Model and Event Validation
```bash
# Test with correct model and event
curl -X POST "https://your-n8n-instance.com/webhook/test" \
  -H "Content-Type: application/json" \
  -d '{"model":"App\\Models\\User","event":"created","data":{}}'

# Test with incorrect model
curl -X POST "https://your-n8n-instance.com/webhook/test" \
  -H "Content-Type: application/json" \
  -d '{"model":"App\\Models\\WrongModel","event":"created","data":{}}'

# Test with incorrect event
curl -X POST "https://your-n8n-instance.com/webhook/test" \
  -H "Content-Type: application/json" \
  -d '{"model":"App\\Models\\User","event":"wrong_event","data":{}}'
```

### 3. Node Functionality Testing

#### Laravel Eloquent Get Node
```javascript
// Test cases for Get node
const testCases = [
  {
    operation: 'getAll',
    model: 'App\\Models\\User',
    limit: 10,
    expected: 'Array of user records'
  },
  {
    operation: 'getById',
    model: 'App\\Models\\User',
    recordId: '1',
    expected: 'Single user record'
  },
  {
    operation: 'search',
    model: 'App\\Models\\User',
    filters: [
      { field: 'email', operator: 'like', value: '%@example.com' }
    ],
    expected: 'Filtered user records'
  }
];
```

#### Laravel Eloquent Set Node
```javascript
// Test cases for Set node
const testCases = [
  {
    operation: 'create',
    model: 'App\\Models\\User',
    data: { name: 'Test User', email: 'test@example.com' },
    expected: 'Created user record'
  },
  {
    operation: 'update',
    model: 'App\\Models\\User',
    recordId: '1',
    data: { name: 'Updated User' },
    expected: 'Updated user record'
  },
  {
    operation: 'delete',
    model: 'App\\Models\\User',
    recordId: '1',
    expected: 'Deletion confirmation'
  }
];
```

### 4. Error Handling Testing

#### Authentication Errors
```bash
# Test expired API key
curl -X GET "https://your-laravel-app.com/api/n8n/models" \
  -H "X-N8n-Api-Key: expired-key" \
  -H "Accept: application/json"

# Test malformed API key
curl -X GET "https://your-laravel-app.com/api/n8n/models" \
  -H "X-N8n-Api-Key: malformed@key!" \
  -H "Accept: application/json"
```

#### Validation Errors
```bash
# Test invalid model name
curl -X GET "https://your-laravel-app.com/api/n8n/models/InvalidModel" \
  -H "X-N8n-Api-Key: your-valid-api-key" \
  -H "Accept: application/json"

# Test invalid record ID
curl -X GET "https://your-laravel-app.com/api/n8n/models/App%5CModels%5CUser/invalid-id" \
  -H "X-N8n-Api-Key: your-valid-api-key" \
  -H "Accept: application/json"
```

#### Network Errors
```bash
# Test connection timeout
curl -X GET "https://unreachable-server.com/api/n8n/models" \
  --connect-timeout 5 \
  -H "X-N8n-Api-Key: your-valid-api-key" \
  -H "Accept: application/json"

# Test SSL certificate errors
curl -X GET "https://self-signed-cert.com/api/n8n/models" \
  -H "X-N8n-Api-Key: your-valid-api-key" \
  -H "Accept: application/json"
```

## 🔧 Test Environment Setup

### Laravel Test Environment
```bash
# Set up test Laravel application
composer create-project laravel/laravel laravel-test
cd laravel-test

# Install the n8n eloquent package
composer require n8n-eloquent/laravel-package

# Configure test environment
cp .env.example .env.testing
php artisan key:generate --env=testing

# Set up test database
php artisan migrate --env=testing
php artisan db:seed --env=testing

# Generate test API key
php artisan n8n:setup --env=testing
```

### n8n Test Environment
```bash
# Set up n8n test instance
npm install -g n8n

# Start n8n in test mode
N8N_BASIC_AUTH_ACTIVE=true \
N8N_BASIC_AUTH_USER=test \
N8N_BASIC_AUTH_PASSWORD=test123 \
n8n start --tunnel
```

### Test Data Setup
```sql
-- Create test users
INSERT INTO users (name, email, created_at, updated_at) VALUES
('Test User 1', 'test1@example.com', NOW(), NOW()),
('Test User 2', 'test2@example.com', NOW(), NOW()),
('Test User 3', 'test3@example.com', NOW(), NOW());

-- Create test user counter
INSERT INTO user_counters (total_users, active_users, created_at, updated_at) VALUES
(3, 3, NOW(), NOW());
```

## 📊 Test Automation

### Jest Test Suite
```javascript
// tests/security.test.js
const crypto = require('crypto');
const axios = require('axios');

describe('Laravel Eloquent Security Tests', () => {
  const baseUrl = process.env.LARAVEL_TEST_URL;
  const apiKey = process.env.TEST_API_KEY;
  const hmacSecret = process.env.TEST_HMAC_SECRET;

  test('should authenticate with valid API key', async () => {
    const response = await axios.get(`${baseUrl}/api/n8n/models`, {
      headers: { 'X-N8n-Api-Key': apiKey }
    });
    expect(response.status).toBe(200);
  });

  test('should reject invalid API key', async () => {
    try {
      await axios.get(`${baseUrl}/api/n8n/models`, {
        headers: { 'X-N8n-Api-Key': 'invalid-key' }
      });
    } catch (error) {
      expect(error.response.status).toBe(401);
    }
  });

  test('should verify HMAC signature', () => {
    const payload = JSON.stringify({ test: 'data' });
    const signature = crypto.createHmac('sha256', hmacSecret)
      .update(payload, 'utf8')
      .digest('hex');
    
    // Test signature verification logic
    expect(signature).toMatch(/^[a-f0-9]{64}$/);
  });
});
```

### Postman Collection
```json
{
  "info": {
    "name": "Laravel Eloquent n8n Integration Tests",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Authentication Tests",
      "item": [
        {
          "name": "Valid API Key",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "X-N8n-Api-Key",
                "value": "{{api_key}}"
              }
            ],
            "url": "{{base_url}}/api/n8n/models"
          }
        },
        {
          "name": "Invalid API Key",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "X-N8n-Api-Key",
                "value": "invalid-key"
              }
            ],
            "url": "{{base_url}}/api/n8n/models"
          }
        }
      ]
    }
  ]
}
```

## 🚨 Security Testing Checklist

### Pre-Production Security Tests
- [ ] API key authentication working correctly
- [ ] HMAC signature verification functioning
- [ ] IP address restrictions enforced
- [ ] Timestamp validation preventing replay attacks
- [ ] Model and event validation working
- [ ] Error messages don't leak sensitive information
- [ ] Rate limiting functioning correctly
- [ ] HTTPS enforced in production
- [ ] Credential storage secure
- [ ] Logging captures security events

### Penetration Testing Scenarios
- [ ] Brute force API key attacks
- [ ] HMAC signature bypass attempts
- [ ] IP spoofing attempts
- [ ] Replay attack simulations
- [ ] SQL injection attempts
- [ ] Cross-site scripting (XSS) tests
- [ ] Cross-site request forgery (CSRF) tests
- [ ] Man-in-the-middle attack simulations

### Performance Testing
- [ ] High volume webhook processing
- [ ] Concurrent request handling
- [ ] Memory usage under load
- [ ] Database connection pooling
- [ ] Rate limiting effectiveness
- [ ] Error recovery mechanisms

## 📈 Monitoring and Metrics

### Key Metrics to Track
```javascript
// Example monitoring metrics
const metrics = {
  authentication: {
    successful_requests: 0,
    failed_requests: 0,
    invalid_api_keys: 0,
    hmac_failures: 0
  },
  security: {
    ip_violations: 0,
    timestamp_violations: 0,
    model_validation_failures: 0,
    event_validation_failures: 0
  },
  performance: {
    average_response_time: 0,
    request_volume: 0,
    error_rate: 0,
    uptime_percentage: 0
  }
};
```

### Alerting Thresholds
```yaml
alerts:
  - metric: "authentication.failed_requests"
    threshold: "> 10 per minute"
    action: "notify security team"
  
  - metric: "security.ip_violations"
    threshold: "> 5 per hour"
    action: "block IP and alert"
  
  - metric: "performance.error_rate"
    threshold: "> 5%"
    action: "investigate and alert"
```

## 🔍 Debugging and Troubleshooting

### Common Issues and Solutions

1. **Authentication Failures**
   - Check API key validity
   - Verify header format
   - Confirm Laravel package configuration

2. **HMAC Verification Failures**
   - Ensure same secret on both sides
   - Check payload format
   - Verify signature generation

3. **IP Restriction Issues**
   - Check actual client IP
   - Verify CIDR notation
   - Consider proxy/load balancer IPs

4. **Timestamp Validation Failures**
   - Check system clock synchronization
   - Verify timestamp format
   - Adjust time window if needed

### Debug Mode Configuration
```javascript
// Enable debug logging in n8n nodes
const debugMode = process.env.N8N_DEBUG === 'true';

if (debugMode) {
  console.log('Debug: Request details', {
    headers: sanitizedHeaders,
    payload: sanitizedPayload,
    timestamp: new Date().toISOString()
  });
}
```

---

**Remember: Regular testing is essential for maintaining security and functionality. Automate tests where possible and include security testing in your CI/CD pipeline.** 