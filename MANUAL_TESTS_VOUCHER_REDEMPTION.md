# Manual Test Cases for Voucher Redemption Feature

This document provides step-by-step manual test cases to verify the voucher redemption functionality.

## Prerequisites

Before testing, ensure you have:
1. WordPress with WooCommerce installed and activated
2. FP Esperienze plugin activated
3. At least one experience product created with schedules
4. Test voucher data in the `fp_exp_vouchers` table

## Test Data Setup

Insert test vouchers into the database:

```sql
INSERT INTO wp_fp_exp_vouchers (voucher_code, voucher_type, amount, product_id, status, expires_on, signature) VALUES
('FULL50', 'full', 0.00, NULL, 'active', '2025-12-31', 'test_signature_full'),
('VALUE25', 'value', 25.00, NULL, 'active', '2025-12-31', 'test_signature_value'),
('EXPIRED01', 'value', 10.00, NULL, 'active', '2023-01-01', 'test_signature_expired'),
('PRODUCT123', 'value', 15.00, 123, 'active', '2025-12-31', 'test_signature_product'),
('REDEEMED01', 'value', 20.00, NULL, 'redeemed', '2025-12-31', 'test_signature_redeemed');
```

*Note: In production, the signature would be a valid HMAC hash.*

---

## Test Case 1: Valid Full Voucher Application

### Purpose
Verify that a valid full voucher makes the experience product free while keeping extras chargeable.

### Steps to Test

1. **Navigate to Experience Product**:
   - Go to a single experience product page
   - Select a date and time slot
   - Add participants (e.g., 2 adults at €50 each = €100 base)
   - Add extras if available (e.g., €10 photo package)

2. **Apply Full Voucher**:
   - In the "Have a voucher?" section, enter: `FULL50`
   - Click "Apply"
   - Verify success message: "Voucher applied successfully!"

3. **Verify Price Calculation**:
   - Base experience price should become €0
   - Extras should remain at full price (€10)
   - Total should be €10 (extras only)
   - Price breakdown should show: "Voucher (FULL50): -€100.00"

4. **Add to Cart**:
   - Click "Add to Cart"
   - Verify cart item shows voucher applied
   - Check cart totals are correct

### Expected Results
- ✅ Voucher applies successfully
- ✅ Base experience price becomes €0
- ✅ Extras remain chargeable
- ✅ Voucher information is displayed in cart
- ✅ Total calculation is correct

---

## Test Case 2: Valid Value Voucher Application

### Purpose
Verify that a value voucher applies the correct discount amount.

### Steps to Test

1. **Setup Product Selection**:
   - Navigate to experience product page
   - Select date, time, and participants (e.g., €100 base price)
   - Add extras (e.g., €15 total)

2. **Apply Value Voucher**:
   - Enter voucher code: `VALUE25`
   - Click "Apply"
   - Verify success message appears

3. **Verify Discount Application**:
   - Base price should be reduced by €25 (€100 → €75)
   - Extras should remain unchanged (€15)
   - Total should be €90 (€75 + €15)
   - Price breakdown should show: "Voucher (VALUE25): -€25.00"

4. **Test with Lower Base Price**:
   - Reduce participants to make base price €20
   - Voucher discount should cap at €20 (not exceed base price)
   - Total should be €0 + €15 = €15

### Expected Results
- ✅ Voucher applies correct discount amount
- ✅ Discount doesn't exceed base product price
- ✅ Extras remain unaffected
- ✅ Price calculations are accurate

---

## Test Case 3: Invalid Voucher Scenarios

### Purpose
Test various invalid voucher scenarios and error handling.

### Steps to Test

1. **Empty Voucher Code**:
   - Leave voucher field empty
   - Click "Apply"
   - Expected: "Please enter a valid voucher code."

2. **Non-existent Voucher**:
   - Enter: `INVALID123`
   - Click "Apply"
   - Expected: "Invalid voucher code or voucher not applicable to this product."

3. **Expired Voucher**:
   - Enter: `EXPIRED01`
   - Click "Apply"
   - Expected: "Invalid voucher code or voucher not applicable to this product."

4. **Already Redeemed Voucher**:
   - Enter: `REDEEMED01`
   - Click "Apply"
   - Expected: "Invalid voucher code or voucher not applicable to this product."

5. **Product-Specific Voucher on Wrong Product**:
   - On a product other than ID 123, enter: `PRODUCT123`
   - Click "Apply"
   - Expected: "Invalid voucher code or voucher not applicable to this product."

### Expected Results
- ✅ Appropriate error messages for each scenario
- ✅ No price changes occur
- ✅ Error messages auto-hide after 5 seconds
- ✅ Form remains functional after errors

---

## Test Case 4: Cart Page Voucher Application

### Purpose
Verify voucher application works on the cart page for experience products.

### Steps to Test

1. **Add Experience to Cart**:
   - Add an experience product to cart (without voucher)
   - Navigate to cart page

2. **Apply Voucher in Cart**:
   - Locate "Have a voucher?" section
   - Enter valid voucher code: `VALUE25`
   - Click "Apply Voucher"

3. **Verify Cart Update**:
   - Page should reload with updated totals
   - Cart item should show voucher information
   - Cart totals should reflect discount

### Expected Results
- ✅ Voucher section appears on cart page with experience products
- ✅ AJAX application works correctly
- ✅ Cart updates with correct pricing
- ✅ Voucher info is visible in cart

---

## Test Case 5: Order Processing and Redemption

### Purpose
Test voucher redemption during order completion and restoration during cancellation.

### Steps to Test

1. **Complete Order with Voucher**:
   - Add experience with applied voucher to cart
   - Proceed through checkout
   - Complete payment (use test payment method)
   - Order status should be "Processing" or "Completed"

2. **Verify Voucher Redemption**:
   - Check order notes for voucher redemption message
   - In database, verify voucher status changed to 'redeemed'
   - Verify `order_id` and `redeemed_at` fields are populated

3. **Test Order Cancellation**:
   - Change order status to "Cancelled"
   - Check order notes for voucher restoration message
   - In database, verify voucher status returned to 'active'
   - Verify `order_id` and `redeemed_at` fields are cleared

4. **Test Order Refund**:
   - Complete another order with voucher
   - Process a full refund
   - Verify voucher is restored to active status

### Expected Results
- ✅ Voucher is marked as redeemed on order completion
- ✅ Order notes document voucher redemption
- ✅ Voucher is restored to active on cancellation/refund
- ✅ Database updates are correct

---

## Test Case 6: Voucher Removal

### Purpose
Test removing an applied voucher before adding to cart.

### Steps to Test

1. **Apply Voucher**:
   - On experience product page, apply a valid voucher
   - Verify it's applied and prices updated

2. **Remove Voucher**:
   - Click the "Remove" button (should appear after applying)
   - Verify voucher removal message
   - Verify prices return to original amounts

3. **Re-apply Different Voucher**:
   - Apply a different voucher code
   - Verify it works correctly

### Expected Results
- ✅ Voucher can be removed successfully
- ✅ Prices revert to original amounts
- ✅ Form allows applying different voucher
- ✅ No session data persists after removal

---

## Test Case 7: Mobile Responsiveness

### Purpose
Verify voucher functionality works on mobile devices.

### Steps to Test

1. **Test on Mobile Device or Browser Developer Tools**:
   - Resize browser to mobile width (< 768px)
   - Navigate to experience product page

2. **Test Voucher UI**:
   - Verify voucher input field is accessible
   - Test voucher application process
   - Verify success/error messages are readable

3. **Test Cart Page**:
   - Navigate to cart page on mobile
   - Test voucher application in cart

### Expected Results
- ✅ Voucher UI is responsive and usable on mobile
- ✅ Input fields and buttons are appropriately sized
- ✅ Messages are readable on small screens
- ✅ All functionality works on touch devices

---

## Troubleshooting

### Common Issues

1. **AJAX Errors**: Check browser console for JavaScript errors and verify nonce values
2. **Database Errors**: Ensure `fp_exp_vouchers` table exists and has correct structure
3. **Signature Validation**: In testing, you may need to disable signature validation temporarily
4. **Session Issues**: Clear browser cache and cookies if voucher state persists incorrectly

### Debug Information

To debug voucher issues:
1. Enable WordPress debug logging
2. Check PHP error logs for VoucherManager errors
3. Inspect AJAX responses in browser developer tools
4. Verify database queries in MySQL logs

---

## Performance Notes

- Voucher validation queries are simple SELECT statements
- AJAX requests are lightweight and fast
- Session storage is used temporarily for cart integration
- Database updates only occur on order completion/cancellation

## Security Testing

Verify that:
- Nonce validation prevents CSRF attacks
- Input sanitization prevents XSS
- HMAC signature verification works (in production)
- Unauthorized users cannot manipulate voucher data