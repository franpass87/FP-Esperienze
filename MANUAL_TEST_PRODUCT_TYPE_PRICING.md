# Manual Test: Experience Product Type and Dynamic Pricing Fixes

## Overview
This manual test verifies that both reported issues have been resolved:
1. Experience product type missing from WooCommerce product creation dropdown
2. AIFeaturesManager::applyDynamicPricing() type error when receiving string prices

## Prerequisites
- WordPress site with WooCommerce active
- FP Esperienze plugin installed and active
- Admin access to WordPress dashboard

## Test 1: Experience Product Type Registration

### Steps:
1. **Go to WooCommerce Products**
   - Navigate to `WooCommerce → Products`
   - Click "Add New" to create a new product

2. **Check Product Type Dropdown**
   - In the "Product Data" meta box, locate the "Product Type" dropdown
   - Verify that "Experience" is listed among the available options
   - **Expected Result**: "Experience" should appear in the dropdown

3. **Create Experience Product**
   - Select "Experience" from the product type dropdown
   - Fill in basic product information (name, description)
   - Save the product
   - **Expected Result**: Product saves successfully with type "experience"

4. **Verify Product Type Persistence**
   - After saving, reload the product edit page
   - Check that "Experience" is still selected in the product type dropdown
   - **Expected Result**: Product type remains "Experience" after save/reload

### Troubleshooting:
- If "Experience" doesn't appear, check error logs for plugin initialization errors
- Verify FP Esperienze plugin is active and loaded
- Check that WooCommerce is active and compatible version

## Test 2: Dynamic Pricing Type Error Fix

### Background:
The original error was:
```
FP\Esperienze\AI\AIFeaturesManager::applyDynamicPricing(): Argument #1 ($price) must be of type float, string given
```

### Steps:

1. **Enable Debug Logging**
   - In `wp-config.php`, ensure: `define('WP_DEBUG', true);` and `define('WP_DEBUG_LOG', true);`

2. **Test with Experience Product**
   - Create or edit an Experience product
   - Set a price (e.g., "50.00")
   - Save the product

3. **Trigger Price Filters**
   - View the product on frontend
   - Add product to cart (if booking system is configured)
   - Check WordPress error logs (`wp-content/debug.log`)

4. **Verify No Type Errors**
   - **Expected Result**: No TypeError exceptions in the logs related to applyDynamicPricing
   - The method should handle string prices gracefully

### Test Script Verification:
Run the included test scripts to verify fixes:

```bash
# Test the type conversion logic
php test-reflection.php

# Test complete product type registration (if WordPress environment available)
php test-product-type.php
```

**Expected Output for test-reflection.php:**
```
✅ SUCCESS: Method accepts string input and returns: 50
✅ SUCCESS: Method accepts empty string and returns: 0
🎉 The type error fix is working correctly!
```

## Test 3: Integration Test

### Steps:
1. **Create Experience Product with AI Features**
   - Create an Experience product
   - Ensure AI features are enabled in plugin settings
   - Set a base price

2. **Test Different Price Scenarios**
   - View product with various price states (empty, string, numeric)
   - Check that no fatal errors occur
   - Verify pricing calculations work without type errors

3. **Monitor Error Logs**
   - **Expected Result**: No TypeError exceptions related to price handling
   - All price conversions should handle mixed input types gracefully

## Success Criteria

### ✅ Experience Product Type Working:
- "Experience" appears in WooCommerce product type dropdown
- Experience products can be created and saved
- Product type persists after save/reload

### ✅ Dynamic Pricing Type Error Fixed:
- No TypeError exceptions in logs related to applyDynamicPricing
- Method accepts string, numeric, and null price inputs
- Price conversions work correctly (string "50.00" → float 50.0)

## Code Changes Summary

### 1. AIFeaturesManager.php
```php
// Before (caused TypeError):
public function applyDynamicPricing(float $price, \WC_Product $product): float

// After (accepts mixed types):
public function applyDynamicPricing($price, \WC_Product $product): float {
    $price = is_numeric($price) ? (float) $price : 0.0;
    // ... rest of method
}
```

### 2. Experience.php
```php
// Moved critical filters to constructor with higher priority:
add_filter('woocommerce_product_type_selector', [$this, 'addProductType'], 5);
add_filter('woocommerce_product_class', [$this, 'getProductClass'], 5, 2);
add_filter('woocommerce_data_stores', [$this, 'registerDataStore'], 5, 1);
```

### 3. Plugin.php
```php
// Initialize Experience product type immediately instead of on 'init':
$this->initExperienceProductType();
```

## Expected Log Entries (Success)
Look for these entries in WordPress debug logs:

```
✅ FP Esperienze: Experience product type initialized successfully
✅ No TypeError exceptions related to price handling
```

## Error Indicators (If Still Broken)
Watch for these error patterns:

```
❌ FP\Esperienze\AI\AIFeaturesManager::applyDynamicPricing(): Argument #1 ($price) must be of type float, string given
❌ Experience product type not found in woocommerce_product_type_selector
```

If you see these errors, the fixes may need additional adjustments.