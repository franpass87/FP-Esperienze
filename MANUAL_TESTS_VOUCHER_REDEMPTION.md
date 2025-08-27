# Manual Tests - Voucher Redemption Feature

## Test Environment Setup

### Prerequisites
- WordPress with WooCommerce active
- FP Esperienze plugin installed and activated  
- At least one experience product created
- Valid gift vouchers in the database (both 'full' and 'value' types)

### Test Data Required
- Experience product with price (e.g., €50 adult price)
- Valid voucher codes:
  - Full voucher: `TEST-FULL-001` (amount_type: 'full')
  - Value voucher: `TEST-VALUE-025` (amount_type: 'value', amount: 25.00)
- Expired voucher: `TEST-EXPIRED-001`
- Redeemed voucher: `TEST-REDEEMED-001`
- Invalid voucher: `INVALID-CODE-001`

## Frontend Voucher Application Tests

### Test 1: Valid Full Voucher Application
**Steps:**
1. Navigate to an experience product page
2. Select a date and time slot
3. Select 1 adult participant
4. Scroll to voucher section
5. Enter valid full voucher code: `TEST-FULL-001`
6. Click "Apply" button

**Expected Results:**
- [ ] Success message appears: "Voucher applied successfully!"
- [ ] Voucher code input becomes readonly
- [ ] "Apply" button is hidden, "Remove" button is shown
- [ ] Status shows "Voucher applied: Full discount"
- [ ] Product price becomes €0 (extras still charged if any)
- [ ] Total price updates correctly

### Test 2: Valid Value Voucher Application
**Steps:**
1. Navigate to an experience product page (price €50)
2. Select a date and time slot
3. Select 1 adult participant
4. Enter valid value voucher code: `TEST-VALUE-025`
5. Click "Apply" button

**Expected Results:**
- [ ] Success message appears: "Voucher applied successfully!"
- [ ] Status shows "Voucher applied: Up to €25.00"
- [ ] Product price is reduced by €25 (from €50 to €25)
- [ ] Total price updates correctly

### Test 3: Invalid Voucher Code
**Steps:**
1. Navigate to an experience product page
2. Enter invalid voucher code: `INVALID-CODE-001`
3. Click "Apply" button

**Expected Results:**
- [ ] Error message appears: "Invalid voucher code."
- [ ] Input field remains editable
- [ ] No price changes occur

### Test 4: Expired Voucher
**Steps:**
1. Enter expired voucher code: `TEST-EXPIRED-001`
2. Click "Apply" button

**Expected Results:**
- [ ] Error message appears: "This voucher has expired."
- [ ] Voucher status in database updated to 'expired'

### Test 5: Already Redeemed Voucher
**Steps:**
1. Enter redeemed voucher code: `TEST-REDEEMED-001`
2. Click "Apply" button

**Expected Results:**
- [ ] Error message appears: "This voucher has already been used."

### Test 6: Product-Specific Voucher (Wrong Product)
**Steps:**
1. Navigate to Experience Product A
2. Enter voucher code valid only for Experience Product B
3. Click "Apply" button

**Expected Results:**
- [ ] Error message appears: "This voucher is only valid for [Product B Name]."

### Test 7: Voucher Removal
**Steps:**
1. Apply a valid voucher (follow Test 1)
2. Click "Remove" button

**Expected Results:**
- [ ] Success message: "Voucher removed successfully."
- [ ] Input field becomes editable again
- [ ] "Apply" button is shown, "Remove" button is hidden
- [ ] Price returns to original amount
- [ ] Status display is hidden

### Test 8: Empty Voucher Code
**Steps:**
1. Leave voucher code input empty
2. Click "Apply" button

**Expected Results:**
- [ ] Error message appears: "Please enter a voucher code."

## Cart and Checkout Tests

### Test 9: Voucher Persistence in Cart
**Steps:**
1. Apply a valid voucher to an experience
2. Add the experience to cart
3. Navigate to cart page

**Expected Results:**
- [ ] Cart shows applied voucher in item details
- [ ] Discount is properly applied to item price
- [ ] Cart totals reflect the discount

### Test 10: Voucher in Cart Display
**Steps:**
1. Add experience with applied voucher to cart
2. Check cart item metadata

**Expected Results:**
- [ ] Item shows "Voucher Applied: [CODE] (Full discount)" or "(Up to €XX)"
- [ ] Metadata is properly formatted and visible

### Test 11: Checkout with Voucher
**Steps:**
1. Add experience with applied voucher to cart
2. Proceed through checkout
3. Complete the order

**Expected Results:**
- [ ] Order completes successfully
- [ ] Order item metadata includes voucher code
- [ ] Voucher status in database changes to 'redeemed'
- [ ] Order note added: "Voucher [CODE] redeemed for this order"

## Order Processing Tests

### Test 12: Voucher Redemption on Order Completion
**Steps:**
1. Create order with voucher-applied item
2. Change order status to "Completed" in admin

**Expected Results:**
- [ ] Voucher status changes from 'active' to 'redeemed'
- [ ] Order note added about voucher redemption

### Test 13: Voucher Rollback on Order Cancellation
**Steps:**
1. Complete an order with a voucher (status becomes 'redeemed')
2. Change order status to "Cancelled"

**Expected Results:**
- [ ] Voucher status changes from 'redeemed' back to 'active'
- [ ] Order note added about voucher restoration

### Test 14: Voucher Rollback on Order Refund
**Steps:**
1. Complete an order with a voucher (status becomes 'redeemed')
2. Change order status to "Refunded"

**Expected Results:**
- [ ] Voucher status changes from 'redeemed' back to 'active'
- [ ] Order note added about voucher restoration

## Edge Cases and Error Handling

### Test 15: Multiple Cart Items (Only One with Voucher)
**Steps:**
1. Add multiple experience items to cart
2. Apply voucher to only one item
3. Proceed to checkout

**Expected Results:**
- [ ] Only the voucher-applied item has discount
- [ ] Other items maintain original pricing
- [ ] Checkout completes successfully

### Test 16: Cart Changes Invalidating Voucher
**Steps:**
1. Apply product-specific voucher to Experience A
2. Change the experience to Experience B (different product)

**Expected Results:**
- [ ] Voucher is automatically removed
- [ ] Clear message shown about incompatibility
- [ ] Price reverts to original

### Test 17: AJAX Failures
**Steps:**
1. Simulate network error during voucher application
2. Attempt to apply voucher

**Expected Results:**
- [ ] Generic error message: "Something went wrong. Please try again."
- [ ] Button returns to original state
- [ ] No partial state changes

### Test 18: Value Voucher Exceeding Item Price
**Steps:**
1. Apply €25 value voucher to €15 experience
2. Check price calculation

**Expected Results:**
- [ ] Discount applied is only €15 (not exceeding item price)
- [ ] Final price is €0
- [ ] Extras still charged if any

## Mobile Responsiveness Tests

### Test 19: Mobile Interface
**Steps:**
1. Open experience page on mobile device
2. Test voucher application flow

**Expected Results:**
- [ ] Voucher form displays properly on mobile
- [ ] Buttons stack vertically on small screens
- [ ] Text remains readable
- [ ] Touch interactions work correctly

## Browser Compatibility Tests

### Test 20: Cross-Browser Testing
**Test in each browser:**
- Chrome
- Firefox  
- Safari
- Edge

**Expected Results:**
- [ ] Voucher functionality works identically across browsers
- [ ] AJAX requests complete successfully
- [ ] CSS styling renders correctly

## Security Tests

### Test 21: HMAC Signature Verification
**Steps:**
1. Attempt to apply voucher with manipulated QR payload
2. Try to bypass signature verification

**Expected Results:**
- [ ] Invalid signatures are rejected
- [ ] Error message: "Invalid voucher signature."

### Test 22: Nonce Verification
**Steps:**
1. Attempt AJAX request with invalid/expired nonce
2. Check server response

**Expected Results:**
- [ ] Request is rejected
- [ ] Proper error handling

## Performance Tests

### Test 23: Load Testing
**Steps:**
1. Apply and remove vouchers multiple times rapidly
2. Monitor server response times

**Expected Results:**
- [ ] Response times remain acceptable (<2 seconds)
- [ ] No memory leaks or performance degradation

## Test Results Summary

| Test Case | Status | Notes |
|-----------|---------|-------|
| Test 1 - Valid Full Voucher | ⏳ Pending | |
| Test 2 - Valid Value Voucher | ⏳ Pending | |
| Test 3 - Invalid Code | ⏳ Pending | |
| Test 4 - Expired Voucher | ⏳ Pending | |
| Test 5 - Redeemed Voucher | ⏳ Pending | |
| Test 6 - Wrong Product | ⏳ Pending | |
| Test 7 - Voucher Removal | ⏳ Pending | |
| Test 8 - Empty Code | ⏳ Pending | |
| Test 9 - Cart Persistence | ⏳ Pending | |
| Test 10 - Cart Display | ⏳ Pending | |
| Test 11 - Checkout | ⏳ Pending | |
| Test 12 - Order Completion | ⏳ Pending | |
| Test 13 - Order Cancellation | ⏳ Pending | |
| Test 14 - Order Refund | ⏳ Pending | |
| Test 15 - Multiple Items | ⏳ Pending | |
| Test 16 - Cart Changes | ⏳ Pending | |
| Test 17 - AJAX Failures | ⏳ Pending | |
| Test 18 - Value Exceeding Price | ⏳ Pending | |
| Test 19 - Mobile Interface | ⏳ Pending | |
| Test 20 - Cross-Browser | ⏳ Pending | |
| Test 21 - HMAC Security | ⏳ Pending | |
| Test 22 - Nonce Security | ⏳ Pending | |
| Test 23 - Performance | ⏳ Pending | |

## Test Environment Details

- **WordPress Version:** ___________
- **WooCommerce Version:** ___________
- **PHP Version:** ___________
- **Plugin Version:** ___________
- **Test Date:** ___________
- **Tester:** ___________

## Known Issues

_(Document any issues found during testing)_

## Test Completion Checklist

- [ ] All test cases executed
- [ ] Issues documented and reported
- [ ] Screenshots captured for UI tests
- [ ] Performance metrics recorded
- [ ] Security validations confirmed
- [ ] Mobile testing completed
- [ ] Cross-browser testing completed