# Manual Tests - Consent Mode v2

## Test Environment Setup

### Prerequisites
1. WordPress site with FP Esperienze plugin active
2. WooCommerce installed and active
3. At least one Experience product configured
4. Browser developer tools open (Console tab)
5. Admin access to FP Esperienze settings

### Test Data Requirements
- GA4 Measurement ID (can be fake for testing: `G-TEST123456`)
- Meta Pixel ID (can be fake for testing: `123456789`)
- Experience product with booking form

## Test Cases

### 1. Consent Mode Settings Access

**Objective**: Verify consent mode settings are accessible and properly saved

**Steps**:
1. Log in as admin user
2. Navigate to **FP Esperienze → Settings → Integrations**
3. Scroll down to find "Consent Mode v2" section
4. Verify the following fields are visible:
   - "Enable Consent Mode" checkbox
   - "Consent Cookie Name" text field (default: `marketing_consent`)
   - "Consent JavaScript Function" text field
5. Enable consent mode and change cookie name to `test_consent`
6. Save settings
7. Reload page and verify settings are preserved

**Expected Results**:
- All consent mode fields are visible and functional
- Settings save and persist correctly
- Form validation works (no JavaScript errors)

### 2. Consent Mode Disabled (Default Behavior)

**Objective**: Verify tracking works normally when consent mode is disabled

**Steps**:
1. Ensure GA4 and Meta Pixel are configured in settings
2. Ensure "Enable Consent Mode" is unchecked
3. Save settings
4. Navigate to an experience product page
5. Open browser console
6. Add product to cart
7. Observe console output

**Expected Results**:
- GA4 events fire normally
- Meta Pixel events fire normally
- Console shows successful event tracking
- No consent-related blocking messages

### 3. Cookie-Based Consent - Granted

**Objective**: Test tracking when consent is granted via cookie

**Steps**:
1. Enable consent mode in settings
2. Set cookie name to `test_consent`
3. Save settings
4. Navigate to experience product page
5. Open browser console
6. Set consent cookie: `document.cookie = "test_consent=true; path=/"`
7. Verify consent status: `window.fpExpGetConsent()` should return `true`
8. Add product to cart
9. Observe console output

**Expected Results**:
- `window.fpExpGetConsent()` returns `true`
- GA4 events fire normally
- Meta Pixel events fire normally
- Console shows successful event tracking
- No blocking messages

### 4. Cookie-Based Consent - Denied

**Objective**: Test tracking blockage when consent is denied via cookie

**Steps**:
1. Ensure consent mode is enabled with cookie name `test_consent`
2. Navigate to experience product page
3. Open browser console
4. Set denied consent cookie: `document.cookie = "test_consent=false; path=/"`
5. Verify consent status: `window.fpExpGetConsent()` should return `false`
6. Add product to cart
7. Observe console output

**Expected Results**:
- `window.fpExpGetConsent()` returns `false`
- Console shows "GA4 event blocked due to consent" messages
- Console shows "Meta Pixel event blocked due to consent" messages
- No actual GA4/Meta events are fired
- `dataLayer` and `fbq` calls are prevented

### 5. Cookie Values Acceptance

**Objective**: Test various cookie values that should grant consent

**Steps**:
1. Test each of the following cookie values:
   - `document.cookie = "test_consent=true; path=/"`
   - `document.cookie = "test_consent=1; path=/"`
   - `document.cookie = "test_consent=granted; path=/"`
   - `document.cookie = "test_consent=TRUE; path=/"` (test case insensitivity)
2. For each value:
   - Set the cookie
   - Check `window.fpExpGetConsent()`
   - Add to cart and verify tracking works

**Expected Results**:
- All values (`true`, `1`, `granted`, `TRUE`) should return consent granted
- Tracking should work normally for all accepted values
- Case insensitive matching works

### 6. JavaScript Function-Based Consent

**Objective**: Test consent checking via JavaScript function

**Steps**:
1. Set consent mode to use function: `window.testCMP.getConsent`
2. Clear any existing consent cookies
3. In browser console, create the function:
   ```javascript
   window.testCMP = {
       getConsent: function() { return true; }
   };
   ```
4. Test consent status: `window.fpExpGetConsent()`
5. Add to cart and verify tracking
6. Change function to return false:
   ```javascript
   window.testCMP.getConsent = function() { return false; };
   ```
7. Test again

**Expected Results**:
- Function returning `true` grants consent and allows tracking
- Function returning `false` denies consent and blocks tracking
- JavaScript function takes precedence over cookie method

### 7. No Consent Data (Safe Default)

**Objective**: Verify default behavior when no consent information is available

**Steps**:
1. Enable consent mode
2. Clear all consent cookies: `document.cookie = "test_consent=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/"`
3. Ensure no consent JavaScript function is configured
4. Test consent status: `window.fpExpGetConsent()`
5. Add to cart and observe behavior

**Expected Results**:
- `window.fpExpGetConsent()` returns `false` (safe default)
- All tracking events are blocked
- Console shows blocking messages

### 8. Settings Validation

**Objective**: Test edge cases and validation in settings

**Steps**:
1. Test empty cookie name (should fall back to default)
2. Test invalid JavaScript function path
3. Test very long cookie names
4. Test special characters in settings

**Expected Results**:
- Empty cookie name defaults to `marketing_consent`
- Invalid JS function paths fail gracefully
- Settings handle edge cases without errors

### 9. Checkout Flow with Consent

**Objective**: Test consent mode through complete checkout process

**Steps**:
1. Enable consent mode and grant consent
2. Add experience to cart
3. Go to checkout
4. Complete checkout process
5. Verify all tracking events fire correctly:
   - add_to_cart
   - begin_checkout
   - add_payment_info
   - purchase

**Expected Results**:
- All ecommerce events fire when consent is granted
- Events are properly blocked when consent is denied
- No JavaScript errors during checkout

### 10. Browser Compatibility

**Objective**: Test consent mode across different browsers

**Steps**:
1. Test in Chrome, Firefox, Safari, Edge
2. Verify console API works correctly
3. Check cookie handling across browsers

**Expected Results**:
- Consistent behavior across browsers
- No browser-specific JavaScript errors
- Cookie handling works reliably

## Test Results Template

```
Test Case: [Number and Name]
Date: [Date]
Tester: [Name]
Browser: [Browser and Version]
Result: [PASS/FAIL]
Notes: [Any observations or issues]
```

## Common Issues and Troubleshooting

### Issue: `window.fpExpGetConsent is not defined`
- **Cause**: Tracking script not loaded or JavaScript error
- **Solution**: Check if experience product page and tracking is enabled

### Issue: Events still fire despite consent denied
- **Cause**: Consent mode not properly enabled
- **Solution**: Verify settings are saved and page is refreshed

### Issue: Cookie not being read correctly
- **Cause**: Cookie domain/path issues or typos in cookie name
- **Solution**: Check cookie name spelling and browser DevTools → Application → Cookies

### Issue: JavaScript function not working
- **Cause**: Function path incorrect or function not defined
- **Solution**: Verify function exists and path syntax is correct

## Performance Considerations

- Consent checking adds minimal performance overhead
- Cookie reading is synchronous and fast
- JavaScript function calls should be lightweight
- No impact on page load speed when consent mode is disabled