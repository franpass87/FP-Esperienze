# Manual Test Cases for Schedules and Overrides Feature

This document provides step-by-step manual test cases to verify the schedules and overrides functionality.

## Prerequisites

1. WordPress site with WooCommerce installed
2. FP Esperienze plugin activated
3. At least one Experience product created
4. Admin access to WordPress

## Test Case 1: Normal Day with Schedules

### Purpose
Verify that regular schedules work correctly and availability is calculated properly.

### Steps to Test

1. **Create Meeting Point** (if none exists):
   - Go to FP Esperienze > Meeting Points
   - Add a new meeting point with name "Test Location" and address

2. **Configure Experience Product**:
   - Go to Products > Add New (or edit existing experience)
   - Set Product Type to "Experience"
   - In the Experience tab, configure:
     - Duration: 60 minutes
     - Max Capacity: 10 
     - Adult Price: €50.00
     - Child Price: €25.00
     - Languages: "English, Italian"
     - Default Meeting Point: Select "Test Location"

3. **Add Schedules**:
   - In the Schedules section, click "Add Schedule"
   - Configure first schedule:
     - Day: Monday
     - Start Time: 09:00
     - Duration: 60 minutes
     - Capacity: 8
     - Language: en
     - Meeting Point: Test Location
     - Adult Price: 50.00
     - Child Price: 25.00
   - Click "Add Schedule" again for second schedule:
     - Day: Monday
     - Start Time: 14:00
     - Duration: 90 minutes
     - Capacity: 6
     - Language: it
     - Meeting Point: Test Location
     - Adult Price: 60.00
     - Child Price: 30.00
   - Save the product

4. **Test Availability API**:
   - Navigate to: `/wp-json/fp-exp/v1/availability?product_id=[PRODUCT_ID]&date=2024-12-23` (next Monday)
   - Verify response contains 2 slots:
     - 09:00-10:00 slot with capacity 8, adult price 50, child price 25
     - 14:00-15:30 slot with capacity 6, adult price 60, child price 30

### Expected Results
- ✅ Schedules are saved correctly
- ✅ Availability API returns correct slots for the configured day
- ✅ Prices and capacities match the schedule configuration
- ✅ No slots are returned for days without schedules

---

## Test Case 2: Day with Closure

### Purpose
Verify that global closures and product-specific closures work correctly.

### Steps to Test

1. **Test Global Closure**:
   - Go to FP Esperienze > Closures
   - Add a global closure:
     - Date: 2024-12-25 (Christmas)
     - Reason: "Christmas Holiday"
   - Save the closure

2. **Test Product-Specific Closure**:
   - Edit your Experience product
   - In the Date Overrides section, click "Add Override"
   - Configure override:
     - Date: 2024-12-24 (Christmas Eve)
     - Check "Closed" checkbox
     - Reason: "Christmas Eve - Closed"
   - Save the product

3. **Verify Closures in Admin**:
   - Go to FP Esperienze > Closures
   - Verify both closures are listed
   - Test removing a closure (try removing Christmas Eve)

4. **Test Availability API with Closures**:
   - Test Christmas Day: `/wp-json/fp-exp/v1/availability?product_id=[PRODUCT_ID]&date=2024-12-25`
   - Test Christmas Eve: `/wp-json/fp-exp/v1/availability?product_id=[PRODUCT_ID]&date=2024-12-24`

### Expected Results
- ✅ Global closures are created and affect all experience products
- ✅ Product-specific closures only affect the specific product
- ✅ Availability API returns empty slots array for closed dates
- ✅ Closures can be removed from the admin interface

---

## Test Case 3: Day with Price/Capacity Override

### Purpose
Verify that price and capacity overrides work correctly without closing the day.

### Steps to Test

1. **Configure Price Override**:
   - Edit your Experience product
   - In the Date Overrides section, click "Add Override"
   - Configure override for a Monday with existing schedules:
     - Date: 2024-12-30 (a Monday)
     - Leave "Closed" unchecked
     - Capacity Override: 15
     - Adult Price: 40.00
     - Child Price: 20.00
     - Reason: "New Year Special Pricing"
   - Save the product

2. **Test Another Override (Capacity Only)**:
   - Add another override:
     - Date: 2025-01-06 (another Monday)
     - Leave "Closed" unchecked
     - Capacity Override: 12
     - Leave prices empty
     - Reason: "Increased capacity for high demand"
   - Save the product

3. **Test Availability API with Overrides**:
   - Test New Year pricing: `/wp-json/fp-exp/v1/availability?product_id=[PRODUCT_ID]&date=2024-12-30`
   - Test capacity override: `/wp-json/fp-exp/v1/availability?product_id=[PRODUCT_ID]&date=2025-01-06`
   - Test normal Monday: `/wp-json/fp-exp/v1/availability?product_id=[PRODUCT_ID]&date=2025-01-13`

### Expected Results
- ✅ Price overrides apply the new prices to all slots on that date
- ✅ Capacity overrides change the capacity for all slots on that date
- ✅ When only capacity is overridden, original prices are maintained
- ✅ When only prices are overridden, original capacity is maintained
- ✅ Normal days without overrides use the schedule defaults

---

## Additional Verification Tests

### Timezone Handling
1. Change WordPress timezone in Settings > General
2. Test availability API and verify times are correctly handled in the configured timezone
3. Verify schedule start times are interpreted in WordPress timezone

### Data Persistence
1. Create schedules and overrides
2. Deactivate and reactivate the plugin
3. Verify all data is still present

### User Interface
1. Test adding/removing schedules in the product admin
2. Test adding/removing overrides in the product admin
3. Verify JavaScript functions work correctly
4. Test the global closures interface

### API Error Handling
1. Test availability API with invalid product ID
2. Test availability API with invalid date format
3. Test availability API with past dates
4. Verify appropriate error responses

---

## Troubleshooting

If tests fail, check:
1. Database tables were created correctly (wp_fp_schedules, wp_fp_overrides)
2. Plugin is activated and autoloader is working
3. WooCommerce is active and compatible
4. JavaScript console for any errors
5. PHP error logs for backend issues

## Performance Notes

- The availability calculation queries the database for each request
- For high-traffic sites, consider implementing caching
- Monitor query performance with many schedules/overrides