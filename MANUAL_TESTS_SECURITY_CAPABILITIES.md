# Manual Permission Tests - Security Capabilities

Test the new `manage_fp_esperienze` capability system across different user roles.

## Test Environment Setup

1. WordPress site with WooCommerce and FP Esperienze activated
2. Test users with different roles:
   - **Administrator** (should have `manage_fp_esperienze`)
   - **Shop Manager** (should have `manage_fp_esperienze`)
   - **Editor** (should NOT have `manage_fp_esperienze`)
   - **Customer** (should NOT have `manage_fp_esperienze`)

## Test Cases

### 1. Admin Menu Access Control

**Objective**: Verify proper access control for FP Esperienze admin pages

#### 1.1 Administrator Access
**Steps**:
1. Log in as Administrator
2. Navigate to WordPress admin
3. Check left sidebar for "FP Esperienze" menu
4. Try to access each submenu:
   - Dashboard (`/wp-admin/admin.php?page=fp-esperienze`)
   - Bookings (`/wp-admin/admin.php?page=fp-esperienze-bookings`)
   - Meeting Points (`/wp-admin/admin.php?page=fp-esperienze-meeting-points`)
   - Extras (`/wp-admin/admin.php?page=fp-esperienze-extras`)
   - Vouchers (`/wp-admin/admin.php?page=fp-esperienze-vouchers`)
   - Closures (`/wp-admin/admin.php?page=fp-esperienze-closures`)
   - Settings (`/wp-admin/admin.php?page=fp-esperienze-settings`)

**Expected Results**:
- [ ] FP Esperienze menu is visible
- [ ] All submenus are accessible
- [ ] All pages load successfully without permission errors

#### 1.2 Shop Manager Access
**Steps**:
1. Log in as Shop Manager
2. Repeat steps from 1.1

**Expected Results**:
- [ ] FP Esperienze menu is visible
- [ ] All submenus are accessible
- [ ] All pages load successfully without permission errors

#### 1.3 Editor Access (Should be Denied)
**Steps**:
1. Log in as Editor
2. Check if FP Esperienze menu is visible
3. Try direct URL access to: `/wp-admin/admin.php?page=fp-esperienze`

**Expected Results**:
- [ ] FP Esperienze menu is NOT visible
- [ ] Direct URL access shows "You do not have sufficient permissions" error

#### 1.4 Customer Access (Should be Denied)
**Steps**:
1. Log in as Customer
2. Check if FP Esperienze menu is visible in frontend user account
3. Try direct URL access to: `/wp-admin/admin.php?page=fp-esperienze`

**Expected Results**:
- [ ] FP Esperienze menu is NOT visible
- [ ] Direct URL access shows "You do not have sufficient permissions" error

### 2. Admin Action Security Tests

**Objective**: Verify nonce and capability protection for admin actions

#### 2.1 Meeting Point CRUD Operations

**Test as Administrator/Shop Manager**:
1. Navigate to Meeting Points page
2. Create a new meeting point
3. Edit an existing meeting point
4. Delete a meeting point

**Expected Results**:
- [ ] All operations complete successfully
- [ ] Success messages are displayed
- [ ] No security errors

**Test as Editor** (if they somehow access the form):
1. Try to submit meeting point form directly via browser developer tools
2. Use invalid nonce or no nonce

**Expected Results**:
- [ ] Operations are blocked with "Security check failed" message
- [ ] No data is created/modified

#### 2.2 Extras Management

**Test as Administrator/Shop Manager**:
1. Navigate to Extras page
2. Create a new extra
3. Edit an existing extra
4. Delete an extra

**Expected Results**:
- [ ] All operations complete successfully
- [ ] Proper nonce verification occurs
- [ ] No security errors

#### 2.3 Voucher Management

**Test as Administrator/Shop Manager**:
1. Navigate to Vouchers page
2. Void a voucher
3. Resend voucher email
4. Extend voucher expiration

**Expected Results**:
- [ ] All operations complete successfully
- [ ] Audit logs are created
- [ ] No security errors

#### 2.4 Closures Management

**Test as Administrator/Shop Manager**:
1. Navigate to Closures page
2. Add a global closure
3. Remove a closure

**Expected Results**:
- [ ] Operations complete successfully
- [ ] Proper nonce verification
- [ ] No security errors

### 3. REST API Rate Limiting Tests

**Objective**: Verify rate limiting on /availability endpoint

#### 3.1 Normal Usage
**Steps**:
1. Make availability API requests: `GET /wp-json/fp-exp/v1/availability?product_id=1&date=2024-12-25`
2. Check response headers
3. Verify data is returned

**Expected Results**:
- [ ] Requests complete successfully
- [ ] Response includes rate limit headers:
  - `X-RateLimit-Limit: 30`
  - `X-RateLimit-Remaining: 29` (decrements)
  - `X-RateLimit-Window: 60`
- [ ] Cache headers show `X-Cache: MISS` then `X-Cache: HIT`

#### 3.2 Rate Limit Exceeded
**Steps**:
1. Make 31 rapid requests to availability endpoint within 1 minute
2. Check 31st request response

**Expected Results**:
- [ ] First 30 requests succeed
- [ ] 31st request returns HTTP 429 "Rate limit exceeded"
- [ ] Rate limit resets after 60 seconds

#### 3.3 Cache Behavior
**Steps**:
1. Make request to availability endpoint
2. Make same request within 5 minutes
3. Make same request after 5 minutes

**Expected Results**:
- [ ] First request: `X-Cache: MISS`
- [ ] Second request: `X-Cache: HIT`
- [ ] Third request: `X-Cache: MISS` (cache expired)

### 4. Frontend Voucher Security

**Objective**: Verify frontend voucher redemption security

#### 4.1 Nonce Verification
**Steps**:
1. Add experience product to cart
2. Try to apply voucher via AJAX
3. Use browser developer tools to modify/remove nonce
4. Retry voucher application

**Expected Results**:
- [ ] Valid nonce: voucher application works
- [ ] Invalid/missing nonce: operation blocked with error

#### 4.2 Input Sanitization
**Steps**:
1. Try to apply voucher with malicious input:
   - `<script>alert('xss')</script>`
   - `'; DROP TABLE wp_posts; --`
   - Special characters and Unicode

**Expected Results**:
- [ ] All inputs are properly sanitized
- [ ] No XSS or SQL injection possible
- [ ] Graceful error handling

### 5. Review Data Sanitization

**Objective**: Verify Google Places review data is properly sanitized

#### 5.1 Review Display
**Steps**:
1. Check meeting point with Google Places reviews
2. Verify author names are partially hidden
3. Check review text is properly escaped

**Expected Results**:
- [ ] Author names show as "John D." format
- [ ] Review text is HTML-escaped
- [ ] No script injection possible
- [ ] Character limits are enforced

## Security Checklist Summary

- [ ] **Capability System**: `manage_fp_esperienze` properly assigned to admin/shop manager roles
- [ ] **Admin Menu Protection**: All admin pages require proper capability
- [ ] **Form Security**: All admin forms have nonce protection
- [ ] **AJAX Security**: Frontend voucher actions use nonce verification
- [ ] **Rate Limiting**: REST API has 30 req/min/IP limit with proper headers
- [ ] **Caching**: 5-minute cache on availability endpoint
- [ ] **Input Sanitization**: All user inputs are properly sanitized
- [ ] **Output Escaping**: All outputs are properly escaped
- [ ] **Role Separation**: Editor role cannot access FP Esperienze admin

## Found Issues Template

**Issue**: [Brief description]
**Severity**: [Low/Medium/High/Critical]
**Steps to Reproduce**:
1. 
2. 
3. 

**Expected Behavior**:
**Actual Behavior**:
**User Role**: [Administrator/Shop Manager/Editor/Customer]
**Browser**: 
**Additional Notes**: