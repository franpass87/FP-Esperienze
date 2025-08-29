# Manual Test Instructions for Advanced Settings Bug Fix

## Issue Description
This document provides step-by-step instructions to test the fixes for the following issues:
1. Advanced Settings checkbox getting auto-enabled after save
2. Capacity field defaulting to 1 when it should remain empty
3. Double blocks appearing when adding time slots or overrides

## Before Testing
Ensure you have applied the fixes in commit f9912cd.

## Test Case 1: Advanced Settings Auto-Enable Issue

### Setup:
1. Navigate to WordPress admin → Products → Add New
2. Set product type to "Experience"
3. Go to the "Experience" tab

### Test Steps:
1. Add a time slot with just a start time (e.g., 12:00) and select some days
2. Do NOT check the "Advanced Settings" checkbox
3. Leave all override fields empty
4. Save the product
5. Refresh the page or re-edit the product

### Expected Result:
- The "Advanced Settings" checkbox should remain UNCHECKED
- No override values should be visible in the fields

### If Test Fails:
- The checkbox appears checked when it shouldn't be
- This indicates the `hasActualOverrides()` method is not working properly

## Test Case 2: Capacity Defaulting to 1

### Setup:
1. Create or edit an experience product
2. Add a time slot

### Test Steps:
1. Click on "Advanced Settings" to expand the overrides section
2. Do NOT enter any value in the "Capacity" field (leave it empty)
3. Enter some other override value (e.g., Duration)
4. Save the product
5. Re-edit the product and check the capacity field

### Expected Result:
- The capacity field should remain empty
- Only the fields you actually filled should show values

### If Test Fails:
- The capacity field shows "1" when it should be empty
- This indicates browser validation or server-side processing is setting default values

## Test Case 3: Double Blocks Issue

### Setup:
1. Create or edit an experience product
2. Go to the Experience tab

### Test Steps:
1. Click "Add Time Slot" button once
2. Observe how many time slot blocks are added
3. Click "Add Date Override" button once (if available)
4. Observe how many override blocks are added

### Expected Result:
- Clicking "Add Time Slot" once should add exactly ONE time slot block
- Clicking "Add Date Override" once should add exactly ONE override block

### If Test Fails:
- Two blocks appear when clicking once
- This indicates double event binding in JavaScript

## Test Case 4: Advanced Settings State Persistence

### Setup:
1. Create an experience product with a time slot
2. Enable Advanced Settings and enter some override values that differ from defaults

### Test Steps:
1. Check "Advanced Settings" checkbox
2. Enter a capacity value different from the default (e.g., if default is 10, enter 15)
3. Save the product
4. Re-edit the product

### Expected Result:
- Advanced Settings checkbox should be checked
- The override values should be preserved
- The advanced settings section should be visible

## Test Case 5: Mixed State Handling

### Setup:
1. Create an experience product with multiple time slots

### Test Steps:
1. Add 3 time slots
2. For slot 1: Enable advanced settings and set capacity to 5
3. For slot 2: Leave advanced settings disabled
4. For slot 3: Enable advanced settings and set duration to 90 minutes
5. Save the product
6. Re-edit the product

### Expected Result:
- Slot 1: Advanced settings checked, capacity shows 5
- Slot 2: Advanced settings unchecked, no override values
- Slot 3: Advanced settings checked, duration shows 90

## Verification Commands

If you have access to the database, you can verify the fix by checking the schedules table:

```sql
-- Check that schedules without advanced settings have NULL override values
SELECT id, product_id, start_time, duration_min, capacity 
FROM fp_experience_schedules 
WHERE product_id = [YOUR_PRODUCT_ID];
```

Expected: Records for time slots without advanced settings should have NULL values for override fields.

## Browser Console Testing

Open browser developer tools and check for JavaScript errors when:
1. Adding time slots
2. Toggling advanced settings
3. Adding overrides

No JavaScript errors should appear in the console.

## Rollback Instructions

If tests fail and you need to rollback:
```bash
git checkout [previous_commit_hash] -- includes/ProductType/Experience.php assets/js/admin.js
```