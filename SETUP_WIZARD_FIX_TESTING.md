# Setup Wizard Fix - Manual Testing Guide

## Summary of Fixes Applied

**Primary Issue**: Setup wizard menu was not appearing due to timing issue where submenu was registered before parent menu existed.

### Fixes Applied:

1. **Menu Registration Timing** (CRITICAL FIX)
   - Changed admin_menu hook priority from 5 to 15
   - Ensures parent menu exists before submenu registration

2. **WooCommerce Dependencies**
   - Added function_exists() checks for WooCommerce functions
   - Provided fallback values when WooCommerce unavailable

3. **CSS Styling**
   - Fixed inline style registration to ensure base styles are enqueued

## Manual Testing Steps

### Prerequisites
- WordPress installation with WooCommerce plugin active
- FP Esperienze plugin files with the fixes applied

### Test 1: Setup Wizard Menu Visibility

1. **Reset setup state:**
   ```sql
   DELETE FROM wp_options WHERE option_name = 'fp_esperienze_setup_complete';
   ```

2. **Deactivate and reactivate plugin:**
   - Go to Plugins → Deactivate FP Esperienze
   - Reactivate FP Esperienze

3. **Check setup wizard menu:**
   - Navigate to admin dashboard
   - Look for "FP Esperienze" menu in admin sidebar
   - Verify "Setup Wizard" submenu appears

   **Expected Result**: ✅ Setup Wizard submenu should be visible

### Test 2: Automatic Redirect on Activation

1. **Ensure clean state:**
   ```sql
   DELETE FROM wp_options WHERE option_name = 'fp_esperienze_setup_complete';
   ```

2. **Activate plugin:**
   - Deactivate FP Esperienze
   - Reactivate FP Esperienze

3. **Check redirect:**
   - Should automatically redirect to setup wizard
   - URL should be: `/wp-admin/admin.php?page=fp-esperienze-setup-wizard`

   **Expected Result**: ✅ Automatic redirect to setup wizard occurs

### Test 3: Setup Wizard Form Functionality

1. **Access setup wizard:**
   - Navigate to FP Esperienze → Setup Wizard

2. **Test Step 1 - Basic Settings:**
   - Verify all form fields render correctly
   - Currency dropdown shows options (even without WooCommerce)
   - Fill in basic settings and click "Next"

   **Expected Result**: ✅ Form submits and advances to step 2

3. **Test Step 2 - Integrations:**
   - Verify integration fields appear
   - Fill in optional integration settings
   - Click "Next" or "Skip"

   **Expected Result**: ✅ Form submits and advances to step 3

4. **Test Step 3 - Brand Settings:**
   - Verify brand settings fields appear
   - Test logo upload functionality
   - Color picker should work
   - Click "Finish Setup"

   **Expected Result**: ✅ Setup completes successfully

### Test 4: Setup Completion

1. **Complete setup wizard fully**

2. **Verify completion state:**
   - Should redirect to dashboard with completion message
   - Setup Wizard menu should disappear from admin menu
   - Option `fp_esperienze_setup_complete` should be set to 1

   **Expected Result**: ✅ Setup completion works correctly

### Test 5: No Duplicate Redirects

1. **Access admin pages after setup:**
   - Navigate to various admin pages
   - Verify no unwanted redirects occur

   **Expected Result**: ✅ No redirects after setup completion

## Troubleshooting

### If Setup Wizard Menu Still Not Visible:

1. **Check for PHP errors:**
   ```bash
   tail -f /path/to/wordpress/debug.log
   ```

2. **Verify parent menu exists:**
   - Check if main "FP Esperienze" menu appears
   - If not, there may be capability issues

3. **Check user capabilities:**
   - Ensure current user has `manage_woocommerce` capability

### If Redirects Not Working:

1. **Check transient:**
   ```sql
   SELECT * FROM wp_options WHERE option_name LIKE '%fp_esperienze_activation_redirect%';
   ```

2. **Clear any caching:**
   - Clear object cache if using cache plugins

## Verification Checklist

- [ ] Setup wizard menu appears in admin when setup not complete
- [ ] Automatic redirect works on plugin activation
- [ ] All three setup steps function correctly
- [ ] Forms submit and advance properly
- [ ] Setup completion works and redirects properly
- [ ] Setup wizard menu disappears after completion
- [ ] No unwanted redirects after completion
- [ ] Works with and without WooCommerce functions available

## Success Criteria

The setup wizard fix is successful if:
1. ✅ Setup wizard menu appears correctly
2. ✅ All form steps work without errors
3. ✅ Setup completion process works
4. ✅ No PHP errors in debug log
5. ✅ Proper redirect behavior

If all tests pass, the setup wizard issue has been resolved!