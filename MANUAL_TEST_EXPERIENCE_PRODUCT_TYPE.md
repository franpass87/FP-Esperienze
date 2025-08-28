# Manual Test: Experience Product Type Fix

## Issue Fixed
**Problem**: Experience product type not appearing in WooCommerce product type dropdown during creation/editing.

**Root Cause**: The plugin was using the wrong filter hook `woocommerce_product_type_selector` instead of the correct `product_type_selector` that WooCommerce actually calls.

## Fix Applied
**Modified filter registration** in `/includes/ProductType/Experience.php` (line 31):
- Changed from: `add_filter('woocommerce_product_type_selector', [$this, 'addProductType']);`
- Changed to: `add_filter('product_type_selector', [$this, 'addProductType']);`

**Updated debug scripts** for consistency:
- `debug-product-type.php` - Updated to check correct filter
- `verify-product-type-fix.php` - Fixed filter names and descriptions

## Manual Testing Steps

### Prerequisites
1. WordPress with WooCommerce installed and activated
2. FP Esperienze plugin activated
3. Admin access to WordPress

### Test 1: Verify Experience Appears in Product Type Dropdown

1. **Go to WordPress Admin → Products → Add New**
2. **Look at the Product Data section**
3. **Check the Product Type dropdown**
4. **Expected Result**: "Experience" should appear as an option in the dropdown
5. **Screenshot**: Take a screenshot showing "Experience" in the dropdown

### Test 2: Create New Experience Product

1. **Enter a product title** (e.g., "Test Experience Tour")
2. **Select "Experience" from the Product Type dropdown**
3. **Verify experience-specific fields appear**:
   - Experience tab should be visible
   - Dynamic Pricing tab should be visible
   - Duration field should appear
   - Capacity field should appear
   - Adult/Child pricing fields should appear
4. **Fill in some basic information**:
   - Duration: 120 (minutes)
   - Capacity: 10
   - Adult Price: 50
5. **Click "Publish"**
6. **Expected Result**: Product saves successfully

### Test 3: Verify Product Type Persists

1. **After publishing, stay on the product edit page**
2. **Check the Product Type dropdown**
3. **Expected Result**: "Experience" should still be selected
4. **Reload the page**
5. **Expected Result**: "Experience" should still be selected after reload

### Test 4: Verify Experience-Specific Functionality

1. **Go to the Experience tab**
2. **Verify all experience-specific fields are present**:
   - Meeting Point dropdown
   - Language settings
   - Cancellation policy fields
3. **Go to the Dynamic Pricing tab**
4. **Verify dynamic pricing options are available**

### Test 5: Test Product Frontend Display

1. **View the product on the frontend**
2. **Expected Result**: Product should display with experience-specific features
3. **Check that the product type is correctly identified as "experience"**

## Success Criteria

✅ **All tests should pass with these results**:
- Experience appears in product type dropdown
- Experience-specific tabs and fields are visible when Experience is selected
- Experience products can be created and saved successfully
- Product type persists as "experience" after saving and reloading
- Experience-specific functionality works on both admin and frontend

## If Tests Fail

If any test fails, check:

1. **PHP Error Logs**: Look for any PHP fatal errors during product creation
2. **Browser Console**: Check for JavaScript errors in the admin interface
3. **Plugin Activation**: Ensure FP Esperienze is properly activated
4. **WooCommerce Version**: Verify WooCommerce 8.0+ is installed
5. **WordPress Version**: Verify WordPress 6.5+ is running

## Technical Details

**Files Modified**:
1. `/includes/ProductType/Experience.php` - Fixed filter hook name (line 31)
2. `/debug-product-type.php` - Updated filter references for consistency
3. `/verify-product-type-fix.php` - Corrected filter names and test descriptions

**Key Fix**:
```php
// Before (WRONG):
add_filter('woocommerce_product_type_selector', [$this, 'addProductType']);

// After (CORRECT):
add_filter('product_type_selector', [$this, 'addProductType']);
```

**Why this works**:
- WooCommerce core uses `product_type_selector` filter to populate the dropdown
- The prefix `woocommerce_` was incorrect and not recognized by WooCommerce
- This simple one-line change ensures the Experience type is properly registered