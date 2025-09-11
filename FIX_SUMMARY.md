# FP Esperienze - Time Slots Saving Fix

## Issue Summary
**Problem**: Time slots with advanced settings were not appearing after saving in the Recurring Time Slots interface.

**Root Cause**: The `ScheduleManager::getSchedules()` method was using a limited SELECT statement that excluded important override columns.

**Solution**: Updated the SELECT query to use `SELECT *` to include all columns.

## Technical Details

### Before (Broken)
```sql
SELECT id, product_id, day_of_week, start_time, end_time, max_participants, is_active 
FROM wp_fp_schedules 
WHERE product_id = %d AND is_active = 1 
ORDER BY day_of_week, start_time
```

### After (Fixed)  
```sql
SELECT * 
FROM wp_fp_schedules 
WHERE product_id = %d AND is_active = 1 
ORDER BY day_of_week, start_time
```

### Missing Columns That Are Now Included
- `duration_min` - Override duration for specific time slots
- `capacity` - Override capacity for specific time slots  
- `lang` - Override language for specific time slots
- `meeting_point_id` - Override meeting point for specific time slots
- `price_adult` - Override adult price for specific time slots
- `price_child` - Override child price for specific time slots

## Manual Testing Steps

1. Go to WordPress Admin → Products → Add New Product
2. Set Product Type to "Experience"
3. Navigate to the "Recurring Time Slots" tab
4. Click "Add Time Slot" button
5. Fill in basic information (Start Time: 09:00, Days: Mon/Wed/Fri)
6. Enable "Advanced Settings" and fill override values:
   - Duration: 120 minutes
   - Capacity: 8 people
   - Language: English
   - Adult Price: 45.00
   - Child Price: 25.00
7. Save the product
8. **VERIFY**: Reload page and confirm all values are preserved

## Expected Results After Fix
✅ Time slots persist after saving
✅ Advanced settings checkbox remains checked
✅ All override values are displayed correctly
✅ Summary table shows accurate information
✅ Data can be edited and re-saved without loss

## Files Modified
- `includes/Data/ScheduleManager.php` (Line 28 - SELECT query)

## Impact
This is a minimal, surgical fix that resolves the user-reported issue without affecting any other functionality. The change is backward-compatible and only improves data retrieval.

---

## Booking Order Item Uniqueness

- Added `UNIQUE KEY order_item_unique (order_id, order_item_id)` to the bookings table to prevent duplicate order item entries.
- Installer now ensures existing installations receive this unique index during upgrades.