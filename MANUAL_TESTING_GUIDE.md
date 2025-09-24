# Manual Testing Guide - Experience Product Type

## Quick Start Verification

Follow these steps to verify that the Experience product type is working correctly in your WordPress/WooCommerce installation.

### Prerequisites ✅

Before testing, ensure you have:
- WordPress 6.5+ installed
- WooCommerce 8.0+ activated  
- FP Esperienze plugin activated
- Admin access to WordPress

### Step 1: Basic Verification

1. **Login to WordPress Admin**
2. **Navigate to Products → Add New**
3. **Look for the Product Data section**
4. **Check the Product Type dropdown**

**✅ Expected Result**: "Experience" should appear as an option in the dropdown

**❌ If Experience is missing**: 
- Check that FP Esperienze plugin is activated
- Verify no PHP errors in error logs
- Check WooCommerce version (8.0+ required)

### Step 2: Create Experience Product

1. **Enter a product title**: `Test Rome Walking Tour`
2. **Select "Experience" from Product Type dropdown**
3. **Verify Experience-specific tabs appear**:
   - ✅ "Experience" tab should be visible
   - ✅ "Dynamic Pricing" tab should be visible
4. **Click on the "Experience" tab**
5. **Fill in Experience fields**:
   - Duration: `180` (minutes)
   - Capacity: `15` (people)
   - Adult Price: `45.00`
   - Child Price: `25.00`
   - Booking Cutoff: `120` (minutes)

### Step 3: Save and Verify

1. **Click "Publish" or "Save Draft"**
2. **Wait for save confirmation**
3. **Reload the product edit page**
4. **Verify**:
   - ✅ Product Type still shows "Experience"
   - ✅ Experience tab is still visible
   - ✅ All Experience data is preserved
   - ✅ Experience-specific fields show saved values

### Step 4: Advanced Verification

#### Check Product Type in Database
If you have database access, verify the product type is stored correctly:

```sql
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE meta_key = '_product_type' 
AND post_id = [YOUR_PRODUCT_ID];
```

**Expected Result**: `meta_value = 'experience'`

#### Check Product Object Type
In WordPress admin or via code:

```php
$product = wc_get_product([PRODUCT_ID]);
echo $product->get_type(); // Should output: 'experience'
echo get_class($product); // Should output: FP\Esperienze\ProductType\WC_Product_Experience
```

### Step 5: Frontend Verification

1. **View the product on the frontend**
2. **Verify**:
   - ✅ Product displays correctly
   - ✅ No fatal errors on page
   - ✅ Product is recognized as Experience type

## Automated Testing Scripts

> **Security Requirement:** All utility scripts must run inside a WordPress context with an authenticated administrator. Use WP-CLI with the `--user=<admin>` flag or access the file from the browser while logged in as an administrator.

### Status Report
- **Location:** `wp-content/plugins/fp-esperienze/status-report.php`
- **WP-CLI:**
  ```bash
  wp eval-file wp-content/plugins/fp-esperienze/status-report.php --user=<admin>
  ```
- **Notes:** Requires the OPcache extension to perform inline syntax linting. If OPcache is unavailable, lint critical files manually with `php -l`.

### PHP Syntax Test
- **Location:** `wp-content/plugins/fp-esperienze/test-php-syntax.php`
- **WP-CLI:**
  ```bash
  wp eval-file wp-content/plugins/fp-esperienze/test-php-syntax.php --user=<admin>
  ```
- **Notes:** Uses OPcache to validate syntax without executing the files. When OPcache is disabled, manually lint files (e.g. `php -l wp-content/plugins/fp-esperienze/fp-esperienze.php`).

### Full Functionality Test
Run this in WordPress via WP-CLI or the browser:
```bash
wp eval-file wp-content/plugins/fp-esperienze/test-experience-functionality.php --user=<admin>
```

Or access via browser: `/wp-content/plugins/fp-esperienze/test-experience-functionality.php` (admin login required).

### WordPress Diagnostic Script
Run this for detailed diagnostics:
```bash
php verify-experience-product-type.php
```

## Common Issues & Solutions

### Issue: Experience not in dropdown
**Symptoms**: Only default WooCommerce product types appear
**Solutions**:
1. Verify plugin activation: `Plugins → Installed Plugins → FP Esperienze (Active)`
2. Check PHP error logs for fatal errors during plugin loading
3. Verify WooCommerce version: `WooCommerce → Status`
4. Deactivate/reactivate FP Esperienze plugin

### Issue: Experience type reverts to Simple
**Symptoms**: Product saves as Simple instead of Experience
**Solutions**:
1. This was a known issue - verify you have the latest version
2. Check PHP error logs during product save
3. Verify WooCommerce nonce and permissions

### Issue: Experience tabs not showing
**Symptoms**: Experience and Dynamic Pricing tabs missing
**Solutions**:
1. Check JavaScript errors in browser console
2. Verify product type is actually saved as 'experience'
3. Clear any caches (if using caching plugins)

### Issue: Experience fields not saving
**Symptoms**: Experience-specific data not preserved
**Solutions**:
1. Check form submission includes experience field data
2. Verify WordPress nonce validation passing
3. Check database write permissions

## Debug Information

If you need to report issues, include this information:

```
WordPress Version: [Check in Dashboard → Updates]
WooCommerce Version: [Check in WooCommerce → Status]  
FP Esperienze Version: [Check in Plugins list]
PHP Version: [Check in WooCommerce → Status → System Status]
Active Theme: [Check in Appearance → Themes]
Other Active Plugins: [List plugins that might conflict]
Error Logs: [Check PHP error logs for FP Esperienze entries]
```

## Success Confirmation

✅ **Test Passed**: If you can successfully:
1. See "Experience" in Product Type dropdown
2. Select Experience and see custom tabs
3. Save Experience product and data persists
4. Product maintains Experience type after reload

🎉 **Congratulations!** Your Experience product type is fully functional and ready for production use.

## Next Steps

Once Experience product type is working:

1. **Create Meeting Points**: `FP Esperienze → Meeting Points`
2. **Configure Extras**: `FP Esperienze → Extras`  
3. **Set up Dynamic Pricing Rules**: Use the Dynamic Pricing tab
4. **Configure Schedule**: Set recurring schedules for experiences
5. **Test Booking Flow**: Create test bookings to verify end-to-end functionality

---

**Need Help?** If tests fail, use the diagnostic scripts provided or check the WordPress error logs for detailed error information.