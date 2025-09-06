# Manual Tests - Brevo Integration

## Test Environment Setup

1. WordPress site with WooCommerce installed and activated
2. FP Esperienze plugin installed and activated
3. Admin user with `manage_woocommerce` capability
4. Access to Brevo account with API key and configured lists
5. At least one experience product configured in WooCommerce

## Preparation

### 1. Configure Brevo Settings

**Steps**:
1. Navigate to **FP Esperienze → Settings → Integrations**
2. Configure Brevo section:
   - **API Key v3**: Enter valid Brevo API key
   - **List ID (Italian)**: Enter valid Italian list ID
   - **List ID (English)**: Enter valid English list ID
3. Save settings

**Expected Results**:
- Settings save successfully
- All fields persist their values after page reload

### 2. Verify Plugin Loading

**Steps**:
1. Upload `test-brevo-integration.php` to WordPress root directory
2. Access the test file via browser (must be in WP_DEBUG mode)
3. Review all test results

**Expected Results**:
- BrevoManager class loads successfully
- Integration is enabled (if settings configured)
- Language detection works
- Order hooks are registered

## Test Cases

### 3. Test Order Processing - Italian Customer

**Objective**: Verify Italian customers are added to Italian list

**Steps**:
1. Create a test order with:
   - Experience product(s)
   - Customer email: `test-it@example.com`
   - Customer name: `Mario Rossi`
   - Language meta: `Italian` (if applicable)
2. Change order status to "Processing"
3. Check WordPress error logs
4. Check Brevo Italian list for new contact

**Expected Results**:
- No errors in WordPress logs
- Contact `test-it@example.com` appears in Italian Brevo list
- Contact has FIRSTNAME: `Mario`, LASTNAME: `Rossi`

### 4. Test Order Processing - English Customer

**Objective**: Verify English customers are added to English list

**Steps**:
1. Create a test order with:
   - Experience product(s)
   - Customer email: `test-en@example.com`
   - Customer name: `John Smith`
   - Language meta: `English` (if applicable)
2. Change order status to "Completed"
3. Check WordPress error logs
4. Check Brevo English list for new contact

**Expected Results**:
- No errors in WordPress logs
- Contact `test-en@example.com` appears in English Brevo list
- Contact has FIRSTNAME: `John`, LASTNAME: `Smith`

### 5. Test Language Detection Fallback

**Objective**: Verify language detection works with site locale

**Steps**:
1. Create order without explicit language meta
2. Set WordPress locale to Italian (`it_IT`)
3. Process order as above
4. Verify contact goes to Italian list
5. Change locale to English (`en_US`)
6. Process another order
7. Verify contact goes to English list

**Expected Results**:
- Language detection falls back to site locale correctly
- Contacts are routed to appropriate lists

### 6. Test Contact Update

**Objective**: Verify existing contacts are updated, not duplicated

**Steps**:
1. Process order with email that already exists in Brevo
2. Use different name than existing contact
3. Check Brevo list for contact updates

**Expected Results**:
- Contact is updated, not duplicated
- Name attributes are refreshed

### 7. Test Orders Without Experience Products

**Objective**: Verify integration only processes experience orders

**Steps**:
1. Create order with only regular WooCommerce products (no experience type)
2. Change order status to "Processing"
3. Check logs and Brevo lists

**Expected Results**:
- No API calls made to Brevo
- No new contacts added to lists

### 8. Test Invalid Email Addresses

**Objective**: Verify handling of invalid customer data

**Steps**:
1. Create order with invalid email address
2. Process order
3. Check error logs

**Expected Results**:
- Integration skips invalid orders gracefully
- Appropriate error logging without exposing customer data

### 9. Test Disabled Integration

**Objective**: Verify integration respects settings

**Steps**:
1. Remove Brevo API key from settings
2. Process valid experience order
3. Check logs and Brevo lists

**Expected Results**:
- No API calls made when integration disabled
- No errors when disabled

### 10. Test API Error Handling

**Objective**: Verify graceful handling of Brevo API errors

**Steps**:
1. Configure invalid API key or list IDs
2. Process valid experience order
3. Check WordPress error logs

**Expected Results**:
- Errors logged without exposing sensitive data
- Plugin continues to function normally
- No PHP fatal errors

### 11. Test Multiple Experience Items

**Objective**: Verify processing orders with multiple experiences

**Steps**:
1. Create order with multiple experience products
2. Mix Italian and English language items if possible
3. Process order

**Expected Results**:
- Customer added to appropriate list based on primary language detection
- Order processes normally

### 12. Test Order Status Transitions

**Objective**: Verify integration triggers on correct status changes

**Steps**:
1. Create pending order
2. Change to "processing" - should trigger integration
3. Create another pending order  
4. Change to "completed" - should trigger integration
5. Create order and change to other statuses ("cancelled", "refunded")

**Expected Results**:
- Integration triggers only on "processing" and "completed" statuses
- Other status changes are ignored

### 13. Test Automation Event Trigger

**Objective**: Verify automation events are sent to Brevo with order data

**Steps**:
1. Create a test order with experience product(s)
2. Change order status to "processing" or "completed"
3. Check WordPress error logs for Brevo API errors
4. Verify `ExperiencePurchase` event appears in Brevo with correct properties

**Expected Results**:
- No errors in WordPress logs
- Event recorded in Brevo with order ID, total, and currency

## Error Scenarios

### 14. Network Connectivity Issues

**Objective**: Test behavior with network problems

**Steps**:
1. Block outbound connections to `api.brevo.com` temporarily
2. Process experience order
3. Check error handling

**Expected Results**:
- Graceful timeout handling
- Appropriate error logging
- No site disruption

### 15. API Rate Limiting

**Objective**: Test behavior with Brevo rate limits

**Steps**:
1. Process multiple orders quickly (if rate limits accessible)
2. Monitor API responses and error logs

**Expected Results**:
- Rate limit errors handled gracefully
- No data loss or corruption

## Performance Testing

### 16. High Volume Processing

**Objective**: Verify performance with multiple orders

**Steps**:
1. Create 10-20 test orders with experience products
2. Bulk change status to "completed"
3. Monitor processing time and resource usage

**Expected Results**:
- All orders process within reasonable time
- No memory leaks or timeouts
- All contacts successfully added to Brevo

## Security Testing

### 17. Log Security

**Objective**: Verify no sensitive data in logs

**Steps**:
1. Process orders with various customer data
2. Review all WordPress error logs
3. Check for exposed API keys, emails, or names

**Expected Results**:
- No API keys in logs
- No customer email addresses in logs  
- No customer names in logs
- Only generic error messages and codes

## Cleanup

### 18. Test Data Cleanup

**Steps**:
1. Remove all test contacts from Brevo lists
2. Delete test orders from WooCommerce
3. Remove `test-brevo-integration.php` file
4. Reset Brevo settings if using test credentials

## Test Results Template

```
Test Case: [Number and Title]
Date: [Date]
Tester: [Name]
Environment: [WordPress/WooCommerce/Plugin versions]

Result: [PASS/FAIL/PARTIAL]
Notes: [Any observations or issues]
Screenshots: [If applicable]
```

## Browser Compatibility

Test the admin settings interface in:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Mobile Testing

Test settings interface on mobile devices to ensure:
- Form fields are accessible
- Save functionality works
- Responsive layout maintains usability