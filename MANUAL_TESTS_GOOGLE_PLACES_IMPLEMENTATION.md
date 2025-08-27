# Manual Test Checklist - Google Places Reviews Implementation

## Environment Setup

### WordPress Configuration
- [ ] WordPress 6.5+ installed
- [ ] WooCommerce 8.0+ activated  
- [ ] FP Esperienze plugin activated
- [ ] PHP 8.1+ running

### Test Data Required
- [ ] Valid Google Places API key with Places API (New) enabled
- [ ] At least one meeting point with valid `place_id` configured
- [ ] At least one experience product with meeting point assigned
- [ ] Access to Google Places that has reviews (for testing)

## Test Cases

### 1. Admin Settings - Google Places API
**Objective**: Verify Google Places API settings can be configured

**Steps**:
1. Navigate to **WP Admin → FP Esperienze → Settings → Integrations**
2. Locate "Google Places API" section
3. Fill in the following fields:
   - API Key: [Enter valid Google Places API key]
   - Display Reviews: ✓ Check to enable
   - Reviews Limit: Set to 5 (default)
   - Cache TTL: Set to 60 minutes (default)
4. Click "Save Integrations"

**Expected Results**:
- [ ] Settings form appears correctly
- [ ] All fields accept input properly
- [ ] Success message appears after save
- [ ] Settings persist after page reload

### 2. Admin Settings - Google Business Profile (Placeholder)
**Objective**: Verify GBP placeholder fields appear correctly

**Steps**:
1. In the same Integrations page, locate "Google Business Profile API (Optional)" section
2. Check the OAuth Client ID and OAuth Client Secret fields

**Expected Results**:
- [ ] Section appears with proper heading
- [ ] OAuth Client ID field is disabled with placeholder text
- [ ] OAuth Client Secret field is disabled with placeholder text  
- [ ] Descriptive text explains it's for future implementation
- [ ] Requirements note about business ownership is displayed

### 3. Reviews Display - Full Features
**Objective**: Test complete reviews display on experience single page

**Steps**:
1. Ensure Google Places API is configured and enabled
2. Navigate to single experience page with meeting point that has place_id
3. Scroll to Meeting Point section
4. Look for Reviews section after Meeting Point info

**Expected Results**:
- [ ] Reviews section displays with proper heading "Reviews"
- [ ] Rating summary shows:
  - [ ] Star visualization (★ for filled, ☆ for empty)
  - [ ] Numeric rating value (e.g., "4.5")
  - [ ] Total review count (e.g., "(123 reviews)")
- [ ] Individual reviews display:
  - [ ] Author names are partial (e.g., "John D." not "John Doe")
  - [ ] Individual star ratings for each review
  - [ ] Review text excerpts (max 150 chars)
  - [ ] Relative time descriptions (e.g., "2 weeks ago")
- [ ] Google disclosure appears:
  - [ ] "Reviews via Google" text
  - [ ] "View on Google Maps" link
  - [ ] Link opens in new tab with correct Google Maps URL

### 4. Reviews Disabled Test
**Objective**: Verify behavior when reviews are disabled

**Steps**:
1. Navigate to **FP Esperienze → Settings → Integrations**
2. Uncheck "Display Reviews" option
3. Save settings
4. Visit experience page with meeting point

**Expected Results**:
- [ ] No Reviews section appears
- [ ] Meeting Point section displays normally without reviews
- [ ] No API calls are made (check browser Network tab)
- [ ] No JavaScript errors in console

### 5. Missing API Key Test
**Objective**: Test graceful handling of missing API key

**Steps**:
1. Clear the Google Places API Key field in settings
2. Keep "Display Reviews" checked
3. Save settings
4. Visit experience page with meeting point

**Expected Results**:
- [ ] No Reviews section appears
- [ ] No errors displayed to user
- [ ] No API calls are made
- [ ] Meeting Point section displays normally

### 6. Cache Functionality Test
**Objective**: Verify server-side caching works correctly

**Steps**:
1. Set Cache TTL to 5 minutes in settings
2. Visit experience page with meeting point (cold cache)
3. Check browser Network tab for API call
4. Refresh page immediately  
5. Check if API call is made again
6. Wait 6 minutes, refresh page
7. Check for new API call

**Expected Results**:
- [ ] First visit: API call made to Google Places API
- [ ] Immediate refresh: No new API call (cached data used)
- [ ] After TTL expiry: New API call made
- [ ] Reviews display consistently across cache hits/misses

### 7. Mobile Responsiveness Test
**Objective**: Verify reviews display properly on mobile

**Steps**:
1. Open experience page with reviews on mobile device or browser responsive mode
2. Test different screen sizes (320px, 768px, 1024px)
3. Check layout and readability

**Expected Results**:
- [ ] Reviews section adapts to mobile layout
- [ ] Stars and ratings remain readable
- [ ] Author/time information stacks appropriately
- [ ] Text wraps properly without overflow
- [ ] Touch targets are appropriate size

### 8. Error Handling Test
**Objective**: Test API error handling (simulated)

**Steps**:
1. Use invalid API key or invalid place_id
2. Visit experience page
3. Check for graceful error handling

**Expected Results**:
- [ ] No visible errors displayed to users
- [ ] Fallback to no reviews section (graceful degradation)
- [ ] Errors logged to PHP error log (check server logs)
- [ ] Meeting Point section remains functional

### 9. Performance Test
**Objective**: Verify performance impact is minimal

**Steps**:
1. Measure page load time without reviews enabled
2. Enable reviews and measure page load time again
3. Check for any significant performance impact

**Expected Results**:
- [ ] Page load time increase is minimal (< 500ms additional)
- [ ] No blocking of other page content loading
- [ ] Caching reduces subsequent load times

### 10. Data Privacy Test
**Objective**: Verify privacy compliance

**Steps**:
1. Review displayed author names
2. Check that no full personal information is shown
3. Verify cache policy compliance

**Expected Results**:
- [ ] Author names are partial/masked (e.g., "John D.")
- [ ] No email addresses or full names displayed
- [ ] Cache TTL respects Google's policy (no permanent storage)
- [ ] Reviews text is excerpt only (not full content)

## Test Environment Details
- **WordPress Version**: ___________
- **WooCommerce Version**: ___________
- **FP Esperienze Version**: ___________
- **PHP Version**: ___________
- **Theme**: ___________
- **Test Date**: ___________

## Test Results Summary
- **Total Tests**: 10
- **Passed**: ___/10
- **Failed**: ___/10
- **Notes**: ___________

## Issues Found
[List any issues discovered during testing]

## Performance Notes
[Note any performance observations]

## Browser Compatibility
- [ ] Chrome (latest)
- [ ] Firefox (latest)  
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari
- [ ] Mobile Chrome