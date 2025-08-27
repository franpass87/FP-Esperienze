# Manual Tests - Google Places Reviews

## Test Environment Setup

### Prerequisites
1. WordPress 6.5+ with WooCommerce 8.0+
2. FP Esperienze plugin activated
3. Google Places API key with Places API (New) enabled
4. At least one meeting point with a valid `place_id`
5. At least one experience product with meeting point assigned

### Test Data Setup
1. Navigate to **FP Esperienze → Settings → Integrations**
2. Fill in Google Places API settings:
   - API Key: Enter valid Google Places API key
   - Display Reviews: Check to enable
   - Reviews Limit: Set to 5 (default)
   - Cache TTL: Set to 60 minutes (default)
3. Create/edit a meeting point with a valid Google `place_id`
4. Assign the meeting point to an experience product

## Test Cases

### 1. Reviews Display - Full Features

**Objective**: Test complete reviews display when API and data are available

**Steps**:
1. Configure Google Places API with valid key and enable reviews
2. Navigate to single experience page with meeting point that has place_id
3. Scroll to Meeting Point section
4. Check if Reviews section appears after Meeting Point info

**Expected Results**:
- Reviews section displays with proper heading
- Rating summary shows stars, numeric rating, and total count
- Individual reviews show author names (partial), ratings, time, and text excerpts
- Google disclosure appears with "Reviews via Google" and Maps profile link
- All content is properly styled and responsive

### 2. Reviews Disabled in Settings

**Objective**: Test behavior when reviews are disabled in admin

**Steps**:
1. Navigate to **FP Esperienze → Settings → Integrations**
2. Uncheck "Display Reviews" option
3. Save settings
4. Visit experience page with meeting point
5. Check Meeting Point section

**Expected Results**:
- No Reviews section appears
- Meeting Point section displays normally without reviews
- No API calls are made (check browser network tab)

### 3. Missing API Key

**Objective**: Test fallback when API key is not configured

**Steps**:
1. Navigate to **FP Esperienze → Settings → Integrations**
2. Clear Google Places API Key field
3. Keep "Display Reviews" checked
4. Save settings
5. Visit experience page with meeting point

**Expected Results**:
- No Reviews section appears
- No errors displayed to user
- No API calls are made
- Meeting Point section displays normally

### 4. Invalid place_id

**Objective**: Test graceful handling of invalid place_id

**Steps**:
1. Configure valid API settings
2. Edit meeting point to have invalid place_id (e.g., "invalid_id_123")
3. Visit experience page with this meeting point
4. Check console for errors and network requests

**Expected Results**:
- No Reviews section appears (graceful fallback)
- No visible errors to user
- Error logged to PHP error log (check server logs)
- Meeting Point section displays normally

### 5. API Quota Exceeded / Rate Limiting

**Objective**: Test behavior when API limits are reached

**Steps**:
1. If possible, trigger rate limiting or quota exceeded response
2. Or simulate by temporarily using invalid API key
3. Visit experience page multiple times

**Expected Results**:
- Graceful fallback - no reviews shown
- No error messages displayed to frontend users
- Meeting Point section remains functional
- Errors logged appropriately

### 6. Caching Behavior

**Objective**: Test server-side caching functionality

**Steps**:
1. Set Cache TTL to 5 minutes in settings
2. Visit experience page with meeting point (cold cache)
3. Check browser network tab for API call
4. Refresh page immediately
5. Check if API call is made again
6. Wait 6 minutes, refresh page
7. Check for new API call

**Expected Results**:
- First visit: API call made, reviews displayed
- Immediate refresh: No API call, reviews from cache
- After TTL expiry: New API call made
- Cache key: `fp_gplaces_[md5_hash_of_place_id]`

### 7. Partial Data / Minimal Reviews

**Objective**: Test display when only rating data is available

**Steps**:
1. Configure API with place_id that has rating but few/no text reviews
2. Visit experience page

**Expected Results**:
- Fallback to minimal reviews display
- Shows rating summary with stars and count
- Google disclosure and Maps link present
- Clean layout without individual review cards

### 8. Reviews Limit Setting

**Objective**: Test reviews limit configuration

**Steps**:
1. Set Reviews Limit to 3 in settings
2. Visit experience page with place that has many reviews
3. Count displayed reviews
4. Change limit to 8, clear cache, test again

**Expected Results**:
- Only 3 reviews displayed when limit is 3
- Only 8 reviews displayed when limit is 8
- Rating summary shows total count (not limited)

### 9. Mobile Responsiveness

**Objective**: Test reviews display on mobile devices

**Steps**:
1. Configure reviews and visit experience page
2. Test on mobile device or browser responsive mode
3. Check different breakpoints (480px, 768px, 1024px)

**Expected Results**:
- Reviews section adapts to mobile layout
- Stars and ratings remain readable
- Author/time stack vertically on mobile
- Text wraps properly
- Touch targets are appropriate size

### 10. Accessibility

**Objective**: Test accessibility features

**Steps**:
1. Use screen reader or accessibility tools
2. Navigate to reviews section
3. Check tab navigation through reviews
4. Verify ARIA labels and structure

**Expected Results**:
- Star ratings have proper aria-labels ("X out of 5 stars")
- Heading hierarchy is correct (h2 for Reviews)
- Links have descriptive text
- Content is screen reader friendly

### 11. Google Maps Profile Link

**Objective**: Test Maps profile link functionality

**Steps**:
1. Visit experience page with reviews
2. Click "View on Google Maps" link in disclosure
3. Verify link target and attributes

**Expected Results**:
- Link opens in new tab/window (target="_blank")
- Link has rel="noopener nofollow" attributes
- URL format: `https://www.google.com/maps/place/?q=place_id:[place_id]`
- Link leads to correct Google Maps location

### 12. Error Handling & Logging

**Objective**: Test error handling and logging

**Steps**:
1. Check PHP error logs before testing
2. Test with various invalid configurations
3. Monitor error logs during tests
4. Verify no sensitive data in logs

**Expected Results**:
- Errors logged with format: `[FP Esperienze - Google Places] Message: Context`
- No API keys or sensitive data in logs
- Only safe context data logged (place_id, error codes)
- Graceful fallbacks prevent frontend errors

## Test Results Template

### Test Environment
- WordPress Version: ___
- WooCommerce Version: ___
- FP Esperienze Version: ___
- PHP Version: ___
- Theme: ___

### Test Results
- [ ] 1. Reviews Display - Full Features
- [ ] 2. Reviews Disabled in Settings
- [ ] 3. Missing API Key
- [ ] 4. Invalid place_id
- [ ] 5. API Quota Exceeded / Rate Limiting
- [ ] 6. Caching Behavior
- [ ] 7. Partial Data / Minimal Reviews
- [ ] 8. Reviews Limit Setting
- [ ] 9. Mobile Responsiveness
- [ ] 10. Accessibility
- [ ] 11. Google Maps Profile Link
- [ ] 12. Error Handling & Logging

### Notes
- Record any unexpected behavior
- Note performance impacts
- Document any browser-specific issues
- Verify console errors are minimal

## Performance Notes

### Optimization Features
- Server-side caching with configurable TTL
- Conditional loading (only when enabled and place_id exists)
- Graceful fallbacks prevent blocking
- Minimal API calls (respects cache)

### Monitoring
- Check page load times with/without reviews
- Monitor API call frequency
- Verify cache hit rates
- Test under various load conditions