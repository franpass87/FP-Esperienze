# Schedule Builder Implementation - Manual Testing Guide

## Overview
This guide helps you manually test the new Schedule Builder implementation for FP Esperienze Experience products.

## Prerequisites
1. WordPress installation with WooCommerce active
2. FP Esperienze plugin installed and activated
3. Admin access to create/edit products

## Test Scenarios

### Test 1: Basic Schedule Builder Functionality

**Objective**: Verify the Schedule Builder UI appears and works correctly

**Steps**:
1. Go to WordPress Admin → Products → Add New
2. Set Product Type to "Experience" 
3. Navigate to the "Experience" tab
4. Scroll to the "Weekly Schedules" section

**Expected Results**:
- Schedule Builder interface is visible with "Weekly Programming" section
- "Add Time Slot" button is present
- Toggle for "Show Advanced Mode" is available

### Test 2: Product Defaults Setup

**Objective**: Configure product-level defaults that schedules will inherit

**Steps**:
1. In the Experience tab, fill in the product defaults:
   - Default Duration: 90 minutes
   - Default Max Capacity: 12 people
   - Default Language: it
   - Default Child Price: €15.00
   - Default Meeting Point: Select from dropdown
2. Save the product

**Expected Results**:
- All default values are saved correctly
- These values will be used as placeholders in override fields

### Test 3: Create Simple Time Slot (Inheritance)

**Objective**: Create a time slot that inherits all defaults

**Steps**:
1. Click "Add Time Slot"
2. Set Start Time: 09:00
3. Select Days: Monday, Wednesday, Friday
4. Do NOT check "Show advanced overrides"
5. Save the product

**Expected Results**:
- 3 individual schedule records created in database (one per day)
- All schedules inherit default values (duration=90, capacity=12, etc.)
- Availability API should show correct effective values

### Test 4: Create Time Slot with Overrides

**Objective**: Create a time slot with specific overrides

**Steps**:
1. Click "Add Time Slot" 
2. Set Start Time: 14:30
3. Select Days: Tuesday, Thursday, Saturday, Sunday
4. Check "Show advanced overrides"
5. Set overrides:
   - Duration: 120 minutes
   - Adult Price: €35.00
   - (Leave other fields empty to inherit)
6. Save the product

**Expected Results**:
- 4 individual schedule records created
- Duration override is used (120 min)
- Adult price override is used (€35.00)
- Other values inherit from defaults (capacity=12, child price=€15.00, etc.)

### Test 5: Advanced Mode Toggle

**Objective**: Verify backward compatibility with raw schedule editing

**Steps**:
1. In the Weekly Schedules section, check "Show Advanced Mode"
2. Verify the raw schedule interface appears
3. Try editing individual schedule rows
4. Uncheck "Show Advanced Mode"

**Expected Results**:
- Raw schedule interface shows/hides correctly
- Individual schedule rows are editable in advanced mode
- Schedule Builder remains the default interface

### Test 6: Aggregation of Existing Schedules

**Objective**: Test how existing schedules are loaded into the builder

**Prerequisites**: Have some existing schedules in the database

**Steps**:
1. Edit an Experience product that has existing schedules
2. Check how schedules are grouped in the builder
3. Verify time slots are properly aggregated by time + properties

**Expected Results**:
- Schedules with same time/overrides are grouped into single time slots
- Different days are combined in the day checkboxes
- Schedules that can't be aggregated appear in raw mode

### Test 7: Validation Testing

**Objective**: Test form validation

**Steps**:
1. Try to create a time slot without selecting any days
2. Try to create a time slot without setting start time
3. Enter invalid time format
4. Enter negative values in override fields

**Expected Results**:
- Appropriate validation messages appear
- Invalid data is not saved
- Form provides clear feedback to user

### Test 8: Availability API Integration

**Objective**: Verify effective values work in availability calculation

**Steps**:
1. Create schedules with mixed inheritance and overrides
2. Test availability API endpoint:
   ```
   GET /wp-json/fp-exp/v1/availability?product_id=X&date=2024-12-25
   ```
3. Check returned slot data

**Expected Results**:
- API returns correct effective values (inherited + overridden)
- Capacity, prices, duration reflect final calculated values
- No NULL values in API response

### Test 9: Migration Feature Flag (Optional)

**Objective**: Test database migration functionality

**Steps**:
1. Set `FP_ESPERIENZE_ENABLE_SCHEDULE_NULL_MIGRATION` to `true` in fp-esperienze.php
2. Deactivate and reactivate the plugin
3. Check database for NULL values in schedule override fields
4. Verify schedules still work correctly

**Expected Results**:
- Schedule table columns become nullable
- Redundant data is cleaned up (set to NULL where it matches defaults)
- No functionality is broken
- Migration is marked as completed

## Troubleshooting

### Issue: Schedule Builder doesn't appear
**Check**: Product type is set to "Experience"
**Solution**: Ensure JavaScript is loading correctly, check browser console for errors

### Issue: Overrides not working
**Check**: Values are being saved as NULL when empty
**Solution**: Verify ScheduleManager::createSchedule() supports NULL values

### Issue: Inheritance not working
**Check**: Product defaults are properly set
**Solution**: Verify ScheduleHelper::hydrateEffectiveValues() logic

### Issue: Existing schedules not loading correctly
**Check**: Aggregation logic in ScheduleHelper::aggregateSchedulesForBuilder()
**Solution**: Verify schedule grouping and clustering logic

## Success Criteria

- [ ] Schedule Builder UI loads correctly for Experience products
- [ ] Time slots can be created with day selection and start time
- [ ] Override toggle shows/hides advanced fields
- [ ] Empty override fields inherit from product defaults
- [ ] Filled override fields use specific values
- [ ] Multiple time slots can be managed independently
- [ ] Advanced mode provides access to raw schedule editing
- [ ] Form validation prevents invalid data entry
- [ ] Availability API returns correct effective values
- [ ] Existing schedules are preserved and work correctly
- [ ] Database migration (if enabled) completes without errors

## Performance Notes

- Schedule Builder should handle up to 50+ time slots without significant lag
- Form submission should complete within 2-3 seconds for typical use cases
- Availability API response time should remain under 1 second
- Database queries should be optimized and not cause N+1 problems