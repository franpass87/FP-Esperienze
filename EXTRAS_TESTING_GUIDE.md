# FP Esperienze - Extras Feature Manual Testing Guide

## Overview
This guide provides manual testing steps for the newly implemented extras functionality in the FP Esperienze plugin.

## Prerequisites
- WordPress 6.5+ with WooCommerce 8.0+
- FP Esperienze plugin activated
- At least one Experience product created

## Test Scenarios

### Test 1: Admin Interface - Extra Management

#### Test 1.1: Create Extra (Per Person)
1. **Steps:**
   - Navigate to WP Admin → FP Esperienze → Extras
   - Click "Add New"
   - Fill in the form:
     - Name: "Professional Photography"
     - Description: "Professional photos of your experience"
     - Price: 25.00
     - Pricing Type: "Per Person"
     - Maximum Quantity: 1
     - Tax Class: Standard
     - Active: Checked
   - Click "Create Extra"

2. **Expected Result:**
   - Success message displayed
   - Extra appears in the extras list
   - All data correctly saved

#### Test 1.2: Create Extra (Per Booking)
1. **Steps:**
   - Navigate to WP Admin → FP Esperienze → Extras
   - Click "Add New"
   - Fill in the form:
     - Name: "Welcome Drink Package"
     - Description: "Bottle of local wine for the group"
     - Price: 35.00
     - Pricing Type: "Per Booking"
     - Maximum Quantity: 3
     - Tax Class: Standard
     - Active: Checked
   - Click "Create Extra"

2. **Expected Result:**
   - Success message displayed
   - Extra appears in the extras list
   - Shows "Per Booking" pricing type

#### Test 1.3: Edit and Delete Extras
1. **Steps:**
   - Edit an existing extra, change name and price
   - Save changes
   - Delete an extra using the delete button
   - Confirm deletion

2. **Expected Result:**
   - Changes saved successfully
   - Extra deleted from list
   - No errors in admin

### Test 2: Product Association

#### Test 2.1: Associate Extras with Experience Product
1. **Steps:**
   - Navigate to WooCommerce → Products
   - Edit an existing Experience product
   - Go to the "Extras" tab
   - Select both created extras (Photography and Welcome Drink)
   - Save product

2. **Expected Result:**
   - Extras tab appears for Experience products
   - Both extras can be selected
   - Associations saved successfully

### Test 3: Frontend Display and Interaction

#### Test 3.1: Extras Widget Display
1. **Steps:**
   - Visit the Experience product page on frontend
   - Scroll to booking widget
   - Observe extras section

2. **Expected Result:**
   - Extras section appears below booking placeholder
   - Both extras displayed with correct names, prices, and types
   - Per-person vs per-booking labels shown correctly

#### Test 3.2: Dynamic Price Calculation - Per Person Extra
1. **Steps:**
   - Check the "Professional Photography" extra
   - Observe price calculation
   - Note: Currently assumes 1 adult for calculation

2. **Expected Result:**
   - Extras total shows: €25.00 (1 person × €25.00)
   - Overall total updates to include extra cost
   - Real-time calculation without page refresh

#### Test 3.3: Dynamic Price Calculation - Per Booking Extra
1. **Steps:**
   - Check the "Welcome Drink Package" extra
   - If max quantity > 1, quantity field should appear
   - Change quantity to 2
   - Observe price calculation

2. **Expected Result:**
   - Extras total shows: €70.00 (2 × €35.00)
   - Overall total updates correctly
   - Quantity field appears and works

#### Test 3.4: Multiple Extras Selection
1. **Steps:**
   - Select both extras
   - Set Welcome Drink quantity to 2
   - Observe total calculation

2. **Expected Result:**
   - Combined extras total: €95.00 (€25.00 + €70.00)
   - Overall total includes both extras
   - Each extra displayed in breakdown

### Test 4: WooCommerce Integration

#### Test 4.1: Add to Cart with Extras
1. **Steps:**
   - Select extras on product page
   - Add product to cart (when add to cart functionality is implemented)
   - View cart page

2. **Expected Result:**
   - Product added with selected extras
   - Extras displayed as item metadata
   - Correct total calculation in cart

#### Test 4.2: Checkout and Order
1. **Steps:**
   - Proceed to checkout with extras
   - Complete order
   - View order details in admin

2. **Expected Result:**
   - Order shows product with extras
   - Extras listed as order item metadata
   - Correct total amount charged

## Database Verification

### Check Database Tables
Execute these SQL queries to verify data integrity:

```sql
-- Check extras table structure
DESCRIBE wp_fp_extras;

-- Check product-extras associations
SELECT * FROM wp_fp_product_extras;

-- Check specific extra data
SELECT * FROM wp_fp_extras WHERE name = 'Professional Photography';
```

## Performance Notes
- Admin interface handles large numbers of extras efficiently
- Frontend price calculation is real-time via JavaScript
- Database queries optimized with proper indexing

## Known Limitations
- Currently assumes 1 adult for per-person calculations (will be dynamic when booking system is implemented)
- Price formatting uses simplified €X.XX format (should integrate with WooCommerce formatting in production)

## Success Criteria
✅ Admin CRUD operations work correctly  
✅ Product associations save and load properly  
✅ Frontend widget displays and calculates correctly  
✅ WooCommerce integration preserves extras data through cart/order flow  
✅ Both per-person and per-booking pricing types function as expected