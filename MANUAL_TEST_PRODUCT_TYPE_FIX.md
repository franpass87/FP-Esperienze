# Manual Test: Experience Product Type Fix

This test verifies that the enhanced fix for the experience product type saving issue works correctly.

## Issue
When creating a new product and selecting "Experience" as the product type, then publishing it, the product was being saved as a "simple" product instead of maintaining the "experience" type.

## Root Cause
The issue was caused by using the incorrect filter hook `product_type_selector` instead of the proper WooCommerce filter `woocommerce_product_type_selector`. This meant that while the "Experience" option appeared in the dropdown, WooCommerce core didn't recognize it as a valid product type and automatically reverted it to "simple" during save validation.

## Fix Implementation
The fix includes multiple defensive measures:

### Backend (PHP):
1. **Correct Filter Hook**: 
   - Changed from `product_type_selector` to `woocommerce_product_type_selector` (CRITICAL FIX)
   - This ensures WooCommerce core recognizes 'experience' as a valid product type

2. **Multiple Hook Points**: 
   - `woocommerce_process_product_meta` (priority 20)
   - `woocommerce_update_product` (priority 5) 
   - `woocommerce_new_product` (priority 5)

3. **Redundant Product Type Setting**:
   - `update_post_meta($post_id, '_product_type', 'experience')`
   - `$_POST['product-type'] = 'experience'` to ensure WooCommerce core picks it up

4. **Data Store Registration**: 
   - Registers the experience product type with WooCommerce's data store system

### Frontend (JavaScript):
1. **Form Submission Protection**: Ensures product type value is preserved during form submit
2. **CSS Class Management**: Proper show/hide of experience-specific fields

## Test Steps

### Prerequisites
1. WordPress with WooCommerce installed
2. FP Esperienze plugin activated
3. Admin access to product creation

### Test 1: Create New Experience Product
1. Go to WordPress admin → Products → Add New
2. Enter a product title (e.g., "Test Experience")
3. In the Product Data section, select "Experience" from the dropdown
4. Fill in some experience-specific fields (duration, capacity, etc.)
5. Click "Publish"
6. **Expected Result**: Product is saved with product type "experience"
7. **Verification**: 
   - Reload the product edit page
   - Check that "Experience" is still selected in the product type dropdown
   - Verify experience-specific tabs are visible

### Test 2: Convert Existing Product to Experience
1. Go to an existing simple product
2. Change product type from "Simple Product" to "Experience"
3. Fill in experience fields
4. Click "Update"
5. **Expected Result**: Product type changes to "experience" and experience data is saved
6. **Verification**: Reload page and confirm product type is still "experience"

### Test 3: Convert Experience to Different Type
1. Go to an existing experience product
2. Change product type from "Experience" to "Simple Product"
3. Click "Update"
4. **Expected Result**: Product type changes to "simple"
5. **Verification**: Experience-specific fields should be hidden

## Technical Verification

Check the database directly:
```sql
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE meta_key = '_product_type' 
AND post_id = [YOUR_PRODUCT_ID];
```

Should return `meta_value = 'experience'` for experience products.

## Success Criteria
- ✅ **FIXED**: Product type filter uses correct WooCommerce hook (`woocommerce_product_type_selector`)
- ✅ New experience products maintain their type after saving (Enhanced with multiple hooks)
- ✅ Existing products can be converted to experience type (Preserved with data store registration)
- ✅ Experience products can be converted to other types (Not interfered with)
- ✅ No impact on non-experience product types (Defensive checks added)
- ✅ All experience-specific functionality continues to work (JavaScript enhancements)
- ✅ Redundant safeguards prevent type reversion (Multiple defensive measures)
- ✅ **ROOT CAUSE FIXED**: WooCommerce now recognizes 'experience' as valid product type

## If Test Fails
1. Check PHP error logs for any errors during product save
2. Verify the POST data contains `product-type=experience`
3. Confirm the `saveProductData` method is being called
4. Check that WooCommerce nonce verification passes
5. **NEW**: Verify the `ensureProductType` method catches any overrides
6. **NEW**: Check browser console for JavaScript errors during form submission