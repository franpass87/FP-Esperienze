# Manual Test Instructions for Auto-Enable Advanced Settings Fix

## Issue Description
Fixed the issue where recurring time slots automatically enable advanced settings after save, even when the user doesn't want them enabled.

## Problem
The JavaScript auto-enable logic was too aggressive and would automatically check the "Advanced Settings" checkbox whenever override fields contained values, ignoring the user's explicit preference to keep advanced settings disabled.

## Solution
Modified the auto-enable logic to:
1. Track when a user explicitly disables advanced settings
2. Respect user intent by not re-enabling advanced settings automatically if they were explicitly disabled
3. Still provide helpful auto-enabling when the user is actively entering values for the first time

## Test Cases

### Test Case 1: User Explicitly Disables Advanced Settings
**Setup:**
1. Navigate to WordPress admin → Products → Add New
2. Set product type to "Experience"
3. Go to the "Experience" tab

**Test Steps:**
1. Add a time slot with start time (e.g., 12:00) and select some days
2. Click "Advanced Settings" to enable it
3. Enter some override values (e.g., capacity: 5)
4. Click "Advanced Settings" again to DISABLE it (unchecking the checkbox)
5. Save the product
6. Re-edit the product

**Expected Result:**
- The "Advanced Settings" checkbox should remain UNCHECKED
- The override values should still be preserved in the fields
- The override section should be hidden

**Before Fix:**
- Advanced settings would be automatically re-enabled because override fields had values

### Test Case 2: First-Time Auto-Enable Still Works
**Setup:**
1. Create a new experience product
2. Add a time slot without enabling advanced settings

**Test Steps:**
1. Don't check "Advanced Settings"
2. Enter a value in an override field (this should auto-enable advanced settings)
3. Verify that advanced settings is now enabled
4. Save the product
5. Re-edit the product

**Expected Result:**
- Advanced settings should be automatically enabled when typing in override fields
- After save, advanced settings should remain enabled
- Override values should be preserved

### Test Case 3: Auto-Disable When All Values Cleared
**Setup:**
1. Create a time slot with advanced settings enabled
2. Have some override values entered

**Test Steps:**
1. Clear all override values (leave fields empty)
2. Observe if advanced settings gets auto-disabled
3. Save and re-edit

**Expected Result:**
- Advanced settings should auto-disable when all values are cleared
- After save, advanced settings should remain disabled

### Test Case 4: Clean Version Compatibility
**Setup:**
1. Test with the clean/modern version of time slot cards (if available)

**Test Steps:**
1. Repeat all above tests with the clean version interface
2. Verify the same behavior applies

**Expected Result:**
- Same behavior as the regular version

## Files Modified
- `assets/js/admin.js`: Added user-disabled tracking logic
- Added handlers for both regular and clean versions

## Code Changes Summary
1. Added `user-disabled` data tracking to remember when user explicitly disables advanced settings
2. Modified auto-enable logic to respect user intent
3. Updated both regular and clean version handlers
4. Maintained existing auto-enable/disable functionality for new users

## Verification
After applying this fix, users should have full control over their advanced settings preference, while still getting helpful auto-enabling when appropriate.