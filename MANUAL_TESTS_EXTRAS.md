# Manual Test Guide - Extras Functionality

## Pre-requisites
- WordPress site with WooCommerce active
- FP Esperienze plugin installed and activated
- At least one Experience product created
- Admin access to WordPress dashboard

## Test 1: Admin Extras Management

### 1.1 Create Per-Person Extra
1. Navigate to **WP Admin → FP Esperienze → Extras**
2. Fill in the "Add New Extra" form:
   - **Name**: "Professional Photography"
   - **Description**: "High-quality photos of your experience"
   - **Price**: 25.00
   - **Billing Type**: "Per Person"
   - **Tax Class**: "Standard"
   - **Max Quantity**: 2
   - **Required**: Unchecked
   - **Active**: Checked
3. Click **Add Extra**
4. Verify success message appears
5. Verify the extra appears in the list with correct details

**Expected Result**: Extra is created and displayed correctly with "Per Person" billing type.

### 1.2 Create Per-Booking Extra
1. In the same page, create another extra:
   - **Name**: "Private Transfer"
   - **Description**: "Hotel pickup and drop-off service"
   - **Price**: 50.00
   - **Billing Type**: "Per Booking"
   - **Tax Class**: "Standard"
   - **Max Quantity**: 1
   - **Required**: Unchecked
   - **Active**: Checked
2. Click **Add Extra**
3. Verify the extra appears with "Per Booking" billing type

**Expected Result**: Second extra is created with per-booking billing.

### 1.3 Edit Extra
1. Click **Edit** button on the "Professional Photography" extra
2. Modify the price to 30.00
3. Submit the changes
4. Verify success message and updated price in the list

**Expected Result**: Extra is updated successfully.

### 1.4 Create Required Extra
1. Create a third extra:
   - **Name**: "Insurance"
   - **Description**: "Mandatory travel insurance"
   - **Price**: 10.00
   - **Billing Type**: "Per Person"
   - **Required**: Checked
   - **Active**: Checked
2. Verify it's created as required

**Expected Result**: Required extra is created.

## Test 2: Product Association

### 2.1 Associate Extras with Experience Product
1. Navigate to **Products → All Products**
2. Edit an existing Experience product (or create one)
3. Go to the **Experience** tab
4. Scroll to the **Extras** section
5. Check the boxes for:
   - Professional Photography
   - Private Transfer
   - Insurance
6. **Update** the product

**Expected Result**: Extras are associated with the product.

### 2.2 Verify Association Persistence
1. Refresh the product edit page
2. Verify the extras checkboxes remain checked

**Expected Result**: Associations are saved correctly.

## Test 3: Frontend Display and Selection

### 3.1 View Product Frontend
1. Visit the Experience product page on the frontend
2. Verify the booking form includes an "Add Extras" section
3. Verify all associated extras are displayed with:
   - Name and description
   - Price and billing type ("per person" or "per booking")
   - Checkbox for optional extras
   - Quantity controls where applicable

**Expected Result**: Extras section is visible with correct information.

### 3.2 Test Required Extra Behavior
1. Verify the "Insurance" extra:
   - Has no checkbox (because it's required)
   - Quantity is set to 1 and cannot be reduced to 0
   - Is included in price calculation automatically

**Expected Result**: Required extra behaves correctly.

## Test 4: Price Calculation

### 4.1 Test Per-Person Extra Calculation
1. Set participants: 2 Adults, 1 Child
2. Select "Professional Photography" (per-person extra)
3. Set quantity to 1
4. Verify price breakdown shows:
   - Base price for adults and children
   - Photography: 1 × 3 people = €90.00 (30 × 1 × 3)
   - Correct total

**Expected Result**: Per-person extra is multiplied by total participants.

### 4.2 Test Per-Booking Extra Calculation
1. Keep same participants (2 Adults, 1 Child)
2. Select "Private Transfer" (per-booking extra)
3. Verify price breakdown shows:
   - Transfer: €50.00 (not multiplied by participants)
   - Correct total

**Expected Result**: Per-booking extra is not multiplied by participants.

### 4.3 Test Multiple Quantities
1. Set "Professional Photography" quantity to 2
2. Verify calculation: 2 × 3 people = €180.00 (30 × 2 × 3)

**Expected Result**: Multiple quantities calculate correctly.

### 4.4 Test Required Extra Auto-Calculation
1. Verify "Insurance" is automatically included:
   - Insurance: 1 × 3 people = €30.00 (10 × 1 × 3)

**Expected Result**: Required extras are included automatically.

## Test 5: Cart Functionality

### 5.1 Add to Cart with Extras
1. Configure booking with:
   - Date and time slot
   - 2 Adults, 1 Child
   - Professional Photography (qty: 1)
   - Private Transfer (qty: 1)
   - Insurance (automatically included)
2. Click **Add to Cart**

**Expected Result**: Product is added to cart successfully.

### 5.2 Verify Cart Display
1. Navigate to cart page
2. Verify the experience item shows:
   - Participant quantities
   - Each selected extra with quantities
   - Correct total price

**Expected Result**: Cart displays all booking details including extras.

### 5.3 Test Cart Total Calculation
1. Verify cart total matches the frontend price calculation
2. Calculate manually:
   - Base experience price
   - + Photography (per-person)
   - + Transfer (per-booking)
   - + Insurance (per-person, required)
   - = Total

**Expected Result**: Cart total is correct.

## Test 6: Checkout and Order

### 6.1 Complete Checkout
1. Proceed through checkout process
2. Complete the order

**Expected Result**: Order is created successfully.

### 6.2 Verify Order Details
1. Check order in admin
2. Verify order line item includes:
   - All participant details
   - All selected extras with quantities
   - Correct pricing breakdown

**Expected Result**: Order contains complete booking information.

## Test 7: Edge Cases

### 7.1 Test Maximum Quantity Limits
1. Try to increase extra quantity beyond max limit
2. Verify quantity controls prevent exceeding maximum

**Expected Result**: Quantity is limited correctly.

### 7.2 Test Required Extra Validation
1. Try to uncheck or reduce required extra to 0
2. Verify it's not possible
3. Ensure add to cart button works with required extras

**Expected Result**: Required extras cannot be removed.

### 7.3 Test Inactive Extra
1. In admin, set an extra to inactive
2. Verify it doesn't appear on frontend
3. Reactivate and verify it reappears

**Expected Result**: Inactive extras are hidden from frontend.

### 7.4 Test Product Without Extras
1. Create/edit an experience product
2. Uncheck all extras in the association
3. Verify frontend doesn't show extras section

**Expected Result**: No extras section when no extras are associated.

## Test 8: Form Validation

### 8.1 Test Incomplete Selection
1. Select date, time, participants
2. Don't select any optional extras
3. Verify form can still be submitted

**Expected Result**: Optional extras are truly optional.

### 8.2 Test Required Extras Blocking
1. If required extras exist but quantity is 0
2. Verify add to cart button is disabled

**Expected Result**: Required extras prevent form submission when not selected.

## Test Results Template

For each test, record:
- ✅ **PASS** - Works as expected
- ❌ **FAIL** - Doesn't work, note the issue
- ⚠️ **PARTIAL** - Works but with minor issues

### Test 1 Results
- 1.1 Create Per-Person Extra: ___
- 1.2 Create Per-Booking Extra: ___
- 1.3 Edit Extra: ___
- 1.4 Create Required Extra: ___

### Test 2 Results
- 2.1 Associate Extras: ___
- 2.2 Verify Persistence: ___

### Test 3 Results
- 3.1 Frontend Display: ___
- 3.2 Required Extra Behavior: ___

### Test 4 Results
- 4.1 Per-Person Calculation: ___
- 4.2 Per-Booking Calculation: ___
- 4.3 Multiple Quantities: ___
- 4.4 Required Auto-Calculation: ___

### Test 5 Results
- 5.1 Add to Cart: ___
- 5.2 Cart Display: ___
- 5.3 Cart Total: ___

### Test 6 Results
- 6.1 Checkout: ___
- 6.2 Order Details: ___

### Test 7 Results
- 7.1 Quantity Limits: ___
- 7.2 Required Validation: ___
- 7.3 Inactive Extra: ___
- 7.4 No Extras Product: ___

### Test 8 Results
- 8.1 Optional Validation: ___
- 8.2 Required Blocking: ___

## Notes
- Test in different browsers (Chrome, Firefox, Safari)
- Test on mobile devices
- Check for JavaScript console errors
- Verify database entries are created correctly
- Test with different WooCommerce themes