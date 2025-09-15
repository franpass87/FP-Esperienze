# Manual Tests - Dynamic Pricing

## Test Environment Setup

1. Install the FP Esperienze plugin with dynamic pricing feature
2. Ensure WooCommerce is active and configured
3. Create at least one Experience product
4. Database should have `fp_dynamic_pricing_rules` table

## Test Case 1: Dynamic Pricing Tab Access

**Objective**: Verify the Dynamic Pricing tab is accessible in product admin

**Steps**:
1. Log in to WordPress admin
2. Go to Products → All Products
3. Edit an existing Experience product (or create new one)
4. Verify product type is set to "Experience"
5. Check product data tabs at the bottom

**Expected Results**:
- [ ] "Dynamic Pricing" tab is visible alongside other tabs
- [ ] Tab is only shown for Experience products
- [ ] Clicking tab shows pricing rules interface

## Test Case 2: Create Seasonal Pricing Rule

**Objective**: Test seasonal pricing rule creation and saving

**Steps**:
1. Go to Dynamic Pricing tab in Experience product
2. Click "Add Pricing Rule"
3. Fill in seasonal rule:
   - Rule Name: "Summer Season"
   - Type: "Seasonal"
   - Date Start: Next month's start date
   - Date End: Three months from now
   - Adjustment Type: "Percentage (%)"
   - Adult Adjustment: 20
   - Child Adjustment: 15
   - Active: Checked
4. Save product

**Expected Results**:
- [ ] Rule form appears with all required fields
- [ ] Date fields are properly formatted
- [ ] Rule saves without errors
- [ ] Rule appears in list after save

## Test Case 3: Create Weekend Pricing Rule

**Objective**: Test weekend/weekday pricing rule

**Steps**:
1. Add another pricing rule
2. Fill in weekend rule:
   - Rule Name: "Weekend Premium"
   - Type: "Weekend/Weekday"
   - Applies To: "Weekend"
   - Adjustment Type: "Percentage (%)"
   - Adult Adjustment: 10
   - Child Adjustment: 10
3. Save product

**Expected Results**:
- [ ] "Applies To" field appears when Weekend/Weekday is selected
- [ ] Rule saves successfully
- [ ] Multiple rules can coexist

## Test Case 4: Create Early Bird Rule

**Objective**: Test early bird discount functionality

**Steps**:
1. Add early bird rule:
   - Rule Name: "Early Bird Discount"
   - Type: "Early Bird"
   - Days Before: 7
   - Adjustment Type: "Percentage (%)"
   - Adult Adjustment: -15
   - Child Adjustment: -10
2. Save product

**Expected Results**:
- [ ] "Days Before" field appears for Early Bird type
- [ ] Negative adjustments are accepted
- [ ] Rule saves correctly

## Test Case 5: Create Group Discount Rule

**Objective**: Test group discount based on party size

**Steps**:
1. Add group discount rule:
   - Rule Name: "Group Discount 4+"
   - Type: "Group Discount"
   - Min Participants: 4
   - Adjustment Type: "Percentage (%)"
   - Adult Adjustment: -5
   - Child Adjustment: -5
2. Save product

**Expected Results**:
- [ ] "Min Participants" field appears for Group type
- [ ] Rule saves without issues

## Test Case 6: Pricing Preview Calculator

**Objective**: Test the preview pricing functionality

**Steps**:
1. In Dynamic Pricing tab, locate "Pricing Preview" section
2. Set test values:
   - Booking Date: Date within seasonal range and on weekend
   - Purchase Date: 10 days before booking date
   - Adults: 4
   - Children: 2
3. Click "Calculate"

**Expected Results**:
- [ ] Preview calculator is visible
- [ ] All input fields are functional
- [ ] Calculate button triggers AJAX request
- [ ] Results show price breakdown with applied rules
- [ ] Base prices vs final prices are displayed
- [ ] Applied rules are listed with their effects

## Test Case 7: Frontend Cart Integration

**Objective**: Test dynamic pricing in cart display

**Prerequisites**: Complete Test Cases 2-5 first

**Steps**:
1. Go to frontend experience product page
2. Select booking date that matches seasonal and weekend rules
3. Set adults: 4, children: 2 (to trigger group discount)
4. Add to cart
5. View cart page

**Expected Results**:
- [ ] Cart item shows "Dynamic Pricing" section
- [ ] Applied rules are listed with their adjustments
- [ ] Price breakdown is clear and readable
- [ ] Final price reflects all rule applications

## Test Case 8: Rule Priority Testing

**Objective**: Verify rules are applied in correct order

**Steps**:
1. Using preview calculator, test scenario with all rule types active
2. Verify the order: Base → Seasonal → Weekend → Early Bird → Group
3. Check manual calculation matches system calculation

**Expected Results**:
- [ ] Rules apply in documented priority order
- [ ] Compound effects work correctly
- [ ] Final price calculation is accurate

## Test Case 9: Rule Management

**Objective**: Test editing and deleting rules

**Steps**:
1. Edit an existing rule (change adjustment amount)
2. Save product
3. Delete a rule using "Remove" button
4. Save product
5. Add back the deleted rule

**Expected Results**:
- [ ] Rules can be edited and changes persist
- [ ] Rules can be deleted successfully
- [ ] Rule management doesn't affect other rules
- [ ] Interface remains functional after operations

## Test Case 10: Compatibility with Taxes and Coupons

**Objective**: Ensure dynamic pricing works with WooCommerce features

**Steps**:
1. Set up tax class for experience product
2. Apply dynamic pricing rules
3. Add product to cart
4. Apply WooCommerce coupon
5. Proceed to checkout

**Expected Results**:
- [ ] Dynamic pricing applies before tax calculation
- [ ] Taxes are calculated on final dynamic price
- [ ] WooCommerce coupons work alongside dynamic pricing
- [ ] No double discounts occur
- [ ] Total calculations are accurate

## Test Case 11: Voucher Compatibility

**Objective**: Test dynamic pricing with gift vouchers

**Steps**:
1. Create a gift voucher for the experience
2. Add experience to cart with dynamic pricing applied
3. Apply gift voucher
4. Check price calculations

**Expected Results**:
- [ ] Dynamic pricing applies first
- [ ] Voucher discount applies to dynamically priced amount
- [ ] No conflicts between pricing systems
- [ ] Final amount is correct

## Test Case 12: Free Product Handling

**Objective**: Ensure no PHP errors occur with free Experience products.

**Steps**:
1. Create an Experience product with regular price set to `0`.
2. View the product page on the frontend.
3. Add the product to the cart.

**Expected Results**:
- [ ] No PHP warnings or errors are logged.
- [ ] Product price remains `0` during all steps.
- [ ] `_price_adjustment` meta is recorded as `0%`.

## Validation Checklist

After completing all tests:

- [ ] All rule types can be created and saved
- [ ] Preview calculator works accurately
- [ ] Frontend cart shows pricing breakdown
- [ ] Rule priority is enforced correctly
- [ ] No conflicts with WooCommerce taxes/coupons
- [ ] No conflicts with gift voucher system
- [ ] Database tables are created properly
- [ ] No PHP errors in debug log
- [ ] User interface is intuitive and responsive

## Performance Notes

- [ ] Page load times remain acceptable with multiple rules
- [ ] AJAX calls respond promptly
- [ ] No significant impact on cart calculation speed
- [ ] Database queries are optimized

## Browser Compatibility

Test in:
- [ ] Chrome (latest)
- [ ] Firefox (latest) 
- [ ] Safari (latest)
- [ ] Edge (latest)

## Mobile Responsiveness

- [ ] Dynamic Pricing tab works on mobile admin
- [ ] Preview calculator is usable on mobile
- [ ] Frontend cart breakdown displays properly on mobile