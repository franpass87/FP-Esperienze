# Manual Tests - Gift Voucher Feature

## Test Environment Setup
- [ ] WordPress 6.5+ with WooCommerce 8.0+
- [ ] FP Esperienze plugin activated
- [ ] At least one experience product created
- [ ] Email delivery configured and tested

## Frontend Gift Form Tests

### Test 1: Gift Toggle Display
- [ ] Navigate to an experience product page
- [ ] Verify "Gift this experience" toggle is visible above the "Total Price" section
- [ ] Toggle should be OFF by default
- [ ] Gift form should be hidden initially

### Test 2: Gift Form Reveal
- [ ] Click the gift toggle to ON position
- [ ] Gift form should slide down with animation
- [ ] Form should contain all required fields:
  - [ ] Your name (optional)
  - [ ] Recipient name (required with asterisk)
  - [ ] Recipient email (required with asterisk)
  - [ ] Personal message (optional)
  - [ ] Send date (optional with help text)

### Test 3: Gift Form Validation
- [ ] Toggle gift form ON
- [ ] Try to add to cart without filling required fields
- [ ] Should show validation error for missing recipient name
- [ ] Should show validation error for missing recipient email
- [ ] Enter invalid email format - should show validation error
- [ ] Fill all required fields - Add to Cart button should become enabled

### Test 4: Gift Form Submission
- [ ] Fill out complete gift form with valid data
- [ ] Add item to cart
- [ ] In cart, verify gift information is displayed:
  - [ ] "Gift Purchase: Yes"
  - [ ] "Recipient: [name]"
  - [ ] "From: [sender]" (if provided)
  - [ ] "Send Date: [date]" (if not immediate)

## Cart and Checkout Tests

### Test 5: Cart Display
- [ ] Add gift item to cart
- [ ] Verify all gift metadata is visible in cart
- [ ] Proceed to checkout
- [ ] Complete checkout successfully

### Test 6: Order Processing
- [ ] Complete an order with gift item
- [ ] Check order details in admin:
  - [ ] Gift metadata should be saved in order item meta
  - [ ] Order should complete normally

## Voucher Generation Tests

### Test 7: Automatic Voucher Creation
- [ ] Complete a gift order
- [ ] Mark order as "Completed" in admin
- [ ] Check WP Cron to see scheduled email (if future send date)
- [ ] Check database for voucher record in `wp_fp_exp_vouchers` table
- [ ] Verify voucher code is unique and 10-12 characters

### Test 8: PDF Generation
- [ ] Verify PDF file is created in `/wp-content/uploads/fp-vouchers/`
- [ ] Download and open PDF to verify:
  - [ ] Site logo displays (if configured)
  - [ ] Voucher code is prominently displayed
  - [ ] Product name is correct
  - [ ] Recipient name matches
  - [ ] Value shows "Prepaid Ticket" or amount
  - [ ] Expiration date is correct (default 12 months)
  - [ ] QR code is visible and scannable
  - [ ] Personal message included (if provided)
  - [ ] Terms and conditions at bottom

### Test 9: QR Code Verification
- [ ] Scan QR code from PDF
- [ ] Verify payload format: `FPX|VC:<code>|PID:<product_id>|TYPE:<amount_type>|AMT:<amount>|EXP:<YYYY-MM-DD>|SIG:<hmac>`
- [ ] Test signature verification function
- [ ] Verify corrupted payload is rejected

## Email Delivery Tests

### Test 10: Immediate Email Delivery
- [ ] Create gift with "immediate" send date
- [ ] Complete order
- [ ] Verify recipient receives email with:
  - [ ] Proper subject line
  - [ ] Personal message (if provided)
  - [ ] Voucher details
  - [ ] PDF attachment
- [ ] Verify buyer receives confirmation email

### Test 11: Scheduled Email Delivery
- [ ] Create gift with future send date
- [ ] Complete order
- [ ] Verify email is scheduled (not sent immediately)
- [ ] Check WordPress cron events
- [ ] Manually trigger scheduled event or wait for send date
- [ ] Verify email is sent on scheduled date

## Admin Interface Tests

### Test 12: Settings Page
- [ ] Navigate to FP Esperienze → Settings
- [ ] Test all gift voucher settings:
  - [ ] Default expiration months (numeric input)
  - [ ] PDF logo upload (media selector)
  - [ ] Brand color picker
  - [ ] Email sender name and address
  - [ ] Terms and conditions textarea
  - [ ] HMAC secret regeneration
- [ ] Save settings and verify they persist

### Test 13: Vouchers Management Page
- [ ] Navigate to FP Esperienze → Vouchers
- [ ] Verify voucher list displays:
  - [ ] Voucher code
  - [ ] Product name
  - [ ] Recipient details
  - [ ] Value and status
  - [ ] Expiration date
  - [ ] Created date
- [ ] Test filtering by status
- [ ] Test search functionality
- [ ] Test PDF download
- [ ] Test void voucher action

## Edge Cases and Error Handling

### Test 14: Error Conditions
- [ ] Test with invalid product ID
- [ ] Test with missing PDF dependencies
- [ ] Test with invalid email addresses
- [ ] Test with malformed QR code payload
- [ ] Test PDF generation failure
- [ ] Test email delivery failure

### Test 15: Security Tests
- [ ] Verify CSRF protection on all forms
- [ ] Test XSS protection in form inputs
- [ ] Verify SQL injection protection
- [ ] Test unauthorized access to admin pages
- [ ] Verify HMAC signature prevents tampering

### Test 16: Performance Tests
- [ ] Test with large number of vouchers
- [ ] Test PDF generation time
- [ ] Test email queue processing
- [ ] Test database query performance

## Browser and Device Tests

### Test 17: Cross-browser Compatibility
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### Test 18: Mobile Responsiveness
- [ ] Gift form display on mobile
- [ ] Form interaction on touch devices
- [ ] PDF viewing on mobile
- [ ] Email rendering on mobile clients

## Cleanup and Documentation

### Test 19: Documentation Verification
- [ ] README updated with gift feature documentation
- [ ] Settings documented
- [ ] API endpoints documented (if any)
- [ ] Translation strings identified

### Test 20: Final Verification
- [ ] All test cases pass
- [ ] No PHP errors in logs
- [ ] No JavaScript console errors
- [ ] Performance is acceptable
- [ ] User experience is intuitive

## Test Environment Details
- **WordPress Version**: ___________
- **WooCommerce Version**: ___________
- **PHP Version**: ___________
- **Test Date**: ___________
- **Tested By**: ___________

## Test Results Summary
- **Total Tests**: 20
- **Passed**: ___________
- **Failed**: ___________
- **Notes**: ___________