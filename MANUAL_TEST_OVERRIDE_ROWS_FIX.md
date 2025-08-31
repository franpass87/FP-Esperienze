# Manual Test: Override Rows Duplication Fix

## Issue Fixed
**Problem**: When creating a product and clicking "Add Date Override", two rows appeared instead of one, causing layout issues and making the graphics go off-screen. Additionally, the remove functionality was not working properly.

**Root Causes**: 
1. Conflicting CSS rules between flex and grid layouts for `.fp-override-row`
2. Potential double event binding in JavaScript
3. Layout overflow issues with fixed grid column widths

## Fix Applied

### CSS Changes (`assets/css/admin.css`)
1. **Removed conflicting flex layout** - Separated schedule and override row styles
2. **Enhanced grid responsiveness** - Used `minmax()` for responsive column sizing
3. **Added overflow protection** - Added `overflow: hidden`, `max-width: 100%`, `box-sizing: border-box`
4. **Container improvements** - Added `overflow-x: auto` for wide content handling

### JavaScript Changes (`assets/js/admin.js`)
1. **Enhanced initialization prevention** - Moved check outside DOM ready
2. **Namespaced events** - Used `.fp-override` to prevent conflicts  
3. **Event cleanup** - Added `off()` calls before binding
4. **Improved error handling** - Added container validation
5. **Better UX** - Added automatic focus to date input

## Manual Testing Steps

### Prerequisites
1. WordPress with WooCommerce installed and activated
2. FP Esperienze plugin activated  
3. Admin access to WordPress
4. Modern browser with Developer Tools available

### Test 1: Create Experience Product with Override Rows

1. **Go to WordPress Admin → Products → Add New**
2. **Enter a product title** (e.g., "Test Experience with Overrides")
3. **Select "Experience" from the Product Type dropdown**
4. **Fill in basic experience information**:
   - Duration: 120 minutes
   - Capacity: 10  
   - Adult Price: 50€
   - Child Price: 25€
5. **Scroll to "Date-Specific Overrides" section**
6. **Click "Add Date Override" button**

#### Expected Results:
- ✅ Only ONE new override row should appear
- ✅ The new row should have all fields: Date, Closed checkbox, Capacity, Adult €, Child €, Reason, Remove button
- ✅ The layout should stay within screen bounds (no horizontal overflow)
- ✅ The date input should automatically receive focus

### Test 2: Verify Multiple Override Rows

1. **Click "Add Date Override" button again** (2-3 more times)
2. **Check browser console** (F12 → Console) for any JavaScript errors

#### Expected Results:
- ✅ Each click should create exactly ONE new row
- ✅ All rows should be properly laid out in a grid
- ✅ No JavaScript errors in console
- ✅ Row indexes should increment properly (0, 1, 2, 3...)

### Test 3: Test Remove Functionality

1. **In any existing override row, click the "Remove" button**
2. **Try removing multiple rows**

#### Expected Results:
- ✅ Clicking "Remove" should delete only that specific row
- ✅ Other rows should remain intact
- ✅ No layout shifting or breaking

### Test 4: Test Layout Responsiveness

1. **Resize browser window to smaller widths** (simulate mobile/tablet)
2. **Check override rows layout**

#### Expected Results:
- ✅ Grid columns should adjust to available space using `minmax()`
- ✅ No horizontal scrolling required for override section
- ✅ All fields remain accessible and usable

### Test 5: Test Form Submission

1. **Fill in several override rows with different data**:
   - Row 1: Date 2024-02-14, Closed ✓
   - Row 2: Date 2024-02-15, Capacity 20, Adult €75, Child €40, Reason "Valentine's Day"
   - Row 3: Date 2024-03-01, Adult €30, Reason "March promotion"
2. **Save the product**
3. **Reload/re-edit the product**

#### Expected Results:
- ✅ Product saves without errors
- ✅ All override data is preserved
- ✅ Override rows display correctly when re-editing

### Test 6: Browser Compatibility

Test the functionality in different browsers:
- Chrome/Chromium
- Firefox  
- Safari (if available)
- Edge

#### Expected Results:
- ✅ Consistent behavior across all browsers
- ✅ No browser-specific layout issues

## Debugging Tips

If issues occur:

1. **Check browser console** for JavaScript errors
2. **Inspect element** to verify CSS grid properties are applied
3. **Verify jQuery is loaded** on the page
4. **Check for plugin conflicts** by temporarily deactivating other plugins

## Success Criteria

The fix is successful if:
- ✅ Only one row is created per button click
- ✅ Layout stays within screen bounds on all screen sizes
- ✅ Remove buttons work correctly 
- ✅ No JavaScript errors occur
- ✅ Form data saves and loads properly
- ✅ Works consistently across different browsers

## Screenshots

Take screenshots of:
1. Override rows section with multiple rows (showing proper grid layout)
2. Browser console (showing no errors)
3. Mobile/responsive view of override rows
4. Successfully saved product with override data

## Regression Testing

Also verify that the fix doesn't break:
- Schedule management functionality
- Other product type features
- General WooCommerce product editing
- Plugin's other admin interfaces