# Security Guide - n8n Laravel Eloquent Integration

## Overview

This document outlines the security features and best practices for the n8n Laravel Eloquent integration. The extension implements multiple layers of security to ensure safe communication between n8n and Laravel applications.

## 🔐 Security Features

### 1. API Key Authentication

**Implementation:**
- All requests to Laravel use API key authentication via `X-N8n-Api-Key` header
- API keys are generated during Laravel package setup
- Keys are stored securely in n8n credentials

**Best Practices:**
- Use strong, randomly generated API keys (minimum 32 characters)
- Rotate API keys regularly (recommended: every 90 days)
- Store keys securely and never commit them to version control
- Use different API keys for different environments (dev, staging, production)

### 2. HMAC Signature Verification

**Implementation:**
- Optional HMAC-SHA256 signature verification for webhook payloads
- Prevents unauthorized webhook calls and ensures data integrity
- Uses timing-safe comparison to prevent timing attacks

**Configuration:**
```typescript
// In Laravel Eloquent Trigger node
{
  "verifyHmac": true,
  "requireTimestamp": true
}
```

**Best Practices:**
- Always enable HMAC verification in production
- Use strong, unique HMAC secrets (minimum 32 characters)
- Rotate HMAC secrets regularly
- Ensure Laravel and n8n use the same secret

### 3. Timestamp Validation (Replay Attack Prevention)

**Implementation:**
- Validates webhook timestamps to prevent replay attacks
- Rejects webhooks older than 5 minutes
- Configurable via `requireTimestamp` parameter

**Security Benefits:**
- Prevents replay attacks using captured webhook payloads
- Ensures webhook freshness
- Mitigates man-in-the-middle attack scenarios

### 4. IP Address Restriction

**Implementation:**
- Optional IP address or CIDR range restriction for webhooks
- Supports both single IP and subnet filtering
- Configurable per trigger node

**Configuration Examples:**
```typescript
// Single IP
"expectedSourceIp": "192.168.1.100"

// CIDR range
"expectedSourceIp": "192.168.1.0/24"

// Public IP
"expectedSourceIp": "203.0.113.10"
```

### 5. Model and Event Validation

**Implementation:**
- Validates incoming webhooks match configured model and events
- Prevents unauthorized model access
- Ensures webhook integrity

**Security Benefits:**
- Prevents cross-model data leakage
- Ensures only configured events are processed
- Validates webhook authenticity

### 6. Enhanced Error Handling and Logging

**Implementation:**
- Comprehensive security violation logging
- Sanitized headers in logs (removes sensitive data)
- Detailed error categorization (auth, validation, etc.)

**Logged Information:**
- Error type and message
- Source IP address
- Timestamp
- Sanitized headers
- Operation and model context

## 🛡️ Security Best Practices

### For Laravel Applications

1. **API Key Management:**
   ```bash
   # Generate strong API keys
   php artisan n8n:setup --generate-key
   
   # Rotate keys regularly
   php artisan n8n:rotate-key
   ```

2. **HTTPS Configuration:**
   - Always use HTTPS in production
   - Configure proper SSL/TLS certificates
   - Enable HSTS headers

3. **Rate Limiting:**
   - Configure rate limiting in Laravel
   - Use Redis for distributed rate limiting
   - Monitor for unusual traffic patterns

4. **Database Security:**
   - Use database connection encryption
   - Implement proper database user permissions
   - Regular security updates

### For n8n Instances

1. **Credential Security:**
   - Use n8n's credential encryption
   - Restrict credential access to authorized users
   - Regular credential audits

2. **Network Security:**
   - Deploy n8n behind a firewall
   - Use VPN for remote access
   - Implement network segmentation

3. **Access Control:**
   - Use strong authentication for n8n
   - Implement role-based access control
   - Regular user access reviews

### For Webhook Security

1. **HMAC Configuration:**
   ```typescript
   // Strong HMAC secret generation
   const crypto = require('crypto');
   const hmacSecret = crypto.randomBytes(32).toString('hex');
   ```

2. **IP Whitelisting:**
   ```typescript
   // Production configuration
   {
     "expectedSourceIp": "10.0.0.0/8",  // Internal network only
     "verifyHmac": true,
     "requireTimestamp": true
   }
   ```

3. **Monitoring:**
   - Monitor webhook failure rates
   - Alert on authentication failures
   - Log and analyze security violations

## 🚨 Security Incident Response

### Authentication Failures

1. **Immediate Actions:**
   - Check API key validity
   - Verify HMAC secret configuration
   - Review recent credential changes

2. **Investigation:**
   - Analyze error logs
   - Check source IP addresses
   - Review webhook payload integrity

3. **Resolution:**
   - Rotate compromised credentials
   - Update security configurations
   - Implement additional restrictions if needed

### Suspicious Activity

1. **Detection Indicators:**
   - Multiple authentication failures
   - Requests from unexpected IP addresses
   - Unusual traffic patterns
   - Invalid model/event combinations

2. **Response Actions:**
   - Enable additional logging
   - Implement temporary IP restrictions
   - Review and rotate credentials
   - Analyze attack patterns

## 🔍 Security Monitoring

### Key Metrics to Monitor

1. **Authentication Metrics:**
   - API key authentication success/failure rates
   - HMAC verification success/failure rates
   - Geographic distribution of requests

2. **Traffic Metrics:**
   - Request volume and patterns
   - Response time anomalies
   - Error rate trends

3. **Security Violations:**
   - IP restriction violations
   - Timestamp validation failures
   - Model/event validation failures

### Alerting Configuration

```yaml
# Example monitoring alerts
alerts:
  - name: "High Authentication Failure Rate"
    condition: "auth_failures > 10 per minute"
    action: "notify security team"
  
  - name: "Unexpected Source IP"
    condition: "requests from non-whitelisted IPs"
    action: "block and alert"
  
  - name: "HMAC Verification Failures"
    condition: "hmac_failures > 5 per hour"
    action: "investigate and alert"
```

## 📋 Security Checklist

### Pre-Production Deployment

- [ ] API keys generated and securely stored
- [ ] HMAC secrets configured and tested
- [ ] HTTPS enabled with valid certificates
- [ ] IP restrictions configured appropriately
- [ ] Rate limiting enabled
- [ ] Monitoring and alerting configured
- [ ] Security testing completed
- [ ] Incident response procedures documented

### Regular Security Reviews

- [ ] Credential rotation (quarterly)
- [ ] Access control review (monthly)
- [ ] Security log analysis (weekly)
- [ ] Vulnerability assessments (annually)
- [ ] Penetration testing (annually)
- [ ] Security training updates (annually)

## 🔗 Additional Resources

- [Laravel Security Documentation](https://laravel.com/docs/security)
- [n8n Security Best Practices](https://docs.n8n.io/security/)
- [OWASP API Security Top 10](https://owasp.org/www-project-api-security/)
- [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)

## 📞 Security Contact

For security-related issues or questions:
- Email: security@n8n-eloquent.com
- Security Advisory: [GitHub Security Advisories](https://github.com/n8n-io/n8n-eloquent/security/advisories)
- Bug Bounty: [Responsible Disclosure Program](https://github.com/n8n-io/n8n-eloquent/security/policy)

---

**Remember: Security is a shared responsibility between the Laravel application, n8n instance, and network infrastructure. Regular reviews and updates are essential for maintaining a secure integration.** 