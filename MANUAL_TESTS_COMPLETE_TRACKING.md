# Manual Tests - Complete Marketing Attribution & Tracking Integrations

## Overview
This document provides comprehensive tests for the newly implemented tracking integrations including Google Ads conversion tracking, UTM parameter attribution, Meta Conversions API, and consent mode functionality.

## Prerequisites
- WordPress site with FP Esperienze plugin installed
- At least one experience product configured
- Access to admin dashboard
- Browser developer tools for testing JavaScript functionality

## Test 1: Setup Wizard Integration Configuration

### Steps:
1. Go to **FP Esperienze > Setup Wizard** (if available) or trigger setup
2. Navigate to the **Third-Party Integrations** step
3. Test all integration fields:

**Google Analytics 4:**
- Enter a valid GA4 Measurement ID (G-XXXXXXXXXX)
- Enable "Enhanced eCommerce tracking" checkbox

**Google Ads:**
- Enter a valid Conversion ID (AW-XXXXXXXXXX)
- Enter a Purchase Conversion Label (optional)

**Meta Pixel (Facebook):**
- Enter a valid Pixel ID
- Enable "Meta Conversions API (server-side tracking)" checkbox
- When enabled, verify that Access Token and Dataset ID fields appear
- Enter test values for Meta CAPI fields

**Privacy & Consent:**
- Enable "Consent Mode (GDPR Compliance)" checkbox
- When enabled, verify consent settings fields appear
- Set consent cookie name (default: marketing_consent)
- Optionally set JavaScript function for consent checking

### Expected Results:
- All fields should save properly
- Conditional fields (Meta CAPI, Consent settings) should show/hide correctly
- Settings should persist after saving

## Test 2: Admin Settings Integration Tab

### Steps:
1. Go to **FP Esperienze > Settings**
2. Click on **Integrations** tab
3. Verify all integration options are available:

**Google Analytics 4 Section:**
- Measurement ID field
- Enhanced eCommerce checkbox

**Google Ads Section:**
- Conversion ID field
- Purchase Conversion Label field

**Meta Pixel Section:**
- Pixel ID field
- Conversions API checkbox
- Access Token field (shown when CAPI enabled)
- Dataset ID field with "Test Connection" button

**Consent Mode v2 Section:**
- Enable Consent Mode checkbox
- Consent Cookie Name field
- Consent JavaScript Function field

### Expected Results:
- All sections should display correctly
- Settings should load existing values
- Dynamic field visibility should work (Meta CAPI fields, consent settings)

## Test 3: Meta Conversions API Connection Test

### Steps:
1. In **Settings > Integrations**, configure Meta Pixel settings:
   - Enter valid Pixel ID
   - Enable Conversions API
   - Enter valid Access Token
   - Enter valid Dataset ID
2. Click **Test Connection** button
3. Check the response message

### Expected Results:
- Button should show "Testing..." while request is in progress
- Success: Green notice with "Meta Conversions API connection successful!"
- Error: Red notice with specific error message
- Test should not affect live tracking data (uses test event code)

## Test 4: Frontend Tracking Script Loading

### Steps:
1. Configure at least one tracking integration (GA4, Google Ads, or Meta Pixel)
2. Visit an experience product page
3. Open browser Developer Tools > Network tab
4. Reload the page
5. Check for tracking scripts:

**For GA4:**
- gtag/js script from googletagmanager.com
- Inline script with GA4 config

**For Google Ads:**
- Same gtag script (shared with GA4) or separate if only Google Ads enabled
- Google Ads config script

**For Meta Pixel:**
- fbevents.js script
- Meta Pixel initialization script

### Expected Results:
- Only scripts for enabled integrations should load
- Scripts should load on experience product pages, cart, checkout
- Scripts should contain correct IDs/configuration

## Test 5: UTM Parameter Capture

### Steps:
1. Visit any page with UTM parameters:
   ```
   https://yoursite.com/?utm_source=google&utm_medium=cpc&utm_campaign=test&gclid=abc123
   ```
2. Open browser Developer Tools > Application/Storage > Session Storage
3. Look for `fp_utm_params` entry
4. Add an experience to cart and complete checkout
5. Check order meta data in WooCommerce admin

### Expected Results:
- UTM parameters should be stored in sessionStorage
- Order should contain meta fields: `_utm_source`, `_utm_medium`, `_utm_campaign`, `_gclid`
- Attribution data should persist through checkout process

## Test 6: Consent Mode Functionality

### Steps:
1. Enable Consent Mode in **Settings > Integrations**
2. Set up a test consent cookie or JavaScript function
3. Visit an experience product page
4. Check browser console for consent-related messages
5. Test with consent granted and denied states

**Without Consent:**
- Set consent cookie to "false" or ensure consent function returns false
- Check console for "blocked due to consent" messages

**With Consent:**
- Set consent cookie to "true" or ensure consent function returns true
- Verify tracking events fire normally

### Expected Results:
- When consent is denied: tracking events should be blocked with console messages
- When consent is granted: tracking events should fire normally
- Consent checking should work with both cookie and JavaScript function methods

## Test 7: E-commerce Event Tracking

### Steps:
1. Enable GA4 and Meta Pixel tracking
2. Open browser Developer Tools > Console
3. Complete a full purchase flow:
   - View experience product page
   - Add to cart
   - Begin checkout
   - Complete purchase

### Expected Results:
Console should show tracking events in this order:
- `GA4 Event: view_item` (on product page)
- `Meta Pixel Event: ViewContent` (on product page)
- `GA4 Event: add_to_cart` (after adding to cart)
- `Meta Pixel Event: AddToCart` (after adding to cart)
- `GA4 Event: begin_checkout` (on checkout page)
- `Meta Pixel Event: InitiateCheckout` (on checkout page)
- `GA4 Event: purchase` (on order confirmation)
- `Meta Pixel Event: Purchase` (on order confirmation)
- `Google Ads Conversion: purchase` (on order confirmation, if configured)

## Test 8: Server-Side Meta Conversions API

### Steps:
1. Enable Meta Conversions API with valid credentials
2. Place a test order with experience products
3. Confirm the checkout completes without waiting for the Meta HTTP request
4. Check WordPress error logs or debug logs
5. Verify in Meta Events Manager (if access available)

### Expected Results:
- Checkout flow is not delayed by the Meta API call
- Server-side purchase event should be sent to Meta
- Log should show "Meta CAPI Success" message
- Event should include hashed customer data
- Event should have same event_id as frontend for deduplication

## Test 9: Google Ads Conversion Tracking

### Steps:
1. Configure Google Ads Conversion ID and Purchase Label
2. Complete a purchase with experience products
3. Check browser Developer Tools > Console
4. Verify in Google Ads (if access available)

### Expected Results:
- Console should show "Google Ads Conversion: purchase" message
- Conversion should include value, currency, and transaction ID
- If conversion label is configured, it should be included in send_to parameter

## Test 10: Cross-Integration Attribution

### Steps:
1. Enable all tracking integrations
2. Visit site with UTM parameters and complete purchase
3. Check order details in WooCommerce admin
4. Verify attribution data is included in tracking events

### Expected Results:
- Order should contain UTM attribution meta data
- GA4 purchase event should include attribution data
- Meta Pixel event should include attribution data  
- Google Ads conversion should include attribution data
- All tracking should reference the same order and include consistent attribution

## Troubleshooting

### Common Issues:
1. **Scripts not loading**: Check if tracking IDs are properly configured
2. **Consent blocking**: Verify consent mode settings and cookie values
3. **Meta CAPI test fails**: Check access token, dataset ID, and network connectivity
4. **UTM not captured**: Ensure WooCommerce sessions are enabled
5. **Events not firing**: Check browser console for JavaScript errors

### Debug Information:
- Check `window.fpTrackingSettings` in browser console for configuration
- Check `window.fpTrackingData` for pending events
- Use "Preserve log" in Developer Tools to see events across page navigation
- Check WordPress error logs for server-side Meta CAPI errors