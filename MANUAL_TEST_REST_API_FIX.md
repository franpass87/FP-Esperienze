# Manual Test: REST API Availability Fix

## Problem Fixed
Fixed the REST API route registration timing issue where `/wp-json/fp-exp/v1/availability` was returning "Nessun percorso fornisce una corrispondenza tra l'URL ed il metodo richiesto" (No route provides a match between the URL and the requested method).

## Root Cause
REST API classes were adding `add_action('rest_api_init', [$this, 'registerRoutes'])` in their constructors, but since they were already being instantiated FROM within a `rest_api_init` callback, the route registration was happening too late.

## Fix Applied
Changed all REST API class constructors to call `registerRoutes()` immediately instead of deferring with another `rest_api_init` action.

## Test Steps

### 1. Test REST API Endpoints Directly

**Before testing, ensure you have:**
- WordPress with WooCommerce installed
- FP Esperienze plugin activated
- At least one Experience product created

**Test the availability endpoint:**

```bash
# Replace YOUR_SITE_URL with your actual WordPress site URL
# Replace 123 with an actual experience product ID
curl -X GET "YOUR_SITE_URL/wp-json/fp-exp/v1/availability?product_id=123&date=2025-08-30"
```

**Expected result:**
- Status: 200 OK
- Response format:
```json
{
  "product_id": 123,
  "date": "2025-08-30",
  "slots": [
    {
      "start_time": "09:00",
      "end_time": "10:30",
      "adult_price": 25.00,
      "child_price": 15.00,
      "available": 8,
      "is_available": true
    }
  ],
  "total_slots": 1
}
```

**Test error cases:**
```bash
# Invalid date format
curl -X GET "YOUR_SITE_URL/wp-json/fp-exp/v1/availability?product_id=123&date=invalid"

# Past date
curl -X GET "YOUR_SITE_URL/wp-json/fp-exp/v1/availability?product_id=123&date=2020-01-01"

# Non-existent product
curl -X GET "YOUR_SITE_URL/wp-json/fp-exp/v1/availability?product_id=99999&date=2025-08-30"
```

### 2. Test Frontend Booking Widget

**Steps:**
1. Navigate to an Experience product page
2. Open browser developer tools (F12)
3. Go to Network tab
4. Select a future date in the date picker
5. Observe the AJAX request to `/wp-json/fp-exp/v1/availability`

**Expected results:**
- ✅ AJAX request returns status 200
- ✅ Response contains availability data
- ✅ Time slots appear in the widget
- ✅ No error message displayed
- ✅ Console shows no JavaScript errors

**Before the fix:**
- ❌ AJAX request returns 404 
- ❌ Error message: "Nessun percorso fornisce una corrispondenza tra l'URL ed il metodo richiesto"
- ❌ No time slots displayed

### 3. Test All REST API Endpoints

**Verify all endpoints are working:**

```bash
# Bookings API (requires authentication)
curl -X GET "YOUR_SITE_URL/wp-json/fp-exp/v1/bookings" -H "Authorization: Bearer YOUR_TOKEN"

# ICS API (public)
curl -X GET "YOUR_SITE_URL/wp-json/fp-esperienze/v1/ics/product/123"

# Events API (requires authentication)
curl -X GET "YOUR_SITE_URL/wp-json/fp-esperienze/v1/events" -H "Authorization: Bearer YOUR_TOKEN"

# Voucher PDF API (requires special nonce)
curl -X GET "YOUR_SITE_URL/wp-json/fp-exp/v1/voucher/456/pdf?nonce=YOUR_NONCE"
```

### 4. Browser Console Testing

**JavaScript test in browser console:**

```javascript
// Test availability API call directly
fetch('/wp-json/fp-exp/v1/availability?product_id=123&date=2025-08-30')
  .then(response => response.json())
  .then(data => console.log('Success:', data))
  .catch(error => console.error('Error:', error));
```

**Expected:** Data object with slots array

### 5. WordPress Admin Testing

**Check WordPress admin:**
1. Go to Tools > Site Health
2. Look for any REST API related issues
3. Verify no fatal errors in debug.log

## Verification Checklist

- [ ] REST API endpoint `/wp-json/fp-exp/v1/availability` returns 200 OK
- [ ] Frontend booking widget loads time slots without errors
- [ ] No "Nessun percorso..." error messages in frontend
- [ ] All other REST endpoints still working
- [ ] No PHP fatal errors in logs
- [ ] JavaScript console shows no errors
- [ ] Date picker functionality works correctly
- [ ] Time slot selection works properly

## Technical Details

**Files Modified:**
- `includes/REST/AvailabilityAPI.php`
- `includes/REST/BookingsAPI.php` 
- `includes/REST/BookingsController.php`
- `includes/REST/ICSAPI.php`
- `includes/REST/SecurePDFAPI.php`

**Change Made:**
```php
// Before (problematic)
public function __construct() {
    add_action('rest_api_init', [$this, 'registerRoutes']);
}

// After (fixed)
public function __construct() {
    // Register routes immediately since this is already called from rest_api_init
    $this->registerRoutes();
}
```

## Notes

- This fix ensures all REST API routes are registered immediately when the classes are instantiated
- The timing issue was specific to the deferred registration pattern
- All REST endpoints now register reliably in both frontend and admin contexts
- No breaking changes to existing functionality