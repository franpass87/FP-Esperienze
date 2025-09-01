# Manual Testing for Fixed Schedule Builder and Overrides

## Test Environment Setup
1. Ensure FP Esperienze plugin is active
2. Go to WordPress Admin → Products → Add New
3. Set Product Type to "Experience"
4. Navigate to the "Experience" tab

## Test 1: Basic Override Toggle Functionality

### Steps:
1. Fill in product defaults:
   - Default Duration: 60 minutes
   - Default Max Capacity: 10 people
   - Default Language: en
   - Default Child Price: €15.00
2. Click "Add Time Slot"
3. Set Start Time: 09:00
4. Select Days: Monday, Wednesday, Friday
5. Check the "Advanced Settings" toggle

### Expected Results:
- ✅ Override section should become visible
- ✅ All override fields should show proper placeholders (Default: 60, Default: 10, etc.)
- ✅ Hidden field `advanced_enabled` should be set to "1"

## Test 2: Auto-Enable Advanced Settings

### Steps:
1. Add another time slot with Start Time: 14:00
2. Select Days: Tuesday, Thursday
3. **Without checking the Advanced Settings toggle**, enter values in override fields:
   - Duration: 90 minutes
   - Capacity: 15 people
4. Click somewhere else to trigger change event

### Expected Results:
- ✅ Advanced Settings toggle should automatically become checked
- ✅ Override section should become visible
- ✅ Values should remain in the fields

## Test 3: Override Detection Logic

### Steps:
1. Create a time slot with:
   - Time: 16:00
   - Days: Saturday, Sunday
   - Check Advanced Settings
   - Enter Duration: 60 (same as default)
   - Enter Capacity: 12 (different from default)
2. Save the product
3. Reload the page

### Expected Results:
- ✅ Only the time slot with actual overrides should show Advanced Settings enabled
- ✅ Time slot with duration=60 (same as default) should not show as having overrides
- ✅ Time slot with capacity=12 (different from default) should show Advanced Settings enabled

## Test 4: Schedule Persistence and Aggregation

### Steps:
1. Create multiple time slots:
   - Slot A: 09:00, Mon/Wed/Fri, no overrides
   - Slot B: 14:00, Tue/Thu, Duration: 90, Price Adult: €35
   - Slot C: 10:00, Saturday, Capacity: 8, Language: it
2. Save the product
3. Reload the page

### Expected Results:
- ✅ All three time slots should be preserved
- ✅ Slot A should show no advanced settings (inherits defaults)
- ✅ Slot B should show advanced settings with Duration and Price Adult filled
- ✅ Slot C should show advanced settings with Capacity and Language filled
- ✅ Empty override fields should remain empty (not filled with defaults)

## Test 5: Form Validation

### Steps:
1. Add a time slot with Start Time but no days selected
2. Try to save the product

### Expected Results:
- ✅ Validation error should appear
- ✅ Days section should be highlighted in red
- ✅ Product should not save until fixed

## Test 6: Summary Table Updates

### Steps:
1. Create a time slot: 11:00, Mon/Wed, no overrides
2. Check Advanced Settings and add Duration: 120
3. Observe the summary table

### Expected Results:
- ✅ Summary table should show "1 setting" in Customized column
- ✅ Duration should show "120 min" instead of "Default"
- ✅ Updates should happen immediately without page refresh

## Test 7: Override Value Clearing

### Steps:
1. Create a time slot with Advanced Settings enabled and some override values
2. Uncheck the Advanced Settings toggle
3. Re-check the Advanced Settings toggle

### Expected Results:
- ✅ Override values should be preserved (not automatically cleared)
- ✅ User can manually clear values if desired
- ✅ Toggle state should be consistent with actual override values

## Test 8: Database Integration

### Steps:
1. Create time slots with various override combinations
2. Save the product
3. Check the database tables `wp_fp_schedules`

### Expected Results:
- ✅ Schedule records should have NULL values for fields that inherit defaults
- ✅ Schedule records should have actual values for overridden fields
- ✅ Each day should create a separate schedule record
- ✅ start_time and day_of_week should always be populated

## Expected Behavior Summary

### Fixed Issues:
1. **Override Toggle Sync**: Advanced Settings toggle now properly reflects actual override state
2. **Auto-Enable**: Entering override values automatically enables Advanced Settings
3. **Persistent Values**: Override values are preserved when toggling Advanced Settings
4. **Accurate Detection**: Only fields that actually differ from defaults count as overrides
5. **Better Aggregation**: All schedules can be represented in the builder interface
6. **Null Handling**: Proper inheritance when database values are NULL

### Validation Rules:
- Start time is required
- At least one day must be selected
- Override values have minimum constraints (duration ≥ 1, capacity ≥ 1, prices ≥ 0)
- Time format must be HH:MM

## Troubleshooting

If tests fail:
1. Check browser console for JavaScript errors
2. Verify WordPress/WooCommerce compatibility
3. Check PHP error logs for backend issues
4. Ensure database tables exist and are properly structured