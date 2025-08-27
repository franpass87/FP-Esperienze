# Manual Tests - Integrations Settings

## Test Environment Setup

1. WordPress site with WooCommerce installed and activated
2. FP Esperienze plugin installed and activated
3. Admin user with `manage_woocommerce` capability
4. Access to WordPress admin dashboard

## Test Cases

### 1. Access Control

**Objective**: Verify proper access control for integrations settings

**Steps**:
1. Log in as admin user
2. Navigate to **FP Esperienze → Settings**
3. Verify page loads successfully
4. Click on "Integrations" tab
5. Log out and log in as subscriber/customer role
6. Try to access `admin.php?page=fp-esperienze-settings&tab=integrations`

**Expected Results**:
- Admin can access the integrations settings page
- Non-admin users cannot access the page
- Proper error message shown for unauthorized access
- Integrations tab is visible and functional for admins

### 2. Tab Navigation

**Objective**: Test tab switching functionality

**Steps**:
1. Navigate to **FP Esperienze → Settings**
2. Verify "Gift Vouchers" tab is active by default
3. Click on "Integrations" tab
4. Verify URL changes to include `&tab=integrations`
5. Click on "Gift Vouchers" tab
6. Verify URL changes to `&tab=gift`
7. Refresh page on each tab
8. Direct navigate to URL with `&tab=integrations`

**Expected Results**:
- Tab navigation works smoothly
- URL parameters preserved correctly
- Page refreshes maintain correct tab state
- Direct URL navigation works

### 3. Form Structure and Layout

**Objective**: Verify integrations form displays correctly

**Steps**:
1. Navigate to **FP Esperienze → Settings → Integrations**
2. Verify all sections are present:
   - Google Analytics 4
   - Google Ads
   - Meta Pixel (Facebook)
   - Brevo (Email Marketing)
   - Google Places API
   - Google Business Profile API (Optional)
3. Check field labels and descriptions
4. Verify field types (text, number, checkbox, password)
5. Test responsive layout on different screen sizes

**Expected Results**:
- All sections display with proper headings
- Fields have appropriate labels and help text
- Form is well-organized and readable
- Responsive design works on mobile/tablet
- Field types are appropriate for data expected

### 4. Google Analytics 4 Settings

**Objective**: Test GA4 integration fields

**Steps**:
1. Navigate to Integrations tab
2. Test GA4 Measurement ID field:
   - Enter valid format: "G-ABC123DEF4"
   - Enter invalid format: "ABC123"
   - Leave empty
3. Test Enhanced eCommerce checkbox:
   - Check the box
   - Uncheck the box
4. Save settings
5. Reload page and verify values persist

**Expected Results**:
- Measurement ID accepts valid G-XXXX format
- Checkbox states save correctly
- Settings persist after save/reload
- Help text is clear and informative

### 5. Google Ads Settings

**Objective**: Test Google Ads integration fields

**Steps**:
1. Test Conversion ID field:
   - Enter valid format: "AW-123456789"
   - Enter invalid format: "123456"
   - Leave empty
2. Save settings
3. Verify values persist after reload

**Expected Results**:
- Conversion ID field accepts AW-XXXX format
- Settings save and persist correctly
- Help text explains configuration requirements

### 6. Meta Pixel Settings

**Objective**: Test Meta Pixel integration fields

**Steps**:
1. Test Pixel ID field:
   - Enter numeric ID: "123456789012345"
   - Enter non-numeric value
   - Leave empty
2. Test Conversions API checkbox
3. Save settings and verify persistence

**Expected Results**:
- Pixel ID accepts numeric values
- CAPI checkbox works (placeholder feature)
- Settings persist correctly
- Future implementation note is clear

### 7. Brevo Settings

**Objective**: Test Brevo email marketing fields

**Steps**:
1. Test API Key field:
   - Enter sample API key
   - Verify field type is password (hidden input)
   - Leave empty
2. Test List ID fields (Italian/English):
   - Enter numeric values
   - Enter non-numeric values
   - Test with 0 value
   - Leave empty
3. Save settings and verify

**Expected Results**:
- API key field is password type for security
- List ID fields accept only numeric values
- Zero values are handled correctly
- Settings persist after save

### 8. Google Places API Settings

**Objective**: Test Google Places integration fields

**Steps**:
1. Test API Key field:
   - Enter sample API key
   - Leave empty
2. Test Display Reviews checkbox
3. Test Reviews Limit field:
   - Enter value within range (1-10)
   - Try values outside range (0, 15)
   - Enter non-numeric value
4. Test Cache TTL field:
   - Enter value within range (5-1440)
   - Try values outside range (1, 2000)
5. Save settings

**Expected Results**:
- API key field accepts text input
- Reviews limit enforces 1-10 range
- Cache TTL enforces 5-1440 minute range
- Out-of-range values are corrected automatically
- Settings save correctly

### 9. Form Validation and Sanitization

**Objective**: Test input validation and sanitization

**Steps**:
1. Enter various invalid inputs:
   - HTML tags in text fields
   - JavaScript code in fields
   - SQL injection attempts
   - Very long strings
2. Test with special characters
3. Save settings and verify sanitization
4. Check database values directly

**Expected Results**:
- HTML tags are stripped/escaped
- Malicious code is sanitized
- Input lengths are reasonable
- Database contains clean, safe values
- No XSS vulnerabilities

### 10. Settings Storage and Retrieval

**Objective**: Verify settings are stored correctly

**Steps**:
1. Fill out all integration fields with test values
2. Save settings
3. Navigate away from page
4. Return to integrations settings
5. Verify all values are preserved
6. Check WordPress options table for `fp_esperienze_integrations`

**Expected Results**:
- All settings persist correctly
- Single option array contains all integrations data
- No data loss during save/reload cycle
- Database structure is clean and organized

### 11. Error Handling

**Objective**: Test error scenarios

**Steps**:
1. Submit form without nonce (direct POST)
2. Submit with invalid nonce
3. Test with user lacking permissions
4. Test with corrupted form data
5. Test with extremely large input values

**Expected Results**:
- Proper security checks prevent unauthorized access
- Invalid nonces are rejected
- Permission checks work correctly
- Graceful error handling for malformed data
- Appropriate error messages displayed

### 12. Mixed Tab Testing

**Objective**: Test switching between Gift and Integrations tabs

**Steps**:
1. Fill out gift voucher settings
2. Switch to integrations tab without saving
3. Fill out integration settings
4. Save integrations settings
5. Switch back to gift tab
6. Verify gift settings are still there
7. Save gift settings
8. Verify both sets persist independently

**Expected Results**:
- Tab switching doesn't lose unsaved data warning
- Settings for each tab save independently
- No cross-contamination between tab data
- Both settings types can coexist

## Test Results Template

```
Test: [Test Name]
Date: [Test Date]
Tester: [Tester Name]
Environment: [WordPress Version] + [WooCommerce Version]

Results:
- [ ] PASS / [ ] FAIL / [ ] PARTIAL

Issues Found:
[List any issues discovered]

Notes:
[Additional observations]
```

## Performance Notes

- Settings page should load within 2 seconds
- Form submission should complete within 1 second
- No JavaScript console errors
- No PHP warnings or notices
- Database queries should be minimal (< 10 per page load)

## Browser Compatibility

Test on:
- Chrome (latest)
- Firefox (latest)
- Safari (latest) 
- Edge (latest)
- Mobile browsers (iOS Safari, Android Chrome)

## Accessibility Testing

- Keyboard navigation works for all form elements
- Screen reader compatible labels and descriptions
- Proper color contrast for all text
- Focus indicators visible
- Form structure is logical and accessible