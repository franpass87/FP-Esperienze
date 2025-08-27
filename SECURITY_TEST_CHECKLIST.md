# Security Test Checklist - FP Esperienze

## CSRF Protection Tests

### Frontend Forms
- [ ] **Voucher form nonce**: Apply voucher with valid nonce (should work)
- [ ] **Voucher form CSRF**: Try to apply voucher without nonce (should fail)
- [ ] **Voucher form CSRF**: Modify nonce in browser dev tools (should fail)
- [ ] **Booking form nonce**: Submit booking with valid nonce (should work)
- [ ] **Booking form CSRF**: Submit booking without nonce (should fail)

### Admin Forms
- [ ] **Admin voucher actions**: All voucher management actions require valid nonce
- [ ] **Admin booking actions**: All booking management actions require valid nonce
- [ ] **Admin settings**: Settings forms have nonce protection

## XSS Protection Tests

### Template Output Escaping
- [ ] **HTML escaping**: All user data in templates uses esc_html()
- [ ] **Attribute escaping**: All attributes use esc_attr()
- [ ] **URL escaping**: All URLs use esc_url()
- [ ] **FAQ content**: FAQ answers use wp_kses_post() for safe HTML

### Input Testing
- [ ] **Voucher code XSS**: Try voucher code with `<script>alert('xss')</script>`
- [ ] **Meeting point XSS**: Test meeting point fields with XSS payloads
- [ ] **Product name XSS**: Test product names with malicious scripts
- [ ] **FAQ XSS**: Test FAQ content with various XSS vectors

## Voucher Security Tests

### Rate Limiting
- [ ] **Redemption rate limit**: Try more than 5 voucher redemptions per minute (should be blocked)
- [ ] **Rate limit headers**: Verify X-RateLimit-* headers in responses
- [ ] **Rate limit bypass**: Try from different IP (should reset limit)

### Code Generation
- [ ] **Cryptographic randomness**: Verify voucher codes use random_bytes()
- [ ] **Code uniqueness**: Generate multiple vouchers, verify all codes are unique
- [ ] **Code format**: Verify codes are 12 characters, no confusing characters (0,O,I,1)

### QR Code Security
- [ ] **HMAC verification**: Valid QR codes pass verification
- [ ] **HMAC tampering**: Modified QR payloads fail verification
- [ ] **Key rotation**: Test with rotated HMAC keys
- [ ] **KID support**: Verify Key ID (kid) field in QR payload

## PDF Security Tests

### Access Control
- [ ] **Authenticated download**: Admin can download any voucher PDF
- [ ] **Owner download**: Customer can download their own voucher PDF
- [ ] **Unauthorized access**: Customer cannot download other's voucher PDFs
- [ ] **Nonce verification**: PDF download requires valid nonce

### File Protection
- [ ] **Direct access blocked**: Direct URL access to PDF files is denied
- [ ] **Directory listing**: Directory browsing is disabled (.htaccess)
- [ ] **Secure location**: PDFs stored in uploads/fp-esperienze/voucher/

## REST API Security Tests

### Rate Limiting
- [ ] **Availability API**: Test 30 requests/minute limit
- [ ] **Rate limit exceeded**: Verify 429 response when limit exceeded
- [ ] **Cache headers**: Verify cache headers on availability endpoint

### Permission Checks
- [ ] **Public endpoints**: Availability API accessible without auth
- [ ] **Protected endpoints**: Other APIs require proper authentication
- [ ] **Admin endpoints**: Admin-only endpoints check manage_fp_esperienze capability

## Brute Force Protection

### Voucher Redemption
- [ ] **Failed attempts**: Multiple invalid voucher codes trigger rate limit
- [ ] **IP-based limiting**: Rate limits are per IP address
- [ ] **Legitimate usage**: Valid vouchers still work after rate limit period

### Login Protection
- [ ] **Admin login**: Standard WordPress login protection applies
- [ ] **Failed login handling**: Multiple failed logins trigger WordPress protection

## Input Sanitization Tests

### POST Data
- [ ] **Voucher codes**: All voucher code inputs are sanitized
- [ ] **Product IDs**: All product ID inputs use absint()
- [ ] **Text fields**: All text inputs use sanitize_text_field()
- [ ] **Email fields**: Email inputs use sanitize_email()
- [ ] **Textarea fields**: Textarea inputs use sanitize_textarea_field()

### GET Parameters
- [ ] **URL parameters**: All GET parameters are properly sanitized
- [ ] **Query filtering**: Archive filters sanitize input values
- [ ] **Search terms**: Search inputs are sanitized

## SQL Injection Tests

### Database Queries
- [ ] **Prepared statements**: All queries use $wpdb->prepare()
- [ ] **Voucher lookup**: Voucher code queries are parameterized
- [ ] **Booking queries**: All booking database operations are safe

## File Upload Security

### PDF Generation
- [ ] **Path traversal**: PDF paths cannot escape intended directory
- [ ] **File permissions**: Generated files have proper permissions
- [ ] **Cleanup**: Temporary files are properly cleaned up

## Session Security

### Voucher Sessions
- [ ] **Session isolation**: Voucher sessions are per-user
- [ ] **Session cleanup**: Expired voucher sessions are cleaned up
- [ ] **Cross-user access**: Users cannot access other users' voucher sessions

## Error Information Disclosure

### Error Messages
- [ ] **Generic errors**: Error messages don't reveal system details
- [ ] **Debug information**: No debug info leaked in production
- [ ] **File paths**: File paths not exposed in error messages

## Testing Instructions

### Automated Tests
1. Run PHP security scanner: `wp eval-file security-test.php`
2. Check for WordPress security plugins compatibility
3. Review error logs for security warnings

### Manual Tests
1. Test each item in checklist with detailed steps
2. Use browser developer tools to modify requests
3. Test with different user roles (admin, shop manager, customer, guest)
4. Verify rate limiting with tools like curl or Postman

### Security Scanning
1. Run security scanners like WPScan
2. Check for known vulnerabilities in dependencies
3. Review code for security anti-patterns

## Verification Steps

For each test:
1. **Setup**: Describe test environment and prerequisites
2. **Action**: Perform specific test action
3. **Expected Result**: Document expected security behavior
4. **Actual Result**: Record what actually happened
5. **Status**: Pass/Fail/Needs Review

## Reporting

Document any security issues found with:
- Severity level (Critical/High/Medium/Low)
- Steps to reproduce
- Potential impact
- Recommended fix
- Timeline for resolution